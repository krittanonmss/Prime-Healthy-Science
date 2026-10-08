<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('sku')->unique();
            $t->string('name');
            $t->string('unit');
            $t->unsignedInteger('retail');
            $t->unsignedInteger('wholesale')->nullable();
        });
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone');
            $t->text('address');
            $t->string('channel');
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained();
            $t->string('channel');
            $t->string('salesperson');
            $t->string('status')->default('confirmed');
            $t->unsignedInteger('total');
            $t->unsignedInteger('paid')->default(0);
            $t->timestamps();
        });
        Schema::create('order_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained();
            $t->string('name');
            $t->string('sku');
            $t->unsignedInteger('quantity');
            $t->unsignedInteger('free')->default(0);
            $t->unsignedInteger('price');
            $t->unsignedInteger('total');
        });
    }

    public function down(): void
    {
        foreach (['order_lines', 'orders', 'customers', 'products'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
