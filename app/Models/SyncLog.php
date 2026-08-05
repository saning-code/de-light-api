<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'device_id', 'shop_id', 'table_name',
        'last_pulled_at', 'last_pushed_at', 'pending_push_count',
        'pending_pull_count', 'status', 'last_error',
    ];

    protected $casts = [
        'last_pulled_at' => 'datetime',
        'last_pushed_at' => 'datetime',
    ];
}
