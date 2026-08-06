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
use Illuminate\Support\Facades\Cookie;
use Carbon\Carbon;

class AdminController extends Controller
{
    // ─── Login ────────────────────────────────────────────────────────────────

    public function showLogin()
    {
        $file = base_path('resources/views/admin/login.html');
        return response(file_get_contents($file))->header('Content-Type','text/html');
    }

    public function loginJson(Request $request)
    {
        $email    = $request->input('email');
        $password = $request->input('password');

        if (!$email || !$password) {
            return response()->json(['success' => false, 'message' => 'Email and password required.']);
        }

        $admin = SuperAdmin::where('email', $email)->where('is_active', true)->first();

        if (!$admin || !Hash::check($password, $admin->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid email or password.']);
        }

        $token = \Illuminate\Support\Str::random(64);
        $admin->update(['api_token' => hash('sha256', $token), 'last_login_at' => now()]);

        return response()->json(['success' => true, 'token' => $token, 'name' => $admin->name]);
    }

    public function logout()
    {
        $token = request()->cookie('admin_token');
        if ($token) {
            SuperAdmin::where('api_token', hash('sha256', $token))
                      ->update(['api_token' => null]);
        }
        return redirect('/admin/login')
            ->withCookie(Cookie::forget('admin_token'));
    }

    // ─── Dashboard ────────────────────────────────────────────────────────────

    public function dashboard(Request $request)
    {
        $file = base_path('resources/views/admin/dashboard.html');
        return response(file_get_contents($file))->header('Content-Type','text/html');
    }

    // ─── API Endpoints ────────────────────────────────────────────────────────

    public function apiStats()
    {
        return response()->json($this->getPlatformStats());
    }

    public function apiTenants(Request $request)
    {
        $query = Tenant::with(['subscriptionPlan'])
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

        $salesTotal  = Sale::where('tenant_id', $id)->where('status', 'completed')->sum('total');
        $salesCount  = Sale::where('tenant_id', $id)->where('status', 'completed')->count();
        $productsCount = Product::where('tenant_id', $id)->count();

        return response()->json([
            'tenant'         => $tenant,
            'sales_total'    => round((float)$salesTotal, 2),
            'sales_count'    => $salesCount,
            'products_count' => $productsCount,
        ]);
    }

    public function apiSuspendTenant($id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update(['status' => 'suspended']);
        User::where('tenant_id', $id)->update(['is_active' => false]);
        return response()->json(['success' => true, 'message' => "{$tenant->business_name} suspended."]);
    }

    public function apiActivateTenant($id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update(['status' => 'active']);
        User::where('tenant_id', $id)->update(['is_active' => true]);
        return response()->json(['success' => true, 'message' => "{$tenant->business_name} activated."]);
    }

    public function apiExtendTrial($id, Request $request)
    {
        $tenant = Tenant::findOrFail($id);
        $days = (int)($request->days ?? 14);
        $tenant->update(['trial_ends_at' => Carbon::now()->addDays($days), 'status' => 'trial']);
        return response()->json(['success' => true, 'message' => "Trial extended by {$days} days."]);
    }

    public function apiDeleteTenant($id)
    {
        $tenant = Tenant::findOrFail($id);
        $name = $tenant->business_name;
        User::where('tenant_id', $id)->update(['is_active' => false]);
        $tenant->delete();
        return response()->json(['success' => true, 'message' => "{$name} deleted."]);
    }

    public function apiPlans()
    {
        return response()->json(['data' => SubscriptionPlan::withCount('tenants')->get()]);
    }

    public function apiUpdatePlan(Request $request, $id)
    {
        $plan = SubscriptionPlan::findOrFail($id);
        $plan->update($request->only(['name', 'price', 'max_users', 'max_products', 'trial_days']));
        return response()->json(['success' => true, 'message' => 'Plan updated.', 'data' => $plan]);
    }

    public function apiRecentSignups()
    {
        return response()->json([
            'data' => Tenant::with('subscriptionPlan')->latest()->limit(10)->get()
        ]);
    }

    public function apiChartData()
    {
        $signups = Tenant::selectRaw("DATE(created_at) as date, COUNT(*) as count")
                         ->where('created_at', '>=', now()->subDays(30))
                         ->groupBy('date')->orderBy('date')->get();

        $revenue = Sale::selectRaw("DATE(created_at) as date, SUM(total) as total")
                       ->where('status', 'completed')
                       ->where('created_at', '>=', now()->subDays(30))
                       ->groupBy('date')->orderBy('date')->get();

        return response()->json(['signups' => $signups, 'revenue' => $revenue]);
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function getPlatformStats(): array
    {
        return [
            'total_tenants'       => Tenant::count(),
            'active_tenants'      => Tenant::where('status', 'active')->count(),
            'trial_tenants'       => Tenant::where('status', 'trial')->count(),
            'suspended_tenants'   => Tenant::where('status', 'suspended')->count(),
            'total_users'         => User::count(),
            'total_sales'         => Sale::where('status', 'completed')->count(),
            'total_revenue'       => round((float)Sale::where('status', 'completed')->sum('total'), 2),
            'total_products'      => Product::count(),
            'new_this_month'      => Tenant::whereMonth('created_at', now()->month)
                                           ->whereYear('created_at', now()->year)->count(),
            'trial_expiring_soon' => Tenant::where('status', 'trial')
                                           ->where('trial_ends_at', '<=', now()->addDays(3))
                                           ->where('trial_ends_at', '>=', now())->count(),
        ];
    }
}
