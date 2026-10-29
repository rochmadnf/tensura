<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('letter_template_variables', function (Blueprint $table): void {
            $table->json('radio')->nullable();
            $table->json('numeric')->nullable();
            $table->json('date_range')->nullable();
        });
        Schema::create('letter_template_radio_options', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('variable_id')->constrained('letter_template_variables')->cascadeOnDelete();
            $table->string('code', 100);
            $table->string('label', 100);
            $table->integer('sort_order');
            $table->timestamps();
            $table->unique(['variable_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_template_radio_options');
        Schema::table('letter_template_variables', fn(Blueprint $table) => $table->dropColumn(['radio', 'numeric', 'date_range']));
    }
};
