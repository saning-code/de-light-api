<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'tenant_id', 'shop_id', 'name', 'phone', 'email',
        'address', 'city', 'customer_code', 'group', 'credit_limit',
        'credit_balance', 'total_purchases', 'total_transactions',
        'last_purchase_at', 'is_active', 'avatar', 'notes', 'meta',
    ];

    protected $casts = [
        'credit_limit'     => 'decimal:2',
        'credit_balance'   => 'decimal:2',
        'total_purchases'  => 'decimal:2',
        'is_active'         => 'boolean',
        'last_purchase_at' => 'datetime',
        'meta'             => 'array',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function shop()   { return $this->belongsTo(Shop::class); }
    public function sales()  { return $this->hasMany(Sale::class); }

    protected static function boot()
    {
        parent::boot();
        static::creating(function($c) {
            $c->uuid = $c->uuid ?? \Illuminate\Support\Str::uuid();
            $c->customer_code = $c->customer_code ?? 'CUST-' . strtoupper(\Illuminate\Support\Str::random(6));
        });
    }
}
