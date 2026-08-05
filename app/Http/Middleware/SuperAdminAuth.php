<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\SuperAdmin;
use Illuminate\Support\Facades\Hash;

class SuperAdminAuth
{
    public function handle(Request $request, Closure $next)
    {
        // Session-based auth for the HTML dashboard
        if (session('super_admin_id')) {
            $admin = SuperAdmin::find(session('super_admin_id'));
            if ($admin && $admin->is_active) {
                $request->merge(['super_admin' => $admin]);
                return $next($request);
            }
        }

        // Token-based auth for API calls from the dashboard (AJAX)
        $token = $request->bearerToken() ?? $request->header('X-Admin-Token');
        if ($token) {
            $admin = SuperAdmin::where('api_token', hash('sha256', $token))
                               ->where('is_active', true)
                               ->first();
            if ($admin) {
                $request->merge(['super_admin' => $admin]);
                return $next($request);
            }
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        return redirect('/admin/login');
    }
}
