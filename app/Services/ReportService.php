<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Product;
use App\Models\Customer;
use App\Models\CreditSale;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportService
{
    /**
     * Generate comprehensive report for a date range.
     */
    public function generateReport(
        int $tenantId,
        int $shopId,
        Carbon $from,
        Carbon $to,
        string $period = 'custom'
    ): array {
        $salesData    = $this->getSalesData($tenantId, $shopId, $from, $to);
        $expensesData = $this->getExpensesData($tenantId, $shopId, $from, $to);
        $profitData   = $this->getProfitData($tenantId, $shopId, $from, $to);
        $topProducts  = $this->getTopSellingProducts($tenantId, $shopId, $from, $to, 10);
        $lowProducts  = $this->getLeastSellingProducts($tenantId, $shopId, $from, $to, 10);
        $paymentBreakdown = $this->getPaymentMethodBreakdown($tenantId, $shopId, $from, $to);
        $creditSummary = $this->getCreditSummary($tenantId, $shopId);
        $dailyChart   = $this->getDailyChart($tenantId, $shopId, $from, $to);

        return [
            'period' => [
                'type'  => $period,
                'from'  => $from->toDateString(),
                'to'    => $to->toDateString(),
                'days'  => $from->diffInDays($to) + 1,
            ],
            'sales'    => $salesData,
            'expenses' => $expensesData,
            'profit'   => $profitData,
            'top_products'  => $topProducts,
            'least_products'=> $lowProducts,
            'payment_methods' => $paymentBreakdown,
            'credit_summary'  => $creditSummary,
            'daily_chart'     => $dailyChart,
            'generated_at'    => now()->toISOString(),
        ];
    }

    /**
     * Today's dashboard summary.
     */
    public function getDashboardSummary(int $tenantId, int $shopId): array
    {
        $today = today();
        $yesterday = today()->subDay();

        // Today's sales
        $todaySales = Sale::where('tenant_id', $tenantId)
                          ->where('shop_id', $shopId)
                          ->whereDate('created_at', $today)
                          ->where('status', 'completed');

        $todaySalesTotal     = $todaySales->sum('total');
        $todaySalesCount     = $todaySales->count();
        $todayAmountPaid     = $todaySales->sum('amount_paid');
        $todayCreditAmount   = $todaySales->sum('credit_amount');

        // Today's profit
        $todayProfit = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.shop_id', $shopId)
            ->whereDate('sales.created_at', $today)
            ->where('sales.status', 'completed')
            ->sum('sale_items.profit');

        // Today's expenses
        $todayExpenses = Expense::where('tenant_id', $tenantId)
                                ->where('shop_id', $shopId)
                                ->whereDate('expense_date', $today)
                                ->sum('amount');

        // Yesterday comparison
        $yesterdaySalesTotal = Sale::where('tenant_id', $tenantId)
                                   ->where('shop_id', $shopId)
                                   ->whereDate('created_at', $yesterday)
                                   ->where('status', 'completed')
                                   ->sum('total');

        // Stock value
        $stockValue = Product::where('tenant_id', $tenantId)
                              ->where('shop_id', $shopId)
                              ->where('is_active', true)
                              ->where('track_inventory', true)
                              ->selectRaw('SUM(quantity * cost_price) as cost_value, SUM(quantity * selling_price) as retail_value')
                              ->first();

        // Low stock count
        $lowStockCount = Product::where('tenant_id', $tenantId)
                                ->where('shop_id', $shopId)
                                ->where('is_active', true)
                                ->where('track_inventory', true)
                                ->whereColumn('quantity', '<=', 'reorder_level')
                                ->count();

        // Out of stock count
        $outOfStockCount = Product::where('tenant_id', $tenantId)
                                  ->where('shop_id', $shopId)
                                  ->where('is_active', true)
                                  ->where('track_inventory', true)
                                  ->where('quantity', '<=', 0)
                                  ->count();

        // Total customers owing
        $customersOwing = Customer::where('tenant_id', $tenantId)
                                  ->where('shop_id', $shopId)
                                  ->where('credit_balance', '>', 0)
                                  ->selectRaw('COUNT(*) as count, SUM(credit_balance) as total_owed')
                                  ->first();

        // This month totals
        $thisMonth = Sale::where('tenant_id', $tenantId)
                         ->where('shop_id', $shopId)
                         ->whereMonth('created_at', now()->month)
                         ->whereYear('created_at', now()->year)
                         ->where('status', 'completed');

        // Recent sales (last 10)
        $recentSales = Sale::where('tenant_id', $tenantId)
                           ->where('shop_id', $shopId)
                           ->where('status', 'completed')
                           ->with(['customer:id,name', 'user:id,name', 'items'])
                           ->latest()
                           ->limit(10)
                           ->get();

        // Top products today
        $topProductsToday = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.shop_id', $shopId)
            ->whereDate('sales.created_at', $today)
            ->where('sales.status', 'completed')
            ->select(
                'products.id',
                'products.name',
                'products.image',
                DB::raw('SUM(sale_items.quantity) as total_qty'),
                DB::raw('SUM(sale_items.subtotal) as total_revenue'),
                DB::raw('SUM(sale_items.profit) as total_profit')
            )
            ->groupBy('products.id', 'products.name', 'products.image')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();

        // Hourly chart (today)
        $hourlyChart = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->where('shop_id', $shopId)
            ->whereDate('created_at', $today)
            ->where('status', 'completed')
            ->select(
                DB::raw('STRFTIME("%H", created_at) as hour'),
                DB::raw('SUM(total) as revenue'),
                DB::raw('COUNT(*) as transactions')
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $salesGrowth = $yesterdaySalesTotal > 0
            ? (($todaySalesTotal - $yesterdaySalesTotal) / $yesterdaySalesTotal) * 100
            : ($todaySalesTotal > 0 ? 100 : 0);

        return [
            'today' => [
                'sales_total'     => round($todaySalesTotal, 2),
                'sales_count'     => $todaySalesCount,
                'amount_paid'     => round($todayAmountPaid, 2),
                'credit_amount'   => round($todayCreditAmount, 2),
                'profit'          => round($todayProfit, 2),
                'expenses'        => round($todayExpenses, 2),
                'net_profit'      => round($todayProfit - $todayExpenses, 2),
                'sales_growth'    => round($salesGrowth, 1), // % vs yesterday
            ],
            'this_month' => [
                'sales_total' => round($thisMonth->sum('total'), 2),
                'sales_count' => $thisMonth->count(),
            ],
            'inventory' => [
                'stock_cost_value'   => round($stockValue->cost_value ?? 0, 2),
                'stock_retail_value' => round($stockValue->retail_value ?? 0, 2),
                'low_stock_count'    => $lowStockCount,
                'out_of_stock_count' => $outOfStockCount,
            ],
            'credit' => [
                'customers_owing' => $customersOwing->count ?? 0,
                'total_owed'      => round($customersOwing->total_owed ?? 0, 2),
            ],
            'recent_sales'      => $recentSales,
            'top_products_today'=> $topProductsToday,
            'hourly_chart'      => $hourlyChart,
        ];
    }

    // ─── Private report builders ──────────────────────────────────────────────

    private function getSalesData(int $tenantId, int $shopId, Carbon $from, Carbon $to): array
    {
        $query = Sale::where('tenant_id', $tenantId)
                     ->where('shop_id', $shopId)
                     ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
                     ->where('status', 'completed');

        return [
            'total'           => round($query->sum('total'), 2),
            'count'           => $query->count(),
            'average'         => round($query->avg('total') ?? 0, 2),
            'discount_total'  => round($query->sum('discount_amount'), 2),
            'tax_total'       => round($query->sum('tax_amount'), 2),
            'credit_total'    => round($query->sum('credit_amount'), 2),
            'cash_total'      => round($query->where('payment_method', 'cash')->sum('total'), 2),
        ];
    }

    private function getExpensesData(int $tenantId, int $shopId, Carbon $from, Carbon $to): array
    {
        $query = Expense::where('tenant_id', $tenantId)
                        ->where('shop_id', $shopId)
                        ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()]);

        $byCategory = $query->select('category', DB::raw('SUM(amount) as total'))
                            ->groupBy('category')
                            ->pluck('total', 'category');

        return [
            'total'       => round($query->sum('amount'), 2),
            'count'       => $query->count(),
            'by_category' => $byCategory,
        ];
    }

    private function getProfitData(int $tenantId, int $shopId, Carbon $from, Carbon $to): array
    {
        $grossProfit = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.shop_id', $shopId)
            ->whereBetween('sales.created_at', [$from->startOfDay(), $to->endOfDay()])
            ->where('sales.status', 'completed')
            ->sum('sale_items.profit');

        $expenses = Expense::where('tenant_id', $tenantId)
                           ->where('shop_id', $shopId)
                           ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
                           ->sum('amount');

        $netProfit  = $grossProfit - $expenses;
        $salesTotal = Sale::where('tenant_id', $tenantId)->where('shop_id', $shopId)
                          ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
                          ->where('status', 'completed')->sum('total');

        $profitMargin = $salesTotal > 0 ? ($grossProfit / $salesTotal) * 100 : 0;

        return [
            'gross_profit'  => round($grossProfit, 2),
            'expenses'      => round($expenses, 2),
            'net_profit'    => round($netProfit, 2),
            'profit_margin' => round($profitMargin, 1),
        ];
    }

    private function getTopSellingProducts(int $tenantId, int $shopId, Carbon $from, Carbon $to, int $limit): \Illuminate\Support\Collection
    {
        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.shop_id', $shopId)
            ->whereBetween('sales.created_at', [$from->startOfDay(), $to->endOfDay()])
            ->where('sales.status', 'completed')
            ->select(
                'products.id',
                'products.name',
                'products.unit',
                'products.image',
                DB::raw('SUM(sale_items.quantity) as total_qty'),
                DB::raw('SUM(sale_items.subtotal) as total_revenue'),
                DB::raw('SUM(sale_items.profit) as total_profit')
            )
            ->groupBy('products.id', 'products.name', 'products.unit', 'products.image')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get();
    }

    private function getLeastSellingProducts(int $tenantId, int $shopId, Carbon $from, Carbon $to, int $limit): \Illuminate\Support\Collection
    {
        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.shop_id', $shopId)
            ->whereBetween('sales.created_at', [$from->startOfDay(), $to->endOfDay()])
            ->where('sales.status', 'completed')
            ->select(
                'products.id',
                'products.name',
                'products.unit',
                DB::raw('SUM(sale_items.quantity) as total_qty'),
                DB::raw('SUM(sale_items.subtotal) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.unit')
            ->orderBy('total_revenue')
            ->limit($limit)
            ->get();
    }

    private function getPaymentMethodBreakdown(int $tenantId, int $shopId, Carbon $from, Carbon $to): \Illuminate\Support\Collection
    {
        return Sale::where('tenant_id', $tenantId)
                   ->where('shop_id', $shopId)
                   ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
                   ->where('status', 'completed')
                   ->select('payment_method', DB::raw('COUNT(*) as count, SUM(total) as total'))
                   ->groupBy('payment_method')
                   ->get();
    }

    private function getCreditSummary(int $tenantId, int $shopId): array
    {
        $credits = CreditSale::where('tenant_id', $tenantId)
                             ->where('shop_id', $shopId);
        return [
            'total_owed'     => round($credits->whereIn('status', ['unpaid', 'partial'])->sum('balance'), 2),
            'overdue'        => round($credits->where('status', 'overdue')->sum('balance'), 2),
            'unpaid_count'   => $credits->where('status', 'unpaid')->count(),
            'partial_count'  => $credits->where('status', 'partial')->count(),
        ];
    }

    private function getDailyChart(int $tenantId, int $shopId, Carbon $from, Carbon $to): \Illuminate\Support\Collection
    {
        return DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->where('shop_id', $shopId)
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->where('status', 'completed')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total) as revenue'),
                DB::raw('COUNT(*) as transactions')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }
}
