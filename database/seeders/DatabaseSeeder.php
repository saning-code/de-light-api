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
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ─── 0. Super Admin ───────────────────────────────────────────────────
        try {
            SuperAdmin::updateOrCreate(
                ['email' => 'admin@delight.app'],
                [
                    'name'      => 'De-Light Super Admin',
                    'password'  => Hash::make('DeLight@Admin2024!'),
                    'role'      => 'super_admin',
                    'is_active' => true,
                ]
            );
            echo "✅ Super admin seeded\n";
        } catch (\Exception $e) { echo "⚠️ Super admin: {$e->getMessage()}\n"; }

        // ─── 1. Subscription Plans ────────────────────────────────────────────
        try {
            $basic = SubscriptionPlan::updateOrCreate(
                ['slug' => 'basic'],
                [
                    'name'                => 'Basic Shop',
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

            SubscriptionPlan::updateOrCreate(
                ['slug' => 'premium'],
                [
                    'name'                => 'Enterprise Multi-Shop',
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
            echo "✅ Plans seeded\n";
        } catch (\Exception $e) { echo "⚠️ Plans: {$e->getMessage()}\n"; }

        // ─── 2. Demo Tenant ───────────────────────────────────────────────────
        try {
            $basic = SubscriptionPlan::where('slug', 'basic')->first();
            $tenant = Tenant::updateOrCreate(
                ['owner_email' => 'kwame@delight.com'],
                [
                    'business_name'        => 'De-Light Drinks & Provisions Store',
                    'business_type'        => 'provisions',
                    'owner_name'           => 'Kwame Mensah',
                    'owner_phone'          => '0244123456',
                    'city'                 => 'Accra',
                    'region'               => 'Greater Accra',
                    'status'               => 'active',
                    'subscription_plan_id' => $basic?->id,
                    'currency'             => 'GHS',
                    'currency_symbol'      => 'GH₵',
                    'trial_ends_at'        => Carbon::now()->addDays(30),
                ]
            );
            echo "✅ Tenant seeded: {$tenant->business_name} (ID: {$tenant->id})\n";
        } catch (\Exception $e) {
            echo "⚠️ Tenant: {$e->getMessage()}\n";
            return; // Can't continue without tenant
        }

        // ─── 3. Demo Shop ─────────────────────────────────────────────────────
        try {
            $shop = Shop::updateOrCreate(
                ['tenant_id' => $tenant->id, 'is_primary' => true],
                [
                    'tenant_id'  => $tenant->id,
                    'name'       => 'De-Light Main Store (Osu)',
                    'type'       => 'provisions',
                    'phone'      => '0244123456',
                    'address'    => 'Osu Oxford Street, Accra',
                    'city'       => 'Accra',
                    'is_primary' => true,
                    'is_active'  => true,
                ]
            );
            echo "✅ Shop seeded: {$shop->name} (ID: {$shop->id})\n";
        } catch (\Exception $e) {
            echo "⚠️ Shop: {$e->getMessage()}\n";
            $shop = Shop::where('tenant_id', $tenant->id)->first();
            if (!$shop) return;
        }

        // ─── 4. Users ─────────────────────────────────────────────────────────
        try {
            User::updateOrCreate(
                ['email' => 'owner@delight.com'],
                [
                    'tenant_id'            => $tenant->id,
                    'shop_id'              => $shop->id,
                    'name'                 => 'Kwame Mensah',
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
            echo "✅ Owner user seeded\n";
        } catch (\Exception $e) { echo "⚠️ Owner user: {$e->getMessage()}\n"; }

        try {
            User::updateOrCreate(
                ['email' => 'cashier@delight.com'],
                [
                    'tenant_id'            => $tenant->id,
                    'shop_id'              => $shop->id,
                    'name'                 => 'Ama Serwaa',
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
            echo "✅ Cashier user seeded\n";
        } catch (\Exception $e) { echo "⚠️ Cashier user: {$e->getMessage()}\n"; }

        // ─── 5. Categories ────────────────────────────────────────────────────
        $cats = [];
        $catData = [
            'drinks'    => ['Soft Drinks & Juices', '#2563EB', 'local_drink'],
            'beer'      => ['Beers & Stout', '#D97706', 'sports_bar'],
            'rice'      => ['Rice & Grains', '#059669', 'shopping_bag'],
            'oil'       => ['Cooking Oils', '#7C3AED', 'oil_barrel'],
            'snacks'    => ['Snacks & Confectionery', '#DB2777', 'cookie'],
            'household' => ['Household & Cleaning', '#0891B2', 'cleaning_services'],
            'tobacco'   => ['Tobacco & Matches', '#64748B', 'smoke_free'],
        ];

        foreach ($catData as $key => [$name, $color, $icon]) {
            try {
                $cats[$key] = Category::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $name],
                    ['shop_id' => $shop->id, 'color' => $color, 'icon' => $icon]
                );
            } catch (\Exception $e) { echo "⚠️ Category {$name}: {$e->getMessage()}\n"; }
        }
        echo "✅ Categories seeded: " . count($cats) . "\n";

        // ─── 6. Products (15 products) ────────────────────────────────────────
        $products = [
            ['name'=>'Coca Cola 50cl (Crate of 24)','barcode'=>'0544900000099','cat'=>'drinks','unit'=>'crate','sell'=>120,'cost'=>95,'qty'=>50,'reorder'=>10],
            ['name'=>'Maltina Can 33cl (Pack of 24)','barcode'=>'6001050001234','cat'=>'drinks','unit'=>'pack','sell'=>150,'cost'=>125,'qty'=>30,'reorder'=>5],
            ['name'=>'Alvaro Pineapple 330ml (x24)','barcode'=>'5449000024511','cat'=>'drinks','unit'=>'pack','sell'=>135,'cost'=>108,'qty'=>20,'reorder'=>5],
            ['name'=>'Ice Chilled Water 500ml (x24)','barcode'=>'6009696000016','cat'=>'drinks','unit'=>'carton','sell'=>42,'cost'=>32,'qty'=>60,'reorder'=>10],
            ['name'=>'Club Premium Lager 62cl','barcode'=>'6001089000112','cat'=>'beer','unit'=>'bottle','sell'=>18,'cost'=>13.50,'qty'=>120,'reorder'=>24],
            ['name'=>'Guinness Foreign Extra Stout','barcode'=>'6001089000999','cat'=>'beer','unit'=>'bottle','sell'=>20,'cost'=>15,'qty'=>80,'reorder'=>20],
            ['name'=>'Star Lager Beer 33cl Can','barcode'=>'6001089001001','cat'=>'beer','unit'=>'can','sell'=>12,'cost'=>9,'qty'=>3,'reorder'=>24],
            ['name'=>'Royal Aroma Perfumed Rice 5KG','barcode'=>'8935001234567','cat'=>'rice','unit'=>'bag','sell'=>165,'cost'=>135,'qty'=>45,'reorder'=>8],
            ['name'=>'Abeiku Broken Rice 10KG','barcode'=>'6009696100019','cat'=>'rice','unit'=>'bag','sell'=>110,'cost'=>88,'qty'=>22,'reorder'=>5],
            ['name'=>'Gino Cooking Oil 5 Litres','barcode'=>'6001099887766','cat'=>'oil','unit'=>'gallon','sell'=>195,'cost'=>165,'qty'=>25,'reorder'=>5],
            ['name'=>'Frytol Vegetable Oil 1 Litre','barcode'=>'6001099887711','cat'=>'oil','unit'=>'bottle','sell'=>42,'cost'=>34,'qty'=>4,'reorder'=>10],
            ['name'=>'Pringles Original 165g','barcode'=>'0038000845352','cat'=>'snacks','unit'=>'tin','sell'=>38,'cost'=>28,'qty'=>36,'reorder'=>12],
            ['name'=>'Ideal Milk Tin 400g','barcode'=>'7613035958753','cat'=>'snacks','unit'=>'tin','sell'=>28,'cost'=>21,'qty'=>48,'reorder'=>10],
            ['name'=>'Key Soap Bar (Box of 12)','barcode'=>'6001068000049','cat'=>'household','unit'=>'box','sell'=>60,'cost'=>48,'qty'=>15,'reorder'=>5],
            ['name'=>'Supermatch Matchbox (Box of 100)','barcode'=>'6009696200018','cat'=>'tobacco','unit'=>'box','sell'=>25,'cost'=>18,'qty'=>0,'reorder'=>5],
        ];

        $seeded = 0;
        foreach ($products as $p) {
            try {
                Product::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $p['name']],
                    [
                        'tenant_id'       => $tenant->id,
                        'shop_id'         => $shop->id,
                        'category_id'     => $cats[$p['cat']]?->id ?? null,
                        'barcode'         => $p['barcode'],
                        'unit'            => $p['unit'],
                        'selling_price'   => $p['sell'],
                        'cost_price'      => $p['cost'],
                        'quantity'        => $p['qty'],
                        'reorder_level'   => $p['reorder'],
                        'track_inventory' => true,
                        'is_active'       => true,
                        'tax_rate'        => 0,
                    ]
                );
                $seeded++;
            } catch (\Exception $e) { echo "⚠️ Product {$p['name']}: {$e->getMessage()}\n"; }
        }
        echo "✅ Products seeded: $seeded\n";

        // ─── 7. Customers ─────────────────────────────────────────────────────
        $customers = [
            ['phone'=>'0240111222','name'=>'Kofi Kinaata (Wholesale)','limit'=>2000,'balance'=>350],
            ['phone'=>'0277333444','name'=>'Auntie Mary','limit'=>1000,'balance'=>0],
            ['phone'=>'0501234567','name'=>'Ebo Wilson (Regular)','limit'=>500,'balance'=>0],
        ];

        foreach ($customers as $c) {
            try {
                Customer::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'phone' => $c['phone']],
                    ['shop_id'=>$shop->id,'name'=>$c['name'],'credit_limit'=>$c['limit'],'credit_balance'=>$c['balance']]
                );
            } catch (\Exception $e) { echo "⚠️ Customer {$c['name']}: {$e->getMessage()}\n"; }
        }
        echo "✅ Customers seeded\n";
        echo "🎉 Seeder complete!\n";
    }
}
