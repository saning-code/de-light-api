<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\SuperAdmin;

class SuperAdminAuth
{
    public function handle(Request $request, Closure $next)
    {
        $admin = null;

        // Cookie-based auth (HTML dashboard)
        $token = $request->cookie('admin_token');
        if ($token) {
            $admin = SuperAdmin::where('api_token', hash('sha256', $token))
                               ->where('is_active', true)
                               ->first();
        }

        // Bearer token auth (AJAX from dashboard)
        if (!$admin) {
            $bearer = $request->bearerToken();
            if ($bearer) {
                $admin = SuperAdmin::where('api_token', hash('sha256', $bearer))
                                   ->where('is_active', true)
                                   ->first();
            }
        }

        if (!$admin) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }
            return redirect('/admin/login');
        }

        $request->merge(['super_admin' => $admin]);
        return $next($request);
    }
}
