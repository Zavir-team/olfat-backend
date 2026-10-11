<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('location_id')
                ->nullable()
                ->after('role')
                ->constrained('locations')
                ->nullOnDelete();
            $table->unique('location_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['location_id']);
            $table->dropConstrainedForeignId('location_id');
        });
    }
};
