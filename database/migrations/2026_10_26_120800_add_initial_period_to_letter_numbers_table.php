<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('letter_numbers', function (Blueprint $table) {
            $table->unsignedTinyInteger('page_number')->nullable()->change();
            $table->unsignedTinyInteger('sequence_number')->nullable()->change();
            $table->foreignId('initial_period_id')->nullable()->constrained('letter_number_periods')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('letter_numbers')->whereNull('page_number')->orWhereNull('sequence_number')->exists()) {
            throw new RuntimeException('Counter lama kosong pada klasifikasi baru. Rollback membutuhkan penanganan eksplisit agar angka tidak ditebak.');
        }
        Schema::table('letter_numbers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('initial_period_id');
            $table->unsignedTinyInteger('page_number')->nullable(false)->change();
            $table->unsignedTinyInteger('sequence_number')->nullable(false)->change();
        });
    }
};
