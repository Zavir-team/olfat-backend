<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();
            $table->string('category', 80);
            $table->string('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->date('expense_date');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['category', 'expense_date']);
            $table->index(['user_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_expenses');
    }
};
