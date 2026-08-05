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
use App\Models\SuperAdmin;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ─── 0. Super Admin ───────────────────────────────────────────────────
        SuperAdmin::firstOrCreate(
            ['email' => 'admin@delight.app'],
            [
                'name'      => 'De-Light Super Admin',
                'password'  => Hash::make('DeLight@Admin2024!'),
                'role'      => 'super_admin',
                'is_active' => true,
            ]
        );

        // ─── 1. Subscription Plans ────────────────────────────────────────────
        $basic = SubscriptionPlan::firstOrCreate(
            ['slug' => 'basic'],
            [
                'name'                => 'Basic Shop',
                'slug'                => 'basic',
                'description'         => 'Perfect for small single-store businesses.',
                'price'               => 150.00,
                'billing_cycle'       => 'monthly',
                'max_shops'           => 1,
                'max_users'           => 3,
                'max_products'        => 1000,
                'has_reports'         => true,
                'has_barcode'         => true,
                'has_bluetooth_print' => true,
                'has_cloud_backup'    => true,
                'has_multi_shop'      => false,
                'trial_days'          => 14,
            ]
        );

        SubscriptionPlan::firstOrCreate(
            ['slug' => 'premium'],
            [
                'name'                => 'Enterprise Multi-Shop',
                'slug'                => 'premium',
                'description'         => 'For growing businesses with multiple locations.',
                'price'               => 350.00,
                'billing_cycle'       => 'monthly',
                'max_shops'           => 10,
                'max_users'           => 20,
                'max_products'        => 50000,
                'has_reports'         => true,
                'has_barcode'         => true,
                'has_bluetooth_print' => true,
                'has_cloud_backup'    => true,
                'has_multi_shop'      => true,
                'trial_days'          => 14,
            ]
        );

        // ─── 2. Demo Tenant ───────────────────────────────────────────────────
        $tenant = Tenant::firstOrCreate(
            ['owner_email' => 'kwame@delight.com'],
            [
                'business_name'        => 'De-Light Drinks & Provisions Store',
                'business_code'        => 'DL-GH001',
                'business_type'        => 'provisions',
                'owner_name'           => 'Kwame Mensah',
                'owner_email'          => 'kwame@delight.com',
                'owner_phone'          => '0244123456',
                'city'                 => 'Accra',
                'region'               => 'Greater Accra',
                'status'               => 'active',
                'subscription_plan_id' => $basic->id,
                'currency'             => 'GHS',
                'currency_symbol'      => '₵',
                'trial_ends_at'        => Carbon::now()->addDays(14),
            ]
        );

        // ─── 3. Demo Shop ─────────────────────────────────────────────────────
        $shop = Shop::firstOrCreate(
            ['tenant_id' => $tenant->id, 'is_primary' => true],
            [
                'tenant_id'   => $tenant->id,
                'name'        => 'De-Light Main Store (Osu)',
                'type'        => 'provisions',
                'phone'       => '0244123456',
                'address'     => 'Osu Oxford Street, Accra',
                'city'        => 'Accra',
                'is_primary'  => true,
                'is_active'   => true,
            ]
        );

        // ─── 4. Users ─────────────────────────────────────────────────────────
        User::firstOrCreate(
            ['email' => 'owner@delight.com'],
            [
                'tenant_id'            => $tenant->id,
                'shop_id'              => $shop->id,
                'name'                 => 'Kwame Mensah',
                'email'                => 'owner@delight.com',
                'phone'                => '0244123456',
                'password'             => Hash::make('password123'),
                'pin'                  => Hash::make('1234'),
                'role'                 => 'owner',
                'can_give_discount'    => true,
                'max_discount_percent' => 100,
                'can_delete_sale'      => true,
                'can_view_reports'     => true,
                'can_manage_products'  => true,
                'can_manage_users'     => true,
                'can_view_cost_price'  => true,
                'is_active'            => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'cashier@delight.com'],
            [
                'tenant_id'            => $tenant->id,
                'shop_id'              => $shop->id,
                'name'                 => 'Ama Serwaa',
                'email'                => 'cashier@delight.com',
                'phone'                => '0555987654',
                'password'             => Hash::make('password123'),
                'pin'                  => Hash::make('5678'),
                'role'                 => 'cashier',
                'can_give_discount'    => true,
                'max_discount_percent' => 10,
                'can_delete_sale'      => false,
                'can_view_reports'     => false,
                'can_manage_products'  => true,
                'can_manage_users'     => false,
                'can_view_cost_price'  => false,
                'is_active'            => true,
            ]
        );

        // ─── 5. Categories ────────────────────────────────────────────────────
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
        $catSnacks = Category::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Snacks & Confectionery'],
            ['shop_id' => $shop->id, 'color' => '#DB2777', 'icon' => 'cookie']
        );
        $catHousehold = Category::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Household & Cleaning'],
            ['shop_id' => $shop->id, 'color' => '#0891B2', 'icon' => 'cleaning_services']
        );
        $catTobacco = Category::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Tobacco & Matches'],
            ['shop_id' => $shop->id, 'color' => '#64748B', 'icon' => 'smoke_free']
        );

        // ─── 6. Products (15 products) ────────────────────────────────────────
        $products = [
            // Soft Drinks
            [
                'name' => 'Coca Cola 50cl (Crate of 24)',
                'barcode' => '0544900000099',
                'unit' => 'crate',
                'category_id' => $catDrinks->id,
                'selling_price' => 120.00,
                'cost_price' => 95.00,
                'quantity' => 50,
                'reorder_level' => 10,
            ],
            [
                'name' => 'Maltina Can 33cl (Pack of 24)',
                'barcode' => '6001050001234',
                'unit' => 'pack',
                'category_id' => $catDrinks->id,
                'selling_price' => 150.00,
                'cost_price' => 125.00,
                'quantity' => 30,
                'reorder_level' => 5,
            ],
            [
                'name' => 'Alvaro Pineapple 330ml (x24)',
                'barcode' => '5449000024511',
                'unit' => 'pack',
                'category_id' => $catDrinks->id,
                'selling_price' => 135.00,
                'cost_price' => 108.00,
                'quantity' => 20,
                'reorder_level' => 5,
            ],
            [
                'name' => 'Ice Chilled Water 500ml (x24)',
                'barcode' => '6009696000016',
                'unit' => 'carton',
                'category_id' => $catDrinks->id,
                'selling_price' => 42.00,
                'cost_price' => 32.00,
                'quantity' => 60,
                'reorder_level' => 10,
            ],
            // Beers
            [
                'name' => 'Club Premium Lager 62cl Bottle',
                'barcode' => '6001089000112',
                'unit' => 'bottle',
                'category_id' => $catBeer->id,
                'selling_price' => 18.00,
                'cost_price' => 13.50,
                'quantity' => 120,
                'reorder_level' => 24,
            ],
            [
                'name' => 'Guinness Foreign Extra Stout 62cl',
                'barcode' => '6001089000999',
                'unit' => 'bottle',
                'category_id' => $catBeer->id,
                'selling_price' => 20.00,
                'cost_price' => 15.00,
                'quantity' => 80,
                'reorder_level' => 20,
            ],
            [
                'name' => 'Star Lager Beer 33cl Can',
                'barcode' => '6001089001001',
                'unit' => 'can',
                'category_id' => $catBeer->id,
                'selling_price' => 12.00,
                'cost_price' => 9.00,
                'quantity' => 3,
                'reorder_level' => 24,
            ],
            // Rice & Grains
            [
                'name' => 'Royal Aroma Perfumed Rice 5KG',
                'barcode' => '8935001234567',
                'unit' => 'bag',
                'category_id' => $catProvisions->id,
                'selling_price' => 165.00,
                'cost_price' => 135.00,
                'quantity' => 45,
                'reorder_level' => 8,
            ],
            [
                'name' => 'Abeiku Broken Rice 10KG',
                'barcode' => '6009696100019',
                'unit' => 'bag',
                'category_id' => $catProvisions->id,
                'selling_price' => 110.00,
                'cost_price' => 88.00,
                'quantity' => 22,
                'reorder_level' => 5,
            ],
            // Cooking Oils
            [
                'name' => 'Gino Cooking Oil 5 Litres',
                'barcode' => '6001099887766',
                'unit' => 'gallon',
                'category_id' => $catOil->id,
                'selling_price' => 195.00,
                'cost_price' => 165.00,
                'quantity' => 25,
                'reorder_level' => 5,
            ],
            [
                'name' => 'Frytol Vegetable Oil 1 Litre',
                'barcode' => '6001099887711',
                'unit' => 'bottle',
                'category_id' => $catOil->id,
                'selling_price' => 42.00,
                'cost_price' => 34.00,
                'quantity' => 4,
                'reorder_level' => 10,
            ],
            // Snacks
            [
                'name' => 'Pringles Original 165g',
                'barcode' => '0038000845352',
                'unit' => 'tin',
                'category_id' => $catSnacks->id,
                'selling_price' => 38.00,
                'cost_price' => 28.00,
                'quantity' => 36,
                'reorder_level' => 12,
            ],
            [
                'name' => 'Ideal Milk Tin 400g',
                'barcode' => '7613035958753',
                'unit' => 'tin',
                'category_id' => $catSnacks->id,
                'selling_price' => 28.00,
                'cost_price' => 21.00,
                'quantity' => 48,
                'reorder_level' => 10,
            ],
            // Household
            [
                'name' => 'Key Soap Bar (Box of 12)',
                'barcode' => '6001068000049',
                'unit' => 'box',
                'category_id' => $catHousehold->id,
                'selling_price' => 60.00,
                'cost_price' => 48.00,
                'quantity' => 15,
                'reorder_level' => 5,
            ],
            // Tobacco
            [
                'name' => 'Supermatch Matchbox (Box of 100)',
                'barcode' => '6009696200018',
                'unit' => 'box',
                'category_id' => $catTobacco->id,
                'selling_price' => 25.00,
                'cost_price' => 18.00,
                'quantity' => 0,
                'reorder_level' => 5,
            ],
        ];

        foreach ($products as $pData) {
            Product::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name'      => $pData['name'],
                ],
                array_merge($pData, [
                    'tenant_id'            => $tenant->id,
                    'shop_id'              => $shop->id,
                    'track_inventory'      => true,
                    'allow_negative_stock' => false,
                    'is_active'            => true,
                    'tax_rate'             => 0,
                ])
            );
        }

        // ─── 7. Customers ─────────────────────────────────────────────────────
        Customer::firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => '0240111222'],
            [
                'shop_id'        => $shop->id,
                'name'           => 'Kofi Kinaata (Wholesale)',
                'credit_limit'   => 2000.00,
                'credit_balance' => 350.00,
            ]
        );
        Customer::firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => '0277333444'],
            [
                'shop_id'        => $shop->id,
                'name'           => 'Auntie Mary',
                'credit_limit'   => 1000.00,
                'credit_balance' => 0,
            ]
        );
        Customer::firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => '0501234567'],
            [
                'shop_id'        => $shop->id,
                'name'           => 'Ebo Wilson (Regular)',
                'credit_limit'   => 500.00,
                'credit_balance' => 0,
            ]
        );
    }
}
