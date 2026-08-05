<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'uuid', 'tenant_id', 'shop_id', 'name', 'email', 'phone',
        'password', 'pin', 'role', 'avatar', 'is_active',
        'can_give_discount', 'max_discount_percent', 'can_delete_sale',
        'can_view_reports', 'can_manage_products', 'can_manage_users',
        'can_view_cost_price', 'last_login_at', 'fcm_token',
    ];

    protected $hidden = ['password', 'pin', 'remember_token'];

    protected $casts = [
        'is_active' => 'boolean',
        'can_give_discount' => 'boolean',
        'can_delete_sale' => 'boolean',
        'can_view_reports' => 'boolean',
        'can_manage_products' => 'boolean',
        'can_manage_users' => 'boolean',
        'can_view_cost_price' => 'boolean',
        'max_discount_percent' => 'decimal:2',
        'last_login_at' => 'datetime',
        'email_verified_at' => 'datetime',
    ];

    // ─── JWT ──────────────────────────────────────────────────────────────────

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'tenant_id' => $this->tenant_id,
            'shop_id'   => $this->shop_id,
            'role'      => $this->role,
            'uuid'      => $this->uuid,
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function shop() { return $this->belongsTo(Shop::class); }
    public function sales() { return $this->hasMany(Sale::class); }
    public function expenses() { return $this->hasMany(Expense::class); }
    public function devices() { return $this->hasMany(Device::class); }
    public function notifications() { return $this->hasMany(Notification::class); }

    // ─── Role helpers ─────────────────────────────────────────────────────────

    public function isOwner(): bool { return $this->role === 'owner'; }
    public function isManager(): bool { return $this->role === 'manager'; }
    public function isCashier(): bool { return $this->role === 'cashier'; }
    public function isSalesperson(): bool { return $this->role === 'salesperson'; }

    public function hasRole(string|array $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];
        return in_array($this->role, $roles);
    }

    public function canAccess(string $permission): bool
    {
        if ($this->isOwner()) return true;

        return match($permission) {
            'give_discount' => $this->can_give_discount,
            'delete_sale'   => $this->can_delete_sale,
            'view_reports'  => $this->can_view_reports,
            'manage_products' => $this->can_manage_products,
            'manage_users'  => $this->can_manage_users,
            'view_cost_price' => $this->can_view_cost_price,
            default         => false,
        };
    }

    public function verifyPin(string $pin): bool
    {
        return \Illuminate\Support\Facades\Hash::check($pin, $this->pin);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query) { return $query->where('is_active', true); }
    public function scopeForTenant($query, $tenantId) { return $query->where('tenant_id', $tenantId); }
    public function scopeForShop($query, $shopId) { return $query->where('shop_id', $shopId); }
    public function scopeRole($query, string $role) { return $query->where('role', $role); }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? asset('storage/' . $this->avatar) : null;
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($user) {
            if (empty($user->uuid)) {
                $user->uuid = \Illuminate\Support\Str::uuid();
            }
        });
    }
}
