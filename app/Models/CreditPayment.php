<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid', 'local_id', 'credit_sale_id', 'customer_id', 'user_id',
        'amount', 'payment_method', 'reference', 'payment_date',
        'notes', 'synced_at',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'payment_date' => 'date',
        'synced_at'    => 'datetime',
    ];

    public function creditSale() { return $this->belongsTo(CreditSale::class); }
    public function customer()   { return $this->belongsTo(Customer::class); }
    public function user()       { return $this->belongsTo(User::class); }

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($cp) => $cp->uuid = $cp->uuid ?? \Illuminate\Support\Str::uuid());
    }
}
