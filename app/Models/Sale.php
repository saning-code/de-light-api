<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'local_id', 'tenant_id', 'shop_id', 'user_id', 'customer_id',
        'sale_number', 'subtotal', 'discount_amount', 'discount_percent',
        'tax_amount', 'tax_percent', 'total', 'amount_paid', 'change_given',
        'credit_amount', 'payment_method', 'payment_breakdown', 'status',
        'note', 'reference', 'is_credit_sale', 'synced_at', 'device_id',
    ];

    protected $casts = [
        'subtotal'          => 'decimal:2',
        'discount_amount'   => 'decimal:2',
        'discount_percent'  => 'decimal:2',
        'tax_amount'        => 'decimal:2',
        'tax_percent'       => 'decimal:2',
        'total'             => 'decimal:2',
        'amount_paid'       => 'decimal:2',
        'change_given'      => 'decimal:2',
        'credit_amount'     => 'decimal:2',
        'payment_breakdown' => 'array',
        'is_credit_sale'    => 'boolean',
        'synced_at'         => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function tenant()    { return $this->belongsTo(Tenant::class); }
    public function shop()      { return $this->belongsTo(Shop::class); }
    public function user()      { return $this->belongsTo(User::class); }
    public function customer()  { return $this->belongsTo(Customer::class); }
    public function items()     { return $this->hasMany(SaleItem::class); }
    public function creditSale(){ return $this->hasOne(CreditSale::class); }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function getTotalProfit(): float
    {
        return $this->items->sum('profit');
    }

    public function getTotalCost(): float
    {
        return $this->items->sum(fn ($i) => $i->cost_price * $i->quantity);
    }

    public function isPaid(): bool
    {
        return $this->status === 'completed' && $this->credit_amount <= 0;
    }

    public function isVoided(): bool { return $this->status === 'voided'; }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeCompleted($query)   { return $query->where('status', 'completed'); }
    public function scopeForTenant($query, $tenantId) { return $query->where('tenant_id', $tenantId); }
    public function scopeForShop($query, $shopId) { return $query->where('shop_id', $shopId); }
    public function scopeToday($query)       { return $query->whereDate('created_at', today()); }
    public function scopeThisWeek($query)    { return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]); }
    public function scopeThisMonth($query)   { return $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year); }
    public function scopeThisYear($query)    { return $query->whereYear('created_at', now()->year); }

    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    // ─── Auto-generate sale number ────────────────────────────────────────────

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($sale) {
            if (empty($sale->uuid)) {
                $sale->uuid = \Illuminate\Support\Str::uuid();
            }
            if (empty($sale->sale_number)) {
                $today = now()->format('Ymd');
                $count = self::where('shop_id', $sale->shop_id)
                             ->whereDate('created_at', today())
                             ->count() + 1;
                $sale->sale_number = 'INV-' . $today . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }
        });
    }
}
