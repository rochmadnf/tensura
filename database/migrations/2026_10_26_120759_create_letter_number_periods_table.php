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
        Schema::create('letter_number_periods', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('letter_number_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->integer('last_page_number')->nullable();
            $table->unsignedTinyInteger('last_sequence_number')->nullable();
            $table->timestamps();
            $table->unique(['letter_number_id', 'year', 'month']);
        });
        DB::statement('ALTER TABLE letter_number_periods ADD CONSTRAINT letter_number_periods_valid_position CHECK (year BETWEEN 1 AND 9999 AND month BETWEEN 1 AND 12 AND ((last_page_number IS NULL AND last_sequence_number IS NULL) OR (last_page_number IS NOT NULL AND last_sequence_number IS NOT NULL AND last_page_number >= 1 AND last_sequence_number BETWEEN 1 AND 99)))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letter_number_periods');
    }
};
