<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Shop;
use App\Models\SubscriptionPlan;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService) {}

    /**
     * Register a new business (tenant + owner user + primary shop).
     * POST /api/v1/auth/register
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'business_name'  => 'required|string|max:255',
            'business_type'  => 'nullable|string|max:100',
            'owner_name'     => 'required|string|max:255',
            'owner_email'    => 'required|email|unique:tenants,owner_email',
            'owner_phone'    => 'required|string|max:20',
            'password'       => 'required|string|min:8',
            'shop_name'      => 'nullable|string|max:255',
            'city'           => 'nullable|string|max:100',
            'region'         => 'nullable|string|max:100',
            'plan_id'        => 'nullable|exists:subscription_plans,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        \DB::beginTransaction();
        try {
            // Get trial plan or basic plan
            $plan = $request->plan_id
                ? SubscriptionPlan::findOrFail($request->plan_id)
                : SubscriptionPlan::where('slug', 'basic')->first();

            // Create tenant
            $tenant = Tenant::create([
                'business_name'   => $request->business_name,
                'business_type'   => $request->business_type,
                'owner_name'      => $request->owner_name,
                'owner_email'     => $request->owner_email,
                'owner_phone'     => $request->owner_phone,
                'city'            => $request->city,
                'region'          => $request->region,
                'status'          => 'trial',
                'subscription_plan_id' => $plan?->id,
                'trial_ends_at'   => Carbon::now()->addDays($plan?->trial_days ?? 14),
            ]);

            // Create primary shop
            $shop = Shop::create([
                'tenant_id'  => $tenant->id,
                'name'       => $request->shop_name ?? $request->business_name,
                'type'       => $request->business_type ?? 'general',
                'city'       => $request->city,
                'is_primary' => true,
                'is_active'  => true,
            ]);

            // Create owner user
            $user = User::create([
                'tenant_id'         => $tenant->id,
                'shop_id'           => $shop->id,
                'name'              => $request->owner_name,
                'email'             => $request->owner_email,
                'phone'             => $request->owner_phone,
                'password'          => Hash::make($request->password),
                'role'              => 'owner',
                'can_give_discount' => true,
                'can_delete_sale'   => true,
                'can_view_reports'  => true,
                'can_manage_products' => true,
                'can_manage_users'  => true,
                'can_view_cost_price' => true,
                'is_active'         => true,
            ]);

            $token = JWTAuth::fromUser($user);

            \DB::commit();

            return $this->successResponse([
                'token'    => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60,
                'user'     => $this->formatUser($user),
                'tenant'   => $this->formatTenant($tenant),
                'shop'     => $shop,
            ], 'Business registered successfully. Your trial starts now!', 201);

        } catch (\Exception $e) {
            \DB::rollBack();
            return $this->errorResponse('Registration failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Login with email + password.
     * POST /api/v1/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
            'device_id'   => 'nullable|string',
            'device_name' => 'nullable|string',
            'fcm_token'   => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        try {
            if (!$token = JWTAuth::attempt([
                'email'     => $request->email,
                'password'  => $request->password,
                'is_active' => true,
            ])) {
                return $this->errorResponse('Invalid email or password', 401);
            }

            $user = auth()->user();

            // Check tenant status
            if ($user->tenant->isSuspended()) {
                return $this->errorResponse('Your account has been suspended. Please contact support.', 403);
            }

            // Update last login
            $user->update(['last_login_at' => now(), 'fcm_token' => $request->fcm_token]);
            $user->tenant->update(['last_login_at' => now()]);

            // Register device
            if ($request->device_id) {
                $this->registerDevice($user, $request);
            }

            return $this->successResponse([
                'token'      => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60,
                'user'       => $this->formatUser($user),
                'tenant'     => $this->formatTenant($user->tenant),
                'shop'       => $user->shop,
                'permissions'=> $this->formatPermissions($user),
            ], 'Login successful');

        } catch (JWTException $e) {
            return $this->errorResponse('Could not create token', 500);
        }
    }

    /**
     * PIN-based login (fast login for existing session device).
     * POST /api/v1/auth/pin-login
     */
    public function pinLogin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_uuid' => 'required|string',
            'pin'       => 'required|string|min:4|max:6',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        $user = User::where('uuid', $request->user_uuid)->where('is_active', true)->first();

        if (!$user || !$user->verifyPin($request->pin)) {
            return $this->errorResponse('Invalid PIN', 401);
        }

        if ($user->tenant->isSuspended()) {
            return $this->errorResponse('Account suspended', 403);
        }

        $token = JWTAuth::fromUser($user);
        $user->update(['last_login_at' => now()]);

        return $this->successResponse([
            'token'      => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'user'       => $this->formatUser($user),
            'permissions'=> $this->formatPermissions($user),
        ], 'PIN login successful');
    }

    /**
     * Logout - invalidate token.
     * POST /api/v1/auth/logout
     */
    public function logout(): JsonResponse
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return $this->successResponse([], 'Logged out successfully');
        } catch (JWTException $e) {
            return $this->errorResponse('Failed to logout', 500);
        }
    }

    /**
     * Refresh JWT token.
     * POST /api/v1/auth/refresh
     */
    public function refresh(): JsonResponse
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());
            return $this->successResponse([
                'token'      => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60,
            ]);
        } catch (JWTException $e) {
            return $this->errorResponse('Token cannot be refreshed', 401);
        }
    }

    /**
     * Get authenticated user's profile.
     * GET /api/v1/auth/me
     */
    public function me(): JsonResponse
    {
        $user = auth()->user()->load(['tenant', 'shop']);
        return $this->successResponse([
            'user'        => $this->formatUser($user),
            'tenant'      => $this->formatTenant($user->tenant),
            'shop'        => $user->shop,
            'permissions' => $this->formatPermissions($user),
        ]);
    }

    /**
     * Update authenticated user's PIN.
     * POST /api/v1/auth/set-pin
     */
    public function setPin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pin'             => 'required|string|min:4|max:6',
            'pin_confirmation'=> 'required|same:pin',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        auth()->user()->update(['pin' => Hash::make($request->pin)]);

        return $this->successResponse([], 'PIN set successfully');
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function registerDevice(User $user, Request $request): void
    {
        \App\Models\Device::updateOrCreate(
            ['tenant_id' => $user->tenant_id, 'device_id' => $request->device_id],
            [
                'user_id'      => $user->id,
                'shop_id'      => $user->shop_id,
                'device_name'  => $request->device_name,
                'device_model' => $request->device_model,
                'os_version'   => $request->os_version,
                'app_version'  => $request->app_version,
                'fcm_token'    => $request->fcm_token,
                'last_seen_at' => now(),
                'is_active'    => true,
            ]
        );
    }

    private function formatUser(User $user): array
    {
        return [
            'id'         => $user->id,
            'uuid'       => $user->uuid,
            'name'       => $user->name,
            'email'      => $user->email,
            'phone'      => $user->phone,
            'role'       => $user->role,
            'avatar_url' => $user->avatar_url,
            'shop_id'    => $user->shop_id,
            'tenant_id'  => $user->tenant_id,
        ];
    }

    private function formatTenant(Tenant $tenant): array
    {
        return [
            'id'             => $tenant->id,
            'uuid'           => $tenant->uuid,
            'business_name'  => $tenant->business_name,
            'business_code'  => $tenant->business_code,
            'business_type'  => $tenant->business_type,
            'logo_url'       => $tenant->logo_url,
            'status'         => $tenant->status,
            'currency'       => $tenant->currency,
            'currency_symbol'=> $tenant->currency_symbol,
            'trial_ends_at'  => $tenant->trial_ends_at,
            'subscription_ends_at' => $tenant->subscription_ends_at,
        ];
    }

    private function formatPermissions(User $user): array
    {
        return [
            'can_give_discount'   => $user->isOwner() || $user->can_give_discount,
            'max_discount_percent'=> $user->isOwner() ? 100 : $user->max_discount_percent,
            'can_delete_sale'     => $user->isOwner() || $user->can_delete_sale,
            'can_view_reports'    => $user->isOwner() || $user->can_view_reports,
            'can_manage_products' => $user->isOwner() || $user->can_manage_products,
            'can_manage_users'    => $user->isOwner() || $user->can_manage_users,
            'can_view_cost_price' => $user->isOwner() || $user->can_view_cost_price,
        ];
    }

    // ─── Standard response helpers ────────────────────────────────────────────

    private function successResponse(array $data, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    private function errorResponse(string $message, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => null,
        ], $status);
    }
}
