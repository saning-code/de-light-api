<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send low stock alert notification.
     */
    public function sendLowStockAlert(Product $product, int $tenantId, int $shopId): void
    {
        $title = "Low Stock Alert: {$product->name}";
        $body = "Product '{$product->name}' is running low. Remaining quantity: {$product->quantity} {$product->unit}. Reorder level is {$product->reorder_level}.";

        // Create in-app notification for owners/managers
        $users = User::where('tenant_id', $tenantId)
                     ->where(fn($q) => $q->whereNull('shop_id')->orWhere('shop_id', $shopId))
                     ->whereIn('role', ['owner', 'manager'])
                     ->get();

        foreach ($users as $user) {
            Notification::create([
                'uuid'      => \Illuminate\Support\Str::uuid(),
                'tenant_id' => $tenantId,
                'user_id'   => $user->id,
                'type'      => 'low_stock',
                'title'     => $title,
                'body'      => $body,
                'data'      => [
                    'product_id' => $product->id,
                    'quantity'   => $product->quantity,
                    'unit'       => $product->unit,
                ],
                'icon'      => 'warning',
                'color'     => '#D97706',
            ]);
        }

        Log::info("Low stock alert generated", ['product_id' => $product->id, 'tenant_id' => $tenantId]);
    }
}
