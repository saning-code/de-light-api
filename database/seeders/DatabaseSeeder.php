<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\Shop;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\SuperAdmin;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ─── 0. Super Admin (Platform Owner) ─────────────────────────────────
        SuperAdmin::firstOrCreate(
            ['email' => 'admin@delight.app'],
            [
                'name'     => 'De-Light Super Admin',
                'email'    => 'admin@delight.app',
                'password' => Hash::make('DeLight@Admin2024!'),
                'role'     => 'super_admin',
                'is_active'=> true,
            ]
        );

        // ─── 1. Subscription Plans ────────────────────────────────────────────
        $basic = SubscriptionPlan::firstOrCreate(['slug' => 'basic'], [
            'name'                 => 'Basic Shop',
            'slug'                 => 'basic',
            'description'          => 'Perfect for small single-store businesses in Ghana.',
            'price'                => 150.00, // GH₵ 150/mo
            'billing_cycle'        => 'monthly',
            'max_shops'            => 1,
            'max_users'            => 3,
            'max_products'         => 1000,
            'has_reports'          => true,
            'has_barcode'          => true,
            'has_bluetooth_print'  => true,
            'has_cloud_backup'     => true,
            'has_multi_shop'       => false,
            'trial_days'           => 14,
        ]);

        $premium = SubscriptionPlan::firstOrCreate(['slug' => 'premium'], [
            'name'                 => 'Enterprise Multi-Shop',
            'slug'                 => 'premium',
            'description'          => 'For growing businesses with multiple shop locations.',
            'price'                => 350.00, // GH₵ 350/mo
            'billing_cycle'        => 'monthly',
            'max_shops'            => 10,
            'max_users'            => 20,
            'max_products'         => 50000,
            'has_reports'          => true,
            'has_barcode'          => true,
            'has_bluetooth_print'  => true,
            'has_cloud_backup'     => true,
            'has_multi_shop'       => true,
            'has_analytics'        => true,
            'trial_days'           => 14,
        ]);

        // 2. Demo Tenant
        $tenant = Tenant::firstOrCreate(['owner_email' => 'kwame@delight.com'], [
            'business_name'        => 'De-Light Drinks & Provisions Store',
            'business_code'        => 'DL-GH001',
            'business_type'        => 'Drinks & Provisions',
            'owner_name'           => 'Kwame Mensah',
            'owner_email'          => 'kwame@delight.com',
            'owner_phone'          => '0244123456',
            'country'              => 'Ghana',
            'city'                 => 'Accra',
            'region'               => 'Greater Accra',
            'address'              => 'Osu Oxford Street, Accra',
            'status'               => 'active',
            'subscription_plan_id' => $basic->id,
            'currency'             => 'GHS',
            'currency_symbol'      => '₵',
        ]);

        // 3. Demo Shop
        $shop = Shop::firstOrCreate(['tenant_id' => $tenant->id, 'is_primary' => true], [
            'tenant_id'  => $tenant->id,
            'name'       => 'De-Light Main Store (Osu)',
            'type'       => 'Drinks & Provisions',
            'phone'      => '0244123456',
            'address'    => 'Osu Oxford Street, Accra',
            'city'       => 'Accra',
            'region'     => 'Greater Accra',
            'currency'   => 'GHS',
            'currency_symbol' => '₵',
            'is_primary' => true,
            'is_active'  => true,
            'receipt_settings' => [
                'header'    => 'DE-LIGHT DRINKS & PROVISIONS',
                'address'   => 'Osu Oxford Street, Accra, Ghana',
                'phone'     => 'Tel: 0244-123-456 / 0200-987-654',
                'footer'    => 'Medaase! Thank you for buying from De-Light!',
                'show_logo' => true,
            ],
        ]);

        // 4. Owner & Staff Users
        User::firstOrCreate(
            ['email' => 'owner@delight.com'],
            [
                'tenant_id'           => $tenant->id,
                'shop_id'             => $shop->id,
                'name'                => 'Kwame Mensah (Owner)',
                'email'               => 'owner@delight.com',
                'phone'               => '0244123456',
                'password'            => Hash::make('password123'),
                'pin'                 => Hash::make('1234'),
                'role'                => 'owner',
                'can_give_discount'   => true,
                'max_discount_percent'=> 100,
                'can_delete_sale'     => true,
                'can_view_reports'    => true,
                'can_manage_products' => true,
                'can_manage_users'    => true,
                'can_view_cost_price' => true,
                'is_active'           => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'cashier@delight.com'],
            [
                'tenant_id'           => $tenant->id,
                'shop_id'             => $shop->id,
                'name'                => 'Ama Serwaa (Cashier)',
                'email'               => 'cashier@delight.com',
                'phone'               => '0555987654',
                'password'            => Hash::make('password123'),
                'pin'                 => Hash::make('5678'),
                'role'                => 'cashier',
                'can_give_discount'   => true,
                'max_discount_percent'=> 10,
                'can_delete_sale'     => false,
                'can_view_reports'    => false,
                'can_manage_products' => true,
                'can_manage_users'    => false,
                'can_view_cost_price' => false,
                'is_active'           => true,
            ]
        );

        // 5. Categories
        $catDrinks = Category::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Soft Drinks & Juices'],
            ['shop_id' => $shop->id, 'color' => '#2563EB', 'icon' => 'local_drink']
        );
        $catBeer = Category::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Beers & Stout'],
            ['shop_id' => $shop->id, 'color' => '#D97706', 'icon' => 'sports_bar']
        );
        $catProvisions = Category::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Rice & Grains'],
            ['shop_id' => $shop->id, 'color' => '#059669', 'icon' => 'shopping_bag']
        );
        $catOil = Category::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Cooking Oils'],
            ['shop_id' => $shop->id, 'color' => '#7C3AED', 'icon' => 'oil_barrel']
        );

        // 6. Products
        $products = [
            ['name'=>'Coca Cola 50cl (Crate of 24)','barcode'=>'0544900000099','category_id'=>$catDrinks->id,'unit'=>'crate','selling_price'=>120.00,'cost_price'=>95.00,'quantity'=>50,'reorder_level'=>10],
            ['name'=>'Maltina Can 33cl (Pack of 24)','barcode'=>'6001050001234','category_id'=>$catDrinks->id,'unit'=>'pack','selling_price'=>150.00,'cost_price'=>125.00,'quantity'=>30,'reorder_level'=>5],
            ['name'=>'Club Premium Lager 62cl Bottle','barcode'=>'6001089000112','category_id'=>$catBeer->id,'unit'=>'bottle','selling_price'=>18.00,'cost_price'=>13.50,'quantity'=>120,'reorder_level'=>24],
            ['name'=>'Guinness Foreign Extra Stout','barcode'=>'6001089000999','category_id'=>$catBeer->id,'unit'=>'bottle','selling_price'=>20.00,'cost_price'=>15.00,'quantity'=>80,'reorder_level'=>20],
            ['name'=>'Royal Aroma Perfumed Rice 5KG','barcode'=>'8935001234567','category_id'=>$catProvisions->id,'unit'=>'bag','selling_price'=>165.00,'cost_price'=>135.00,'quantity'=>45,'reorder_level'=>8],
            ['name'=>'Gino Cooking Oil 5 Litres','barcode'=>'6001099887766','category_id'=>$catOil->id,'unit'=>'gallon','selling_price'=>195.00,'cost_price'=>165.00,'quantity'=>25,'reorder_level'=>5],
            ['name'=>'Pureco Pure Vegetable Oil 1L','barcode'=>'6001099887711','category_id'=>$catOil->id,'unit'=>'bottle','selling_price'=>42.00,'cost_price'=>34.00,'quantity'=>4,'reorder_level'=>10],
        ];

        foreach ($products as $pData) {
            Product::firstOrCreate(
                ['tenant_id' => $tenant->id, 'barcode' => $pData['barcode']],
                array_merge($pData, [
                    'tenant_id'       => $tenant->id,
                    'shop_id'         => $shop->id,
                    'track_inventory' => true,
                    'is_active'       => true,
                ])
            );
        }

        // 7. Customers
        Customer::firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => '0240111222'],
            ['shop_id' => $shop->id, 'name' => 'Kofi Kinaata (Wholesale Client)', 'credit_limit' => 2000.00, 'credit_balance' => 350.00]
        );
        Customer::firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => '0277333444'],
            ['shop_id' => $shop->id, 'name' => 'Auntie Mary (Neighbourhood Store)', 'credit_limit' => 1000.00, 'credit_balance' => 0]
        );
    }
}
