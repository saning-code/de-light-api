<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'price', 'billing_cycle',
        'max_shops', 'max_users', 'max_products', 'has_reports',
        'has_barcode', 'has_bluetooth_print', 'has_cloud_backup',
        'has_multi_shop', 'has_analytics', 'has_api_access',
        'features', 'is_active', 'trial_days',
    ];

    protected $casts = [
        'price'              => 'decimal:2',
        'has_reports'        => 'boolean',
        'has_barcode'        => 'boolean',
        'has_bluetooth_print'=> 'boolean',
        'has_cloud_backup'   => 'boolean',
        'has_multi_shop'     => 'boolean',
        'has_analytics'      => 'boolean',
        'has_api_access'     => 'boolean',
        'is_active'          => 'boolean',
        'features'           => 'array',
    ];

    public function tenants()
    {
        return $this->hasMany(Tenant::class);
    }
}
