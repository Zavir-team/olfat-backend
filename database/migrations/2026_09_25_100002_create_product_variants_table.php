<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->nullable()->unique();
            $table->string('base_unit', 30); // kg, piece, bag, carton, bottle, liter, drum, package
            $table->decimal('package_qty', 15, 4)->nullable(); // e.g. 20 for 20kg bag; OPEN QUESTION on pricing rules
            $table->string('package_unit', 30)->nullable(); // unit that package_qty refers to when different from sale unit
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'name']);
            $table->index('base_unit');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
