<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40); // purchase, sale, sale_return, damage, adjustment, transfer_in, transfer_out
            $table->decimal('quantity_change', 15, 4); // signed, in base_unit
            $table->decimal('quantity_after', 15, 4);
            $table->string('unit', 30); // unit recorded on the transaction
            $table->decimal('quantity_in_unit', 15, 4); // absolute qty in that unit
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['product_variant_id', 'location_id']);
            $table->index(['type', 'occurred_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
