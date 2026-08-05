<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid', 'tenant_id', 'user_id', 'shop_id', 'device_id',
        'device_name', 'device_model', 'os_version', 'app_version',
        'fcm_token', 'is_active', 'last_seen_at',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($d) => $d->uuid = $d->uuid ?? \Illuminate\Support\Str::uuid());
    }
}
