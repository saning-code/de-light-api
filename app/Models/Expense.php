<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'local_id', 'tenant_id', 'shop_id', 'user_id',
        'category', 'title', 'description', 'amount', 'payment_method',
        'receipt_image', 'expense_date', 'reference', 'is_recurring',
        'recurring_frequency', 'synced_at',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'is_recurring' => 'boolean',
        'synced_at'    => 'datetime',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function shop()   { return $this->belongsTo(Shop::class); }
    public function user()   { return $this->belongsTo(User::class); }

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($e) => $e->uuid = $e->uuid ?? \Illuminate\Support\Str::uuid());
    }
}
