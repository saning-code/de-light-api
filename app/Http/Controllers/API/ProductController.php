<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    /**
     * List products with search, filter, pagination.
     * GET /api/v1/products
     */
    public function index(Request $request): JsonResponse
    {
        $user   = auth()->user();
        $shopId = $request->shop_id ?? $user->shop_id;

        $query = Product::where('tenant_id', $user->tenant_id)
                        ->where('shop_id', $shopId)
                        ->with('category:id,name,color,icon')
                        ->withCount(['saleItems as total_sold' => fn ($q) => $q->selectRaw('COALESCE(SUM(quantity), 0)')])
                        ->latest();

        if ($request->filled('search'))      { $query->search($request->search); }
        if ($request->filled('category_id')) { $query->where('category_id', $request->category_id); }
        if ($request->filled('is_active'))   { $query->where('is_active', (bool) $request->is_active); }
        if ($request->filled('low_stock'))   { $query->lowStock(); }
        if ($request->filled('out_of_stock')){ $query->outOfStock(); }

        $perPage  = min($request->per_page ?? 20, 100);
        $products = $query->paginate($perPage);

        return $this->paginatedResponse($products);
    }

    /**
     * Create a new product.
     * POST /api/v1/products
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user->canAccess('manage_products')) {
            return $this->errorResponse('Permission denied', 403);
        }

        $validator = Validator::make($request->all(), [
            'name'          => 'required|string|max:255',
            'category_id'   => 'nullable|integer|exists:categories,id',
            'barcode'       => 'nullable|string|max:100',
            'sku'           => 'nullable|string|max:100',
            'unit'          => 'required|string|max:50',
            'selling_price' => 'required|numeric|min:0',
            'cost_price'    => 'nullable|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'quantity'      => 'nullable|numeric|min:0',
            'reorder_level' => 'nullable|numeric|min:0',
            'track_inventory' => 'nullable|boolean',
            'allow_negative_stock' => 'nullable|boolean',
            'image'         => 'nullable|image|max:5120', // 5MB
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        $shopId = $request->shop_id ?? $user->shop_id;

        // Barcode uniqueness per shop
        if ($request->barcode) {
            $exists = Product::where('shop_id', $shopId)
                              ->where('barcode', $request->barcode)
                              ->exists();
            if ($exists) {
                return $this->errorResponse('A product with this barcode already exists', 422);
            }
        }

        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store("tenants/{$user->tenant_id}/products", 'public');
        }

        $product = Product::create([
            'tenant_id'        => $user->tenant_id,
            'shop_id'          => $shopId,
            'category_id'      => $request->category_id,
            'name'             => $request->name,
            'sku'              => $request->sku,
            'barcode'          => $request->barcode,
            'description'      => $request->description,
            'unit'             => $request->unit,
            'selling_price'    => $request->selling_price,
            'cost_price'       => $request->cost_price ?? 0,
            'wholesale_price'  => $request->wholesale_price,
            'quantity'         => $request->quantity ?? 0,
            'reorder_level'    => $request->reorder_level ?? 5,
            'max_stock_level'  => $request->max_stock_level,
            'track_inventory'  => $request->boolean('track_inventory', true),
            'allow_negative_stock' => $request->boolean('allow_negative_stock', false),
            'image'            => $imagePath,
            'tax_rate'         => $request->tax_rate ?? 0,
            'expiry_date'      => $request->expiry_date,
            'batch_number'     => $request->batch_number,
            'attributes'       => $request->attributes,
        ]);

        // Record opening stock movement if quantity > 0
        if ($product->quantity > 0) {
            try {
                $this->inventoryService->adjustStock(
                    product: $product,
                    newQuantity: $product->quantity,
                    userId: $user->id,
                    type: 'opening',
                    note: 'Opening stock',
                );
            } catch (\Exception $e) {
                // Stock movement failed — product still created successfully
                \Log::warning('Opening stock movement failed: ' . $e->getMessage());
            }
        }

        return $this->successResponse(
            ['product' => $product->load('category')],
            'Product created successfully',
            201
        );
    }

    /**
     * Get a single product.
     * GET /api/v1/products/{id}
     */
    public function show(int $id): JsonResponse
    {
        $user    = auth()->user();
        $product = Product::where('tenant_id', $user->tenant_id)
                          ->with(['category', 'stockMovements' => fn ($q) => $q->latest()->limit(20)])
                          ->findOrFail($id);

        if (!$user->canAccess('view_cost_price')) {
            $product->makeHidden(['cost_price', 'wholesale_price']);
        }

        return $this->successResponse(['product' => $product]);
    }

    /**
     * Update a product.
     * PUT /api/v1/products/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        if (!$user->canAccess('manage_products')) {
            return $this->errorResponse('Permission denied', 403);
        }

        $product = Product::where('tenant_id', $user->tenant_id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'          => 'sometimes|string|max:255',
            'selling_price' => 'sometimes|numeric|min:0',
            'cost_price'    => 'sometimes|numeric|min:0',
            'reorder_level' => 'sometimes|numeric|min:0',
            'image'         => 'nullable|image|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        if ($request->hasFile('image')) {
            if ($product->image) Storage::disk('public')->delete($product->image);
            $request->merge(['image' => $request->file('image')->store("tenants/{$user->tenant_id}/products", 'public')]);
        }

        $product->update($request->except(['tenant_id', 'shop_id', 'uuid']));

        return $this->successResponse(['product' => $product->fresh('category')], 'Product updated');
    }

    /**
     * Delete a product.
     * DELETE /api/v1/products/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $user = auth()->user();
        if (!$user->canAccess('manage_products')) {
            return $this->errorResponse('Permission denied', 403);
        }

        $product = Product::where('tenant_id', $user->tenant_id)->findOrFail($id);
        $product->delete();

        return $this->successResponse([], 'Product deleted');
    }

    /**
     * Lookup product by barcode.
     * GET /api/v1/products/barcode/{barcode}
     */
    public function lookupBarcode(string $barcode, Request $request): JsonResponse
    {
        $user   = auth()->user();
        $shopId = $request->shop_id ?? $user->shop_id;

        $product = Product::where('tenant_id', $user->tenant_id)
                          ->where('shop_id', $shopId)
                          ->where('barcode', $barcode)
                          ->where('is_active', true)
                          ->with('category:id,name,color')
                          ->first();

        if (!$product) {
            return $this->errorResponse('Product not found for this barcode', 404);
        }

        if (!$user->canAccess('view_cost_price')) {
            $product->makeHidden(['cost_price', 'wholesale_price']);
        }

        return $this->successResponse(['product' => $product]);
    }

    /**
     * Bulk import products via CSV/JSON.
     * POST /api/v1/products/bulk-import
     */
    public function bulkImport(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user->canAccess('manage_products')) {
            return $this->errorResponse('Permission denied', 403);
        }

        $items   = $request->json('products', []);
        $created = 0;
        $errors  = [];

        foreach ($items as $index => $item) {
            try {
                Product::create(array_merge($item, [
                    'tenant_id' => $user->tenant_id,
                    'shop_id'   => $request->shop_id ?? $user->shop_id,
                ]));
                $created++;
            } catch (\Exception $e) {
                $errors[] = "Row {$index}: " . $e->getMessage();
            }
        }

        return $this->successResponse([
            'created' => $created,
            'errors'  => $errors,
        ], "{$created} products imported");
    }

    /**
     * Get stock valuation summary.
     * GET /api/v1/products/stock-value
     */
    public function stockValue(Request $request): JsonResponse
    {
        $user   = auth()->user();
        $shopId = $request->shop_id ?? $user->shop_id;
        $value  = $this->inventoryService->getStockValue($shopId, $user->tenant_id);
        return $this->successResponse($value);
    }

    /**
     * Adjust stock manually.
     * POST /api/v1/products/{id}/adjust-stock
     */
    public function adjustStock(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        if (!$user->hasRole(['owner', 'manager'])) {
            return $this->errorResponse('Permission denied', 403);
        }

        $validator = Validator::make($request->all(), [
            'new_quantity' => 'required|numeric|min:0',
            'reason'       => 'nullable|string|max:500',
            'type'         => 'nullable|in:adjustment,damage,loss,return,opening',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        $product = Product::where('tenant_id', $user->tenant_id)->findOrFail($id);

        $movement = $this->inventoryService->adjustStock(
            product: $product,
            newQuantity: $request->new_quantity,
            userId: $user->id,
            type: $request->type ?? 'adjustment',
            note: $request->reason ?? 'Manual stock adjustment',
        );

        return $this->successResponse([
            'product'  => $product->fresh(),
            'movement' => $movement,
        ], 'Stock adjusted successfully');
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
            ],
        ]);
    }
}
