<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'tenant_id', 'shop_id', 'name', 'color', 'icon',
        'description', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tenant()  { return $this->belongsTo(Tenant::class); }
    public function shop()    { return $this->belongsTo(Shop::class); }
    public function products(){ return $this->hasMany(Product::class); }

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($cat) => $cat->uuid = $cat->uuid ?? \Illuminate\Support\Str::uuid());
    }
}
