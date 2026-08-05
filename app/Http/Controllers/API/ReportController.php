<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    /**
     * Dashboard Summary for mobile home screen.
     * GET /api/v1/reports/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = auth()->user();
        $shopId = $request->shop_id ?? $user->shop_id;

        $summary = $this->reportService->getDashboardSummary($user->tenant_id, $shopId);

        return response()->json([
            'success' => true,
            'message' => 'Dashboard summary loaded',
            'data'    => $summary,
        ]);
    }

    /**
     * Comprehensive Report for custom date ranges (Daily, Weekly, Monthly, Yearly).
     * GET /api/v1/reports/period
     */
    public function period(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user->canAccess('view_reports')) {
            return response()->json(['success' => false, 'message' => 'Permission denied'], 403);
        }

        $shopId = $request->shop_id ?? $user->shop_id;
        $periodType = $request->period ?? 'monthly'; // daily, weekly, monthly, yearly, custom

        $from = match($periodType) {
            'daily'   => today()->startOfDay(),
            'weekly'  => now()->startOfWeek(),
            'monthly' => now()->startOfMonth(),
            'yearly'  => now()->startOfYear(),
            'custom'  => $request->from ? Carbon::parse($request->from)->startOfDay() : now()->startOfMonth(),
            default   => now()->startOfMonth(),
        };

        $to = match($periodType) {
            'daily'   => today()->endOfDay(),
            'weekly'  => now()->endOfWeek(),
            'monthly' => now()->endOfMonth(),
            'yearly'  => now()->endOfYear(),
            'custom'  => $request->to ? Carbon::parse($request->to)->endOfDay() : now()->endOfDay(),
            default   => now()->endOfDay(),
        };

        $report = $this->reportService->generateReport(
            tenantId: $user->tenant_id,
            shopId: $shopId,
            from: $from,
            to: $to,
            period: $periodType
        );

        return response()->json([
            'success' => true,
            'message' => 'Report generated',
            'data'    => $report,
        ]);
    }
}
