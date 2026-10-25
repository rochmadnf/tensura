<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('letter_template_variables', function (Blueprint $table): void {
            $table->text('default_text')->nullable();
        });
        Schema::table('letter_template_variable_columns', function (Blueprint $table): void {
            $table->string('date_format', 100)->nullable();
        });
        Schema::create('letter_template_variable_employee_attributes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('variable_id')->constrained('letter_template_variables')->restrictOnDelete();
            $table->string('source_attribute', 50);
            $table->text('default_text')->nullable();
            $table->string('date_format', 100)->nullable();
            $table->timestamps();
            $table->unique(['variable_id', 'source_attribute'], 'lt_variable_employee_attribute_unique');
        });
        Schema::create('letter_template_signer_employee_attributes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('slot_id')->constrained('letter_template_signer_slots')->restrictOnDelete();
            $table->string('source_attribute', 50);
            $table->text('default_text')->nullable();
            $table->string('date_format', 100)->nullable();
            $table->timestamps();
            $table->unique(['slot_id', 'source_attribute'], 'lt_signer_employee_attribute_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_template_signer_employee_attributes');
        Schema::dropIfExists('letter_template_variable_employee_attributes');
        Schema::table('letter_template_variable_columns', function (Blueprint $table): void {
            $table->dropColumn('date_format');
        });
        Schema::table('letter_template_variables', function (Blueprint $table): void {
            $table->dropColumn('default_text');
        });
    }
};
