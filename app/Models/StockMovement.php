<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid', 'tenant_id', 'shop_id', 'product_id', 'user_id',
        'type', 'quantity', 'balance_before', 'balance_after',
        'unit_cost', 'reference_type', 'reference_id', 'note',
    ];

    protected $casts = [
        'quantity'       => 'decimal:3',
        'balance_before' => 'decimal:3',
        'balance_after'  => 'decimal:3',
        'unit_cost'      => 'decimal:2',
    ];

    public function product() { return $this->belongsTo(Product::class); }
    public function user()    { return $this->belongsTo(User::class); }

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($sm) => $sm->uuid = $sm->uuid ?? \Illuminate\Support\Str::uuid());
    }
}
