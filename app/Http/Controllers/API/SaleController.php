<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;

class SaleController extends Controller
{
    public function __construct(private readonly SaleService $saleService) {}

    /**
     * List sales with filters & pagination.
     * GET /api/v1/sales
     */
    public function index(Request $request): JsonResponse
    {
        $user    = auth()->user();
        $shopId  = $request->shop_id ?? $user->shop_id;

        $query = Sale::where('tenant_id', $user->tenant_id)
                     ->where('shop_id', $shopId)
                     ->with(['customer:id,name,phone', 'user:id,name', 'items.product:id,name,unit,image'])
                     ->latest();

        // Filters
        if ($request->status)      { $query->where('status', $request->status); }
        if ($request->payment_method) { $query->where('payment_method', $request->payment_method); }
        if ($request->customer_id) { $query->where('customer_id', $request->customer_id); }
        if ($request->user_id)     { $query->where('user_id', $request->user_id); }
        if ($request->date_from)   { $query->whereDate('created_at', '>=', $request->date_from); }
        if ($request->date_to)     { $query->whereDate('created_at', '<=', $request->date_to); }
        if ($request->search) {
            $query->where('sale_number', 'like', '%' . $request->search . '%');
        }

        $perPage = min($request->per_page ?? 20, 100);
        $sales   = $query->paginate($perPage);

        return $this->paginatedResponse($sales);
    }

    /**
     * Create a new sale (POS checkout).
     * POST /api/v1/sales
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items'                 => 'required|array|min:1',
            'items.*.product_id'    => 'required|integer|exists:products,id',
            'items.*.quantity'      => 'required|numeric|min:0.001',
            'items.*.unit_price'    => 'nullable|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'amount_paid'           => 'required|numeric|min:0',
            'payment_method'        => 'required|in:cash,momo,card,bank,credit,split',
            'customer_id'           => 'nullable|integer|exists:customers,id',
            'discount_percent'      => 'nullable|numeric|min:0|max:100',
            'tax_percent'           => 'nullable|numeric|min:0|max:100',
            'note'                  => 'nullable|string|max:500',
            'local_id'              => 'nullable|string|max:100',
            'device_id'             => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        // Check for duplicate from offline sync (local_id deduplication)
        if ($request->local_id) {
            $existing = Sale::where('tenant_id', auth()->user()->tenant_id)
                            ->where('local_id', $request->local_id)
                            ->first();
            if ($existing) {
                return $this->successResponse(
                    ['sale' => $existing->load(['items.product', 'customer'])],
                    'Sale already exists (deduplicated)'
                );
            }
        }

        // Check discount permission
        if ($request->discount_percent > 0) {
            $user = auth()->user();
            if (!$user->canAccess('give_discount')) {
                return $this->errorResponse('You do not have permission to give discounts', 403);
            }
            if ($request->discount_percent > $user->max_discount_percent && !$user->isOwner()) {
                return $this->errorResponse("Maximum discount allowed: {$user->max_discount_percent}%", 403);
            }
        }

        try {
            $user = auth()->user();
            $sale = $this->saleService->processSale(
                data: $request->all(),
                tenantId: $user->tenant_id,
                shopId: $request->shop_id ?? $user->shop_id,
                userId: $user->id,
            );

            return $this->successResponse(['sale' => $sale], 'Sale completed successfully', 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Sale failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get a single sale with full details.
     * GET /api/v1/sales/{id}
     */
    public function show(int $id): JsonResponse
    {
        $user = auth()->user();
        $sale = Sale::where('tenant_id', $user->tenant_id)
                    ->where('id', $id)
                    ->with(['items.product', 'customer', 'user', 'creditSale'])
                    ->firstOrFail();

        return $this->successResponse(['sale' => $sale]);
    }

    /**
     * Void a sale.
     * POST /api/v1/sales/{id}/void
     */
    public function void(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();

        if (!$user->canAccess('delete_sale')) {
            return $this->errorResponse('You do not have permission to void sales', 403);
        }

        $sale = Sale::where('tenant_id', $user->tenant_id)->findOrFail($id);

        try {
            $sale = $this->saleService->voidSale($sale, $user->id, $request->reason ?? '');
            return $this->successResponse(['sale' => $sale], 'Sale voided successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Generate PDF receipt for a sale.
     * GET /api/v1/sales/{id}/receipt/pdf
     */
    public function receiptPdf(int $id): mixed
    {
        $user = auth()->user();
        $sale = Sale::where('tenant_id', $user->tenant_id)
                    ->with(['items.product', 'customer', 'user', 'shop'])
                    ->findOrFail($id);

        $pdf = Pdf::loadView('receipts.sale', [
            'sale' => $sale,
            'shop' => $sale->shop,
            'tenant' => $user->tenant,
        ])->setPaper([0, 0, 164.41, 600], 'portrait'); // 58mm width

        return $pdf->download("receipt-{$sale->sale_number}.pdf");
    }

    /**
     * Get receipt data for Bluetooth printing.
     * GET /api/v1/sales/{id}/receipt
     */
    public function receipt(int $id): JsonResponse
    {
        $user = auth()->user();
        $sale = Sale::where('tenant_id', $user->tenant_id)
                    ->with(['items.product', 'customer', 'user', 'shop.tenant'])
                    ->findOrFail($id);

        $shop = $sale->shop;
        $settings = $shop->receipt_settings ?? [];

        return $this->successResponse([
            'sale'         => $sale,
            'receipt' => [
                'header'   => $settings['header'] ?? $shop->name,
                'address'  => $settings['address'] ?? $shop->address,
                'phone'    => $settings['phone'] ?? $shop->phone,
                'footer'   => $settings['footer'] ?? 'Thank you for your patronage!',
                'show_logo'=> $settings['show_logo'] ?? true,
                'show_tax' => $settings['show_tax'] ?? false,
                'currency_symbol' => $shop->currency_symbol,
            ],
        ]);
    }

    /**
     * Get today's summary stats.
     * GET /api/v1/sales/today-summary
     */
    public function todaySummary(Request $request): JsonResponse
    {
        $user   = auth()->user();
        $shopId = $request->shop_id ?? $user->shop_id;

        $sales = Sale::where('tenant_id', $user->tenant_id)
                     ->where('shop_id', $shopId)
                     ->whereDate('created_at', today())
                     ->where('status', 'completed');

        return $this->successResponse([
            'total'    => round($sales->sum('total'), 2),
            'count'    => $sales->count(),
            'paid'     => round($sales->sum('amount_paid'), 2),
            'credit'   => round($sales->sum('credit_amount'), 2),
            'discount' => round($sales->sum('discount_amount'), 2),
        ]);
    }

    // ─── Response helpers ─────────────────────────────────────────────────────

    private function successResponse(array $data, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    private function errorResponse(string $message, int $status = 400): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'data' => null], $status);
    }

    private function paginatedResponse(\Illuminate\Pagination\LengthAwarePaginator $paginator): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $paginator->items(),
            'meta'    => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
        ]);
    }
}
