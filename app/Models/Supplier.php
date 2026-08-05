<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'tenant_id', 'shop_id', 'name', 'phone', 'email',
        'address', 'city', 'company_name', 'supplier_code',
        'total_supplied', 'total_paid', 'balance_owed',
        'is_active', 'notes', 'meta',
    ];

    protected $casts = [
        'total_supplied' => 'decimal:2',
        'total_paid'     => 'decimal:2',
        'balance_owed'   => 'decimal:2',
        'is_active'       => 'boolean',
        'meta'           => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($s) => $s->uuid = $s->uuid ?? \Illuminate\Support\Str::uuid());
    }
}
