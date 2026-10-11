<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damaged_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->string('unit', 30);
            $table->string('reason');
            $table->timestamp('damaged_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['product_variant_id', 'damaged_at']);
            $table->index(['location_id', 'damaged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damaged_stocks');
    }
};
