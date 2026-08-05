<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditSale extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'tenant_id', 'shop_id', 'sale_id', 'customer_id',
        'total_amount', 'amount_paid', 'balance', 'due_date',
        'status', 'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'amount_paid'  => 'decimal:2',
        'balance'      => 'decimal:2',
        'due_date'     => 'date',
    ];

    public function sale()     { return $this->belongsTo(Sale::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function payments() { return $this->hasMany(CreditPayment::class); }

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($cs) => $cs->uuid = $cs->uuid ?? \Illuminate\Support\Str::uuid());
    }
}
