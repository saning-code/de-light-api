<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid', 'tenant_id', 'user_id', 'type', 'title', 'body',
        'data', 'icon', 'color', 'read_at',
    ];

    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($n) => $n->uuid = $n->uuid ?? \Illuminate\Support\Str::uuid());
    }
}
