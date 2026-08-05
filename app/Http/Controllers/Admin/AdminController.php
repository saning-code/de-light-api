<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Sale;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminController extends Controller
{
    // ─── Login ────────────────────────────────────────────────────────────────

    public function showLogin()
    {
        if (session('super_admin_id')) return redirect('/admin/dashboard');
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $admin = SuperAdmin::where('email', $request->email)
                           ->where('is_active', true)
                           ->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return back()->withErrors(['email' => 'Invalid email or password.']);
        }

        session(['super_admin_id' => $admin->id, 'super_admin_name' => $admin->name]);
        $admin->update(['last_login_at' => now()]);

        return redirect('/admin/dashboard');
    }

    public function logout()
    {
        session()->forget(['super_admin_id', 'super_admin_name']);
        return redirect('/admin/login');
    }

    // ─── Dashboard ────────────────────────────────────────────────────────────

    public function dashboard()
    {
        $stats = $this->getPlatformStats();
        return view('admin.dashboard', compact('stats'));
    }

    // ─── API Endpoints (called by dashboard via AJAX) ─────────────────────────

    public function apiStats()
    {
        return response()->json($this->getPlatformStats());
    }

    public function apiTenants(Request $request)
    {
        $query = Tenant::with(['subscriptionPlan', 'shops'])
                       ->withCount(['users', 'shops']);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('business_name', 'like', '%'.$request->search.'%')
                  ->orWhere('owner_email', 'like', '%'.$request->search.'%')
                  ->orWhere('business_code', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $tenants = $query->latest()->paginate(20);

        return response()->json([
            'data' => $tenants->items(),
            'meta' => [
                'total'        => $tenants->total(),
                'current_page' => $tenants->currentPage(),
                'last_page'    => $tenants->lastPage(),
            ],
        ]);
    }

    public function apiTenant($id)
    {
        $tenant = Tenant::with(['subscriptionPlan', 'shops', 'users'])
                        ->withCount(['users', 'shops'])
                        ->findOrFail($id);

        // Sales stats for this tenant
        $salesTotal = Sale::where('tenant_id', $id)
                          ->where('status', 'completed')
                          ->sum('total');

        $salesCount = Sale::where('tenant_id', $id)
                          ->where('status', 'completed')
                          ->count();

        $productsCount = Product::where('tenant_id', $id)->count();

        return response()->json([
            'tenant'         => $tenant,
            'sales_total'    => round($salesTotal, 2),
            'sales_count'    => $salesCount,
            'products_count' => $productsCount,
        ]);
    }

    public function apiSuspendTenant($id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update(['status' => 'suspended']);

        // Deactivate all users of this tenant
        User::where('tenant_id', $id)->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => "{$tenant->business_name} has been suspended.",
        ]);
    }

    public function apiActivateTenant($id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update(['status' => 'active']);

        // Reactivate all users
        User::where('tenant_id', $id)->update(['is_active' => true]);

        return response()->json([
            'success' => true,
            'message' => "{$tenant->business_name} has been activated.",
        ]);
    }

    public function apiExtendTrial($id, Request $request)
    {
        $tenant = Tenant::findOrFail($id);
        $days = $request->days ?? 14;

        $tenant->update([
            'trial_ends_at' => Carbon::now()->addDays($days),
            'status'        => 'trial',
        ]);

        return response()->json([
            'success' => true,
            'message' => "Trial extended by {$days} days for {$tenant->business_name}.",
        ]);
    }

    public function apiDeleteTenant($id)
    {
        $tenant = Tenant::findOrFail($id);
        $name = $tenant->business_name;

        // Soft cascade — deactivate everything
        User::where('tenant_id', $id)->update(['is_active' => false]);
        $tenant->delete();

        return response()->json([
            'success' => true,
            'message' => "{$name} has been deleted.",
        ]);
    }

    public function apiPlans()
    {
        $plans = SubscriptionPlan::withCount('tenants')->get();
        return response()->json(['data' => $plans]);
    }

    public function apiUpdatePlan(Request $request, $id)
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $plan->update($request->only(['name', 'price', 'max_users', 'max_products', 'trial_days']));

        return response()->json(['success' => true, 'message' => 'Plan updated.', 'data' => $plan]);
    }

    public function apiRecentSignups()
    {
        $tenants = Tenant::with('subscriptionPlan')
                         ->latest()
                         ->limit(10)
                         ->get();

        return response()->json(['data' => $tenants]);
    }

    public function apiChartData()
    {
        // Signups per day — last 30 days
        $signups = Tenant::selectRaw("DATE(created_at) as date, COUNT(*) as count")
                         ->where('created_at', '>=', now()->subDays(30))
                         ->groupBy('date')
                         ->orderBy('date')
                         ->get();

        // Revenue per day (sum of all sales across all tenants)
        $revenue = Sale::selectRaw("DATE(created_at) as date, SUM(total) as total")
                       ->where('status', 'completed')
                       ->where('created_at', '>=', now()->subDays(30))
                       ->groupBy('date')
                       ->orderBy('date')
                       ->get();

        return response()->json([
            'signups' => $signups,
            'revenue' => $revenue,
        ]);
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    private function getPlatformStats(): array
    {
        $totalTenants    = Tenant::count();
        $activeTenants   = Tenant::where('status', 'active')->count();
        $trialTenants    = Tenant::where('status', 'trial')->count();
        $suspendedTenants= Tenant::where('status', 'suspended')->count();
        $totalUsers      = User::count();
        $totalSales      = Sale::where('status', 'completed')->count();
        $totalRevenue    = Sale::where('status', 'completed')->sum('total');
        $totalProducts   = Product::count();

        // New signups this month
        $newThisMonth = Tenant::whereMonth('created_at', now()->month)
                              ->whereYear('created_at', now()->year)
                              ->count();

        // Tenants expiring trial in 3 days
        $trialExpiringSoon = Tenant::where('status', 'trial')
                                   ->where('trial_ends_at', '<=', now()->addDays(3))
                                   ->where('trial_ends_at', '>=', now())
                                   ->count();

        return [
            'total_tenants'       => $totalTenants,
            'active_tenants'      => $activeTenants,
            'trial_tenants'       => $trialTenants,
            'suspended_tenants'   => $suspendedTenants,
            'total_users'         => $totalUsers,
            'total_sales'         => $totalSales,
            'total_revenue'       => round((float)$totalRevenue, 2),
            'total_products'      => $totalProducts,
            'new_this_month'      => $newThisMonth,
            'trial_expiring_soon' => $trialExpiringSoon,
        ];
    }
}
