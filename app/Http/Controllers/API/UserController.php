<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * List all users for the tenant.
     * GET /api/v1/users
     */
    public function index(): JsonResponse
    {
        $user = auth()->user();
        if (!$user->canAccess('manage_users')) {
            return $this->errorResponse('Permission denied', 403);
        }

        $users = User::where('tenant_id', $user->tenant_id)
                     ->select(['id','uuid','name','email','phone','role','is_active','created_at'])
                     ->get();

        return $this->successResponse(['users' => $users]);
    }

    /**
     * Create a new staff user.
     * POST /api/v1/users
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        if (!$user->canAccess('manage_users')) {
            return $this->errorResponse('Permission denied', 403);
        }

        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'nullable|email',
            'phone'    => 'nullable|string|max:20',
            'password' => 'required|string|min:6',
            'role'     => 'required|in:manager,cashier,salesperson',
            'can_give_discount'    => 'nullable|boolean',
            'max_discount_percent' => 'nullable|numeric|min:0|max:100',
            'can_delete_sale'      => 'nullable|boolean',
            'can_view_reports'     => 'nullable|boolean',
            'can_manage_products'  => 'nullable|boolean',
            'can_view_cost_price'  => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        // Check email uniqueness within tenant
        if ($request->email) {
            $exists = User::where('tenant_id', $user->tenant_id)
                          ->where('email', $request->email)->exists();
            if ($exists) {
                return $this->errorResponse('Email already used in this business', 422);
            }
        }

        $newUser = User::create([
            'tenant_id'            => $user->tenant_id,
            'shop_id'              => $request->shop_id ?? $user->shop_id,
            'name'                 => $request->name,
            'email'                => $request->email,
            'phone'                => $request->phone,
            'password'             => Hash::make($request->password),
            'role'                 => $request->role,
            'can_give_discount'    => $request->boolean('can_give_discount', false),
            'max_discount_percent' => $request->max_discount_percent ?? 0,
            'can_delete_sale'      => $request->boolean('can_delete_sale', false),
            'can_view_reports'     => $request->boolean('can_view_reports', false),
            'can_manage_products'  => $request->boolean('can_manage_products', true),
            'can_manage_users'     => false,
            'can_view_cost_price'  => $request->boolean('can_view_cost_price', false),
            'is_active'            => true,
        ]);

        return $this->successResponse(
            ['user' => $newUser->makeHidden(['password','pin'])],
            'Staff account created successfully',
            201
        );
    }

    /**
     * Update a staff user.
     * PUT /api/v1/users/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $auth = auth()->user();
        if (!$auth->canAccess('manage_users')) {
            return $this->errorResponse('Permission denied', 403);
        }

        $user = User::where('tenant_id', $auth->tenant_id)->findOrFail($id);

        if ($user->isOwner()) {
            return $this->errorResponse('Cannot modify the owner account', 403);
        }

        if ($request->filled('password')) {
            $request->merge(['password' => Hash::make($request->password)]);
        }

        $user->update($request->except(['tenant_id','shop_id','uuid','role','email']));

        return $this->successResponse(
            ['user' => $user->fresh()->makeHidden(['password','pin'])],
            'User updated'
        );
    }

    /**
     * Toggle user active status.
     * POST /api/v1/users/{id}/toggle
     */
    public function toggle(int $id): JsonResponse
    {
        $auth = auth()->user();
        if (!$auth->canAccess('manage_users')) {
            return $this->errorResponse('Permission denied', 403);
        }

        $user = User::where('tenant_id', $auth->tenant_id)->findOrFail($id);
        if ($user->isOwner()) {
            return $this->errorResponse('Cannot deactivate the owner account', 403);
        }

        $user->update(['is_active' => !$user->is_active]);
        $status = $user->is_active ? 'activated' : 'deactivated';

        return $this->successResponse(
            ['user' => $user->makeHidden(['password','pin'])],
            "User {$status} successfully"
        );
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
