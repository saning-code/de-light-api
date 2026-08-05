<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryService
{
    /**
     * Deduct stock when a sale is made.
     */
    public function deductStock(
        Product $product,
        float $quantity,
        string $referenceType,
        int $referenceId,
        int $userId,
        string $note = ''
    ): StockMovement {
        $balanceBefore = $product->quantity;
        $balanceAfter  = $balanceBefore - $quantity;

        $product->decrement('quantity', $quantity);

        return $this->recordMovement(
            product: $product,
            type: 'sale',
            quantity: -$quantity,
            balanceBefore: $balanceBefore,
            balanceAfter: $balanceAfter,
            unitCost: $product->cost_price,
            referenceType: $referenceType,
            referenceId: $referenceId,
            userId: $userId,
            note: $note,
        );
    }

    /**
     * Add stock when a purchase is received.
     */
    public function addStock(
        Product $product,
        float $quantity,
        string $referenceType,
        int $referenceId,
        int $userId,
        string $note = '',
        float $unitCost = 0.0
    ): StockMovement {
        $balanceBefore = $product->quantity;
        $balanceAfter  = $balanceBefore + $quantity;

        $product->increment('quantity', $quantity);

        if ($unitCost > 0) {
            $product->update(['cost_price' => $unitCost]);
        }

        return $this->recordMovement(
            product: $product,
            type: 'purchase',
            quantity: $quantity,
            balanceBefore: $balanceBefore,
            balanceAfter: $balanceAfter,
            unitCost: $unitCost ?: $product->cost_price,
            referenceType: $referenceType,
            referenceId: $referenceId,
            userId: $userId,
            note: $note,
        );
    }

    /**
     * Manual stock adjustment (count, damage, loss, opening balance).
     */
    public function adjustStock(
        Product $product,
        float $newQuantity,
        int $userId,
        string $type = 'adjustment',
        string $note = ''
    ): StockMovement {
        $balanceBefore = $product->quantity;
        $difference    = $newQuantity - $balanceBefore;

        $product->update(['quantity' => $newQuantity]);

        return $this->recordMovement(
            product: $product,
            type: $type,
            quantity: $difference,
            balanceBefore: $balanceBefore,
            balanceAfter: $newQuantity,
            unitCost: $product->cost_price,
            referenceType: null,
            referenceId: null,
            userId: $userId,
            note: $note ?: "Manual stock adjustment",
        );
    }

    /**
     * Transfer stock between shops.
     */
    public function transferStock(
        Product $fromProduct,
        Product $toProduct,
        float $quantity,
        int $userId,
        string $note = ''
    ): void {
        DB::transaction(function () use ($fromProduct, $toProduct, $quantity, $userId, $note) {
            $this->deductStock($fromProduct, $quantity, Product::class, $toProduct->id, $userId, "Transfer out: {$note}");
            $this->addStock($toProduct, $quantity, Product::class, $fromProduct->id, $userId, "Transfer in: {$note}");
        });
    }

    /**
     * Get stock valuation for a shop.
     */
    public function getStockValue(int $shopId, int $tenantId): array
    {
        $products = Product::where('shop_id', $shopId)
                           ->where('tenant_id', $tenantId)
                           ->where('is_active', true)
                           ->where('track_inventory', true)
                           ->select(['id', 'name', 'quantity', 'cost_price', 'selling_price'])
                           ->get();

        $costValue    = $products->sum(fn ($p) => $p->quantity * $p->cost_price);
        $retailValue  = $products->sum(fn ($p) => $p->quantity * $p->selling_price);
        $potentialProfit = $retailValue - $costValue;

        return [
            'cost_value'       => round($costValue, 2),
            'retail_value'     => round($retailValue, 2),
            'potential_profit' => round($potentialProfit, 2),
            'total_products'   => $products->count(),
            'total_units'      => $products->sum('quantity'),
        ];
    }

    /**
     * Get low stock products for a shop.
     */
    public function getLowStockProducts(int $shopId, int $tenantId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return Product::where('shop_id', $shopId)
                      ->where('tenant_id', $tenantId)
                      ->where('is_active', true)
                      ->where('track_inventory', true)
                      ->whereColumn('quantity', '<=', 'reorder_level')
                      ->orderBy('quantity', 'asc')
                      ->limit($limit)
                      ->get();
    }

    /**
     * Get movement history for a product.
     */
    public function getProductMovements(int $productId, int $tenantId, array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = StockMovement::where('product_id', $productId)
                              ->where('tenant_id', $tenantId)
                              ->with(['user:id,name', 'product:id,name,unit'])
                              ->latest();

        if (!empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    // ─── Private helper ───────────────────────────────────────────────────────

    private function recordMovement(
        Product $product,
        string $type,
        float $quantity,
        float $balanceBefore,
        float $balanceAfter,
        float $unitCost,
        ?string $referenceType,
        ?int $referenceId,
        int $userId,
        string $note
    ): StockMovement {
        return StockMovement::create([
            'uuid'           => \Illuminate\Support\Str::uuid(),
            'tenant_id'      => $product->tenant_id,
            'shop_id'        => $product->shop_id,
            'product_id'     => $product->id,
            'user_id'        => $userId,
            'type'           => $type,
            'quantity'       => $quantity,
            'balance_before' => $balanceBefore,
            'balance_after'  => $balanceAfter,
            'unit_cost'      => $unitCost,
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'note'           => $note,
        ]);
    }
}
