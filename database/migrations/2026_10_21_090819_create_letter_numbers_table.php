<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('letter_numbers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->index();

            $table->string('classification_name', 100);
            $table->char('classification_code', 3)->unique();

            $table->unsignedTinyInteger('page_number');
            $table->unsignedTinyInteger('sequence_number');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letter_numbers');
    }
};
