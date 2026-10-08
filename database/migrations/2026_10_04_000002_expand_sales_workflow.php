<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->unsignedInteger('hospital')->nullable();
            $t->unsignedInteger('pharmacy')->nullable();
        });
        DB::table('products')->update(['hospital' => DB::raw('wholesale')]);
        foreach (['Pro001' => 57000, 'Pro002' => 9000, 'Inno01' => 13700, 'Inno02' => 13700, 'Inno03' => 17000, 'Inno04' => 7500] as $sku => $price) {
            DB::table('products')->where('sku', $sku)->update(['pharmacy' => $price]);
        }
        Schema::table('customers', function (Blueprint $t) {
            $t->string('tax_id', 13)->nullable();
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->date('order_date')->nullable();
            $t->string('sales_code')->default('');
            $t->string('sales_phone')->default('');
            $t->text('customer_snapshot')->nullable();
        });
        Schema::table('order_lines', function (Blueprint $t) {
            $t->unsignedInteger('catalog_price')->nullable();
            $t->text('price_reason')->nullable();
            $t->unsignedInteger('free_box')->default(0);
            $t->unsignedInteger('free_sachet')->default(0);
        });
        DB::table('customers')->where('channel', 'wholesale')->update(['channel' => 'hospital']);
        DB::table('orders')->where('channel', 'wholesale')->update(['channel' => 'hospital']);
        foreach (DB::table('orders')->get() as $o) {
            $c = DB::table('customers')->find($o->customer_id);
            DB::table('orders')->where('id', $o->id)->update(['order_date' => substr($o->created_at, 0, 10), 'customer_snapshot' => json_encode($c, JSON_UNESCAPED_UNICODE)]);
        }
        DB::table('order_lines')->update(['catalog_price' => DB::raw('price'), 'free_box' => DB::raw('free')]);
    }

    public function down(): void
    {
        Schema::table('order_lines', fn (Blueprint $t) => $t->dropColumn(['catalog_price', 'price_reason', 'free_box', 'free_sachet']));
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['order_date', 'sales_code', 'sales_phone', 'customer_snapshot']));
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn('tax_id'));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['hospital', 'pharmacy']));
    }
};
