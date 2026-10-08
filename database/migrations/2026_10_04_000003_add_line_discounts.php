<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_lines', function (Blueprint $t) {
            $t->unsignedInteger('discount_basis_points')->default(0);
            $t->unsignedBigInteger('discount_amount')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('order_lines', fn (Blueprint $t) => $t->dropColumn(['discount_basis_points', 'discount_amount']));
    }
};
