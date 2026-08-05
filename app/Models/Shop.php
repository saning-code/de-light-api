<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shop extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'tenant_id', 'name', 'type', 'phone', 'email',
        'address', 'city', 'region', 'country', 'logo',
        'currency', 'currency_symbol', 'tin_number',
        'charge_tax', 'tax_rate', 'is_primary', 'is_active',
        'receipt_settings', 'settings',
    ];

    protected $casts = [
        'charge_tax' => 'boolean',
        'tax_rate' => 'decimal:2',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'receipt_settings' => 'array',
        'settings' => 'array',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function users() { return $this->hasMany(User::class); }
    public function categories() { return $this->hasMany(Category::class); }
    public function products() { return $this->hasMany(Product::class); }
    public function customers() { return $this->hasMany(Customer::class); }
    public function suppliers() { return $this->hasMany(Supplier::class); }
    public function sales() { return $this->hasMany(Sale::class); }
    public function purchases() { return $this->hasMany(Purchase::class); }
    public function expenses() { return $this->hasMany(Expense::class); }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query) { return $query->where('is_active', true); }
    public function scopeForTenant($query, $tenantId) { return $query->where('tenant_id', $tenantId); }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? asset('storage/' . $this->logo) : null;
    }

    // ─── Computed stats ───────────────────────────────────────────────────────

    public function getTodaySalesTotal(): float
    {
        return $this->sales()
            ->whereDate('created_at', today())
            ->where('status', 'completed')
            ->sum('total');
    }

    public function getTodayProfit(): float
    {
        return $this->sales()
            ->with('items')
            ->whereDate('created_at', today())
            ->where('status', 'completed')
            ->get()
            ->sum(fn ($sale) => $sale->items->sum('profit'));
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($shop) {
            if (empty($shop->uuid)) {
                $shop->uuid = \Illuminate\Support\Str::uuid();
            }
        });
    }
}
