<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Customer;
use App\Models\Product;
use App\Models\CreditPayment;
use App\Models\SyncLog;
use App\Services\SaleService;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class SyncController extends Controller
{
    public function __construct(
        private readonly SaleService $saleService,
        private readonly InventoryService $inventoryService
    ) {}

    /**
     * Push offline records from Flutter app to Laravel backend.
     * POST /api/v1/sync/push
     */
    public function push(Request $request): JsonResponse
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;
        $shopId = $request->shop_id ?? $user->shop_id;

        $validator = Validator::make($request->all(), [
            'device_id' => 'required|string',
            'sales'     => 'nullable|array',
            'expenses'  => 'nullable|array',
            'purchases' => 'nullable|array',
            'customers' => 'nullable|array',
            'payments'  => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        $results = [
            'sales'     => ['synced' => 0, 'duplicates' => 0, 'failed' => 0, 'errors' => []],
            'expenses'  => ['synced' => 0, 'duplicates' => 0, 'failed' => 0, 'errors' => []],
            'customers' => ['synced' => 0, 'duplicates' => 0, 'failed' => 0, 'errors' => []],
            'payments'  => ['synced' => 0, 'duplicates' => 0, 'failed' => 0, 'errors' => []],
        ];

        // 1. Process Offline Customers First
        if ($request->has('customers')) {
            foreach ($request->customers as $cData) {
                try {
                    $existing = Customer::where('tenant_id', $tenantId)
                        ->where('shop_id', $shopId)
                        ->where(function($q) use ($cData) {
                            if (!empty($cData['uuid'])) $q->orWhere('uuid', $cData['uuid']);
                            if (!empty($cData['phone'])) $q->orWhere('phone', $cData['phone']);
                        })->first();

                    if ($existing) {
                        $results['customers']['duplicates']++;
                    } else {
                        Customer::create(array_merge($cData, [
                            'tenant_id' => $tenantId,
                            'shop_id'   => $shopId,
                        ]));
                        $results['customers']['synced']++;
                    }
                } catch (\Exception $e) {
                    $results['customers']['failed']++;
                    $results['customers']['errors'][] = $e->getMessage();
                }
            }
        }

        // 2. Process Offline Sales
        if ($request->has('sales')) {
            foreach ($request->sales as $sData) {
                try {
                    $localId = $sData['local_id'] ?? null;
                    if ($localId) {
                        $existing = Sale::where('tenant_id', $tenantId)
                            ->where('local_id', $localId)
                            ->first();

                        if ($existing) {
                            $results['sales']['duplicates']++;
                            continue;
                        }
                    }

                    $this->saleService->processSale(
                        data: $sData,
                        tenantId: $tenantId,
                        shopId: $shopId,
                        userId: $user->id
                    );
                    $results['sales']['synced']++;

                } catch (\Exception $e) {
                    $results['sales']['failed']++;
                    $results['sales']['errors'][] = "Sale {$sData['local_id']}: " . $e->getMessage();
                }
            }
        }

        // 3. Process Offline Expenses
        if ($request->has('expenses')) {
            foreach ($request->expenses as $eData) {
                try {
                    $localId = $eData['local_id'] ?? null;
                    if ($localId) {
                        $existing = Expense::where('tenant_id', $tenantId)
                            ->where('local_id', $localId)
                            ->first();

                        if ($existing) {
                            $results['expenses']['duplicates']++;
                            continue;
                        }
                    }

                    Expense::create(array_merge($eData, [
                        'tenant_id'  => $tenantId,
                        'shop_id'    => $shopId,
                        'user_id'    => $user->id,
                        'synced_at'  => now(),
                    ]));
                    $results['expenses']['synced']++;

                } catch (\Exception $e) {
                    $results['expenses']['failed']++;
                    $results['expenses']['errors'][] = $e->getMessage();
                }
            }
        }

        // Log device sync status
        SyncLog::updateOrCreate(
            ['tenant_id' => $tenantId, 'device_id' => $request->device_id, 'table_name' => 'all'],
            [
                'last_pushed_at' => now(),
                'status'         => 'success',
            ]
        );

        return $this->successResponse([
            'summary'     => $results,
            'synced_at'   => now()->toIso8601String(),
            'server_time' => now()->timestamp,
        ], 'Background sync push completed');
    }

    /**
     * Pull updated server data to Flutter app.
     * GET /api/v1/sync/pull
     */
    public function pull(Request $request): JsonResponse
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;
        $shopId = $request->shop_id ?? $user->shop_id;

        $lastSyncedAt = $request->last_synced_at 
            ? Carbon::parse($request->last_synced_at) 
            : Carbon::createFromTimestamp(0);

        // Fetch products updated after last_synced_at
        $products = Product::where('tenant_id', $tenantId)
            ->where('shop_id', $shopId)
            ->where('updated_at', '>', $lastSyncedAt)
            ->with('category:id,name,color,icon')
            ->withTrashed()
            ->get();

        // Fetch categories
        $categories = DB::table('categories')
            ->where('tenant_id', $tenantId)
            ->where('shop_id', $shopId)
            ->where('updated_at', '>', $lastSyncedAt)
            ->get();

        // Fetch customers
        $customers = Customer::where('tenant_id', $tenantId)
            ->where('shop_id', $shopId)
            ->where('updated_at', '>', $lastSyncedAt)
            ->get();

        // Fetch settings
        $settings = DB::table('settings')
            ->where('tenant_id', $tenantId)
            ->where(fn($q) => $q->whereNull('shop_id')->orWhere('shop_id', $shopId))
            ->get();

        return $this->successResponse([
            'products'    => $products,
            'categories'  => $categories,
            'customers'   => $customers,
            'settings'    => $settings,
            'synced_at'   => now()->toIso8601String(),
            'server_time' => now()->timestamp,
        ], 'Sync pull successful');
    }

    /**
     * Get sync health & status for a device.
     * GET /api/v1/sync/status
     */
    public function status(Request $request): JsonResponse
    {
        $user = auth()->user();
        $logs = SyncLog::where('tenant_id', $user->tenant_id)
            ->where('device_id', $request->device_id)
            ->get();

        return $this->successResponse([
            'logs' => $logs,
            'is_online' => true,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    private function successResponse(array $data, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    private function errorResponse(string $message, int $status = 400): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'data' => null], $status);
    }
}
