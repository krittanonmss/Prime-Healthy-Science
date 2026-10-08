<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->unsignedInteger('stock_on_hand')->default(0);
            $t->unsignedInteger('stock_reserved')->default(0);
            $t->unsignedInteger('stock_minimum')->default(0);
            $t->boolean('stock_initialized')->default(false);
        });
        Schema::table('orders', fn (Blueprint $t) => $t->boolean('stock_managed')->default(false));
        Schema::table('order_lines', fn (Blueprint $t) => $t->string('unit')->nullable());
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained();
            $t->foreignId('order_id')->nullable()->constrained();
            $t->string('type');
            $t->integer('quantity');
            $t->integer('balance');
            $t->date('movement_date');
            $t->text('reason');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::table('order_lines', fn (Blueprint $t) => $t->dropColumn('unit'));
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('stock_managed'));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['stock_on_hand', 'stock_reserved', 'stock_minimum', 'stock_initialized']));
    }
};
