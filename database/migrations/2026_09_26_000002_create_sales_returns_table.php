<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->timestamp('returned_at');
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('posted');
            $table->timestamps();

            $table->index(['sale_id', 'returned_at']);
            $table->index(['customer_id', 'returned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_returns');
    }
};
