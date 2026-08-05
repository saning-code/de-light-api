<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'business_name',
        'business_code',
        'business_type',
        'owner_name',
        'owner_email',
        'owner_phone',
        'country',
        'city',
        'region',
        'address',
        'logo',
        'status',
        'subscription_plan_id',
        'trial_ends_at',
        'subscription_ends_at',
        'timezone',
        'currency',
        'currency_symbol',
        'settings',
        'last_login_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function shops()
    {
        return $this->hasMany(Shop::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function subscriptionPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)->where('status', 'active')->latest();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isOnTrial(): bool
    {
        return $this->status === 'trial' && $this->trial_ends_at?->isFuture();
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function canAddShop(): bool
    {
        $maxShops = $this->subscriptionPlan?->max_shops ?? 1;
        return $this->shops()->active()->count() < $maxShops;
    }

    public function canAddUser(): bool
    {
        $maxUsers = $this->subscriptionPlan?->max_users ?? 3;
        return $this->users()->where('is_active', true)->count() < $maxUsers;
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', 'suspended');
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? asset('storage/' . $this->logo) : null;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tenant) {
            if (empty($tenant->uuid)) {
                $tenant->uuid = \Illuminate\Support\Str::uuid();
            }
            if (empty($tenant->business_code)) {
                $tenant->business_code = self::generateBusinessCode();
            }
        });
    }

    public static function generateBusinessCode(): string
    {
        do {
            $code = 'DL-' . strtoupper(\Illuminate\Support\Str::random(6));
        } while (self::where('business_code', $code)->exists());

        return $code;
    }
}
