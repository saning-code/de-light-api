<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'tenant_id', 'shop_id', 'category_id',
        'name', 'sku', 'barcode', 'description', 'unit',
        'selling_price', 'cost_price', 'wholesale_price',
        'quantity', 'reorder_level', 'max_stock_level',
        'track_inventory', 'allow_negative_stock',
        'image', 'images', 'is_active', 'is_featured',
        'tax_rate', 'discount_percent', 'expiry_date',
        'batch_number', 'attributes',
    ];

    protected $casts = [
        'selling_price'   => 'decimal:2',
        'cost_price'      => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'quantity'        => 'decimal:3',
        'reorder_level'   => 'decimal:3',
        'max_stock_level' => 'decimal:3',
        'tax_rate'        => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'track_inventory' => 'boolean',
        'allow_negative_stock' => 'boolean',
        'is_active'       => 'boolean',
        'is_featured'     => 'boolean',
        'images'          => 'array',
        'attributes'      => 'array',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function shop() { return $this->belongsTo(Shop::class); }
    public function category() { return $this->belongsTo(Category::class); }
    public function stockMovements() { return $this->hasMany(StockMovement::class); }
    public function saleItems() { return $this->hasMany(SaleItem::class); }
    public function purchaseItems() { return $this->hasMany(PurchaseItem::class); }

    // ─── Stock helpers ────────────────────────────────────────────────────────

    public function isLowStock(): bool
    {
        return $this->track_inventory && $this->quantity <= $this->reorder_level;
    }

    public function isOutOfStock(): bool
    {
        return $this->track_inventory && $this->quantity <= 0;
    }

    public function canSell(float $qty): bool
    {
        if (!$this->track_inventory) return true;
        if ($this->allow_negative_stock) return true;
        return $this->quantity >= $qty;
    }

    public function getProfit(): float
    {
        return $this->selling_price - $this->cost_price;
    }

    public function getProfitMargin(): float
    {
        if ($this->selling_price <= 0) return 0;
        return ($this->getProfit() / $this->selling_price) * 100;
    }

    public function getStockValue(): float
    {
        return $this->quantity * $this->cost_price;
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query) { return $query->where('is_active', true); }
    public function scopeForTenant($query, $tenantId) { return $query->where('tenant_id', $tenantId); }
    public function scopeForShop($query, $shopId) { return $query->where('shop_id', $shopId); }

    public function scopeLowStock($query)
    {
        return $query->where('track_inventory', true)
                     ->whereColumn('quantity', '<=', 'reorder_level')
                     ->where('quantity', '>', 0);
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('track_inventory', true)->where('quantity', '<=', 0);
    }

    public function scopeByBarcode($query, string $barcode)
    {
        return $query->where('barcode', $barcode);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'LIKE', "%{$term}%")
              ->orWhere('barcode', 'LIKE', "%{$term}%")
              ->orWhere('sku', 'LIKE', "%{$term}%");
        });
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($product) {
            if (empty($product->uuid)) {
                $product->uuid = \Illuminate\Support\Str::uuid();
            }
            if (empty($product->sku)) {
                $product->sku = 'PRD-' . strtoupper(\Illuminate\Support\Str::random(8));
            }
        });
    }
}
