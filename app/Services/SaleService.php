<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\Customer;
use App\Models\CreditSale;
use App\Models\StockMovement;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Process a complete sale transaction (POS checkout).
     *
     * @throws ValidationException
     * @throws \Throwable
     */
    public function processSale(array $data, int $tenantId, int $shopId, int $userId): Sale
    {
        return DB::transaction(function () use ($data, $tenantId, $shopId, $userId) {
            // 1. Validate items and check stock
            $items = $this->validateAndPrepareItems($data['items'], $shopId);

            // 2. Calculate totals
            $totals = $this->calculateTotals($items, $data);

            // 3. Create sale record
            $sale = Sale::create([
                'tenant_id'        => $tenantId,
                'shop_id'          => $shopId,
                'user_id'          => $userId,
                'customer_id'      => $data['customer_id'] ?? null,
                'local_id'         => $data['local_id'] ?? null,
                'subtotal'         => $totals['subtotal'],
                'discount_amount'  => $totals['discount_amount'],
                'discount_percent' => $data['discount_percent'] ?? 0,
                'tax_amount'       => $totals['tax_amount'],
                'tax_percent'      => $data['tax_percent'] ?? 0,
                'total'            => $totals['total'],
                'amount_paid'      => $data['amount_paid'],
                'change_given'     => max(0, $data['amount_paid'] - $totals['total']),
                'credit_amount'    => max(0, $totals['total'] - $data['amount_paid']),
                'payment_method'   => $data['payment_method'] ?? 'cash',
                'payment_breakdown'=> $data['payment_breakdown'] ?? null,
                'status'           => 'completed',
                'note'             => $data['note'] ?? null,
                'reference'        => $data['reference'] ?? null,
                'is_credit_sale'   => ($data['amount_paid'] < $totals['total']),
                'device_id'        => $data['device_id'] ?? null,
                'synced_at'        => $data['local_id'] ? now() : null,
            ]);

            // 4. Create sale items and deduct stock
            foreach ($items as $item) {
                $profit = ($item['unit_price'] - $item['cost_price']) * $item['quantity'];
                $profit -= ($item['discount_amount'] ?? 0);

                SaleItem::create([
                    'sale_id'         => $sale->id,
                    'product_id'      => $item['product_id'],
                    'product_name'    => $item['product_name'],
                    'product_unit'    => $item['unit'],
                    'quantity'        => $item['quantity'],
                    'unit_price'      => $item['unit_price'],
                    'cost_price'      => $item['cost_price'],
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'discount_percent'=> $item['discount_percent'] ?? 0,
                    'tax_amount'      => $item['tax_amount'] ?? 0,
                    'subtotal'        => $item['subtotal'],
                    'profit'          => $profit,
                ]);

                // Deduct stock
                if ($item['product']->track_inventory) {
                    $this->inventoryService->deductStock(
                        product: $item['product'],
                        quantity: $item['quantity'],
                        referenceType: Sale::class,
                        referenceId: $sale->id,
                        userId: $userId,
                        note: "Sale #{$sale->sale_number}",
                    );
                }
            }

            // 5. Handle credit sale
            if ($sale->is_credit_sale && $sale->customer_id) {
                $this->createCreditRecord($sale);
            }

            // 6. Update customer stats
            if ($sale->customer_id) {
                $this->updateCustomerStats($sale);
            }

            // 7. Send low stock notifications
            $this->checkAndNotifyLowStock($items, $tenantId, $shopId);

            Log::info("Sale processed", [
                'sale_id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'total' => $sale->total,
                'tenant_id' => $tenantId,
            ]);

            return $sale->load(['items.product', 'customer', 'user']);
        });
    }

    /**
     * Void/cancel a sale and restore stock.
     */
    public function voidSale(Sale $sale, int $userId, string $reason = ''): Sale
    {
        if ($sale->status === 'voided') {
            throw new \InvalidArgumentException('Sale is already voided.');
        }

        return DB::transaction(function () use ($sale, $userId, $reason) {
            $sale->update(['status' => 'voided', 'note' => $reason]);

            // Restore stock for each item
            foreach ($sale->items as $item) {
                $product = $item->product;
                if ($product->track_inventory) {
                    $this->inventoryService->addStock(
                        product: $product,
                        quantity: $item->quantity,
                        referenceType: Sale::class,
                        referenceId: $sale->id,
                        userId: $userId,
                        note: "Void: Sale #{$sale->sale_number}",
                    );
                }
            }

            // Reverse credit if credit sale
            if ($sale->is_credit_sale && $sale->customer_id) {
                CreditSale::where('sale_id', $sale->id)->update(['status' => 'written_off']);
                Customer::find($sale->customer_id)?->decrement('credit_balance', $sale->credit_amount);
            }

            Log::info("Sale voided", ['sale_id' => $sale->id]);

            return $sale->refresh();
        });
    }

    /**
     * Process a return/refund for sold items.
     */
    public function processReturn(Sale $sale, array $returnItems, int $userId): Sale
    {
        return DB::transaction(function () use ($sale, $returnItems, $userId) {
            $refundTotal = 0;

            foreach ($returnItems as $returnItem) {
                $saleItem = $sale->items->find($returnItem['sale_item_id']);
                if (!$saleItem) continue;

                $returnQty = min($returnItem['quantity'], $saleItem->quantity);
                $refundAmount = $returnQty * $saleItem->unit_price;
                $refundTotal += $refundAmount;

                // Restore stock
                $this->inventoryService->addStock(
                    product: $saleItem->product,
                    quantity: $returnQty,
                    referenceType: Sale::class,
                    referenceId: $sale->id,
                    userId: $userId,
                    note: "Return: Sale #{$sale->sale_number}",
                );
            }

            $sale->update(['status' => 'refunded']);

            return $sale->refresh();
        });
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function validateAndPrepareItems(array $items, int $shopId): array
    {
        $prepared = [];

        foreach ($items as $item) {
            $product = Product::where('shop_id', $shopId)
                              ->where('id', $item['product_id'])
                              ->firstOrFail();

            if (!$product->canSell($item['quantity'])) {
                throw ValidationException::withMessages([
                    'items' => "Insufficient stock for product: {$product->name}. Available: {$product->quantity}",
                ]);
            }

            $unitPrice = $item['unit_price'] ?? $product->selling_price;
            $discount  = $item['discount_amount'] ?? 0;
            $taxAmount = $item['tax_amount'] ?? 0;
            $subtotal  = ($unitPrice * $item['quantity']) - $discount + $taxAmount;

            $prepared[] = [
                'product_id'      => $product->id,
                'product_name'    => $product->name,
                'unit'            => $product->unit,
                'quantity'        => $item['quantity'],
                'unit_price'      => $unitPrice,
                'cost_price'      => $product->cost_price,
                'discount_amount' => $discount,
                'discount_percent'=> $item['discount_percent'] ?? 0,
                'tax_amount'      => $taxAmount,
                'subtotal'        => $subtotal,
                'product'         => $product,
            ];
        }

        return $prepared;
    }

    private function calculateTotals(array $items, array $data): array
    {
        $subtotal = collect($items)->sum('subtotal');
        $discountPercent = $data['discount_percent'] ?? 0;
        $discountAmount  = ($subtotal * $discountPercent / 100) + ($data['extra_discount'] ?? 0);
        $taxPercent      = $data['tax_percent'] ?? 0;
        $taxableAmount   = $subtotal - $discountAmount;
        $taxAmount       = $taxableAmount * $taxPercent / 100;
        $total           = $taxableAmount + $taxAmount;

        return [
            'subtotal'        => round($subtotal, 2),
            'discount_amount' => round($discountAmount, 2),
            'tax_amount'      => round($taxAmount, 2),
            'total'           => round($total, 2),
        ];
    }

    private function createCreditRecord(Sale $sale): CreditSale
    {
        $creditSale = CreditSale::create([
            'tenant_id'    => $sale->tenant_id,
            'shop_id'      => $sale->shop_id,
            'sale_id'      => $sale->id,
            'customer_id'  => $sale->customer_id,
            'total_amount' => $sale->credit_amount,
            'amount_paid'  => 0,
            'balance'      => $sale->credit_amount,
            'status'       => 'unpaid',
        ]);

        // Update customer credit balance
        Customer::find($sale->customer_id)?->increment('credit_balance', $sale->credit_amount);

        return $creditSale;
    }

    private function updateCustomerStats(Sale $sale): void
    {
        Customer::where('id', $sale->customer_id)->update([
            'total_purchases'   => DB::raw("total_purchases + {$sale->total}"),
            'total_transactions'=> DB::raw('total_transactions + 1'),
            'last_purchase_at'  => now(),
        ]);
    }

    private function checkAndNotifyLowStock(array $items, int $tenantId, int $shopId): void
    {
        foreach ($items as $item) {
            $product = $item['product']->refresh();
            if ($product->isLowStock()) {
                $this->notificationService->sendLowStockAlert($product, $tenantId, $shopId);
            }
        }
    }
}
