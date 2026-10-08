<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Pro001', 'โปรไฟน์ เบอร์รี่ / Profine Berry', 'box', 112500, 57000],
            ['Pro002', 'โปรไฟน์ เบอร์รี่ / Profine Sachet', 'sachet', 12000, 9000],
            ['Inno01', 'โฟมสีฟัน / Innoage Foam 50 ml', 'bottle', 18000, 11600],
            ['Inno02', 'ยาสีฟัน / Innoage Toothpaste 120 g', 'tube', 18000, 11600],
            ['Inno03', 'น้ำยาบ้วนปาก / Innoage Mouthwash 500 ml', 'bottle', 22000, 15500],
            ['Inno04', 'แปรงสีฟัน / Innoage Suction Brush', 'piece', 10000, 5400],
            ['Inno05', 'แปรงสีฟัน / Innoage Slimmax Brush', 'piece', 7500, null],
        ] as [$sku,$name,$unit,$retail,$wholesale]) {
            DB::table('products')->insert(compact('sku', 'name', 'unit', 'retail', 'wholesale') + ['hospital' => $wholesale, 'pharmacy' => ['Pro001' => 57000, 'Pro002' => 9000, 'Inno01' => 13700, 'Inno02' => 13700, 'Inno03' => 17000, 'Inno04' => 7500][$sku] ?? null]);
        }
        foreach ([
            ['Siam Wellness Clinic (Demo)', '02-000-0101', 'Bangkok, Thailand', 'hospital'],
            ['Narin Customer (Demo)', '080-000-0102', 'Chiang Mai, Thailand', 'retail'],
            ['Healthy Pharmacy (Demo)', '02-000-0103', 'Nonthaburi, Thailand', 'pharmacy'],
        ] as [$name,$phone,$address,$channel]) {
            DB::table('customers')->insert(compact('name', 'phone', 'address', 'channel'));
        }
        foreach ([[1, 'hospital', 1, 12, 2, 'confirmed', 0], [2, 'retail', 3, 4, 0, 'packing', 72000], [3, 'pharmacy', 5, 10, 1, 'dispatched', 100000], [2, 'retail', 1, 2, 0, 'delivered', 225000]] as [$customer,$channel,$product,$quantity,$free,$status,$paid]) {
            $p = DB::table('products')->find($product);
            $price = $p->$channel;
            $total = $price * $quantity;
            $id = DB::table('orders')->insertGetId(['customer_id' => $customer, 'channel' => $channel, 'salesperson' => 'Demo Sales Team', 'status' => $status, 'total' => $total, 'paid' => $paid, 'order_date' => now()->toDateString(), 'customer_snapshot' => json_encode(DB::table('customers')->find($customer)), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('order_lines')->insert(['order_id' => $id, 'product_id' => $product, 'name' => $p->name, 'sku' => $p->sku, 'quantity' => $quantity, 'free' => $free, 'free_box' => $free, 'catalog_price' => $price, 'price' => $price, 'total' => $total]);
        }
    }
}
