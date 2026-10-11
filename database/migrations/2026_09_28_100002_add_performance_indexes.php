<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->index('balance');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->index('balance');
        });

        Schema::table('stock_balances', function (Blueprint $table) {
            $table->index(['location_id', 'quantity']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->index(['location_id', 'payment_type', 'sold_at']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['balance']);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex(['balance']);
        });

        Schema::table('stock_balances', function (Blueprint $table) {
            $table->dropIndex(['location_id', 'quantity']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['location_id', 'payment_type', 'sold_at']);
        });
    }
};
