<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('letter_drafts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('letter_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('template_version_id')->constrained('letter_template_versions')->restrictOnDelete();
            $table->smallInteger('kind')->default(1);
            $table->date('letter_date');
            $table->smallInteger('numbering_mode')->default(1);
            $table->smallInteger('number_year');
            $table->smallInteger('number_month');
            $table->integer('page_number')->nullable();
            $table->smallInteger('sequence_number')->nullable();
            $table->integer('revision')->default(1);
            $table->jsonb('form_data');
            $table->jsonb('snapshot');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('updater_uuid');
            $table->string('updater_name');
            $table->timestamps();
            $table->index(['number_year', 'number_month', 'letter_id']);
        });
        DB::statement('ALTER TABLE letter_drafts ADD CONSTRAINT letter_drafts_state_check CHECK (kind IN (1, 2, 3) AND revision > 0 AND number_year BETWEEN 1 AND 9999 AND number_month BETWEEN 1 AND 12 AND EXTRACT(YEAR FROM letter_date) = number_year AND EXTRACT(MONTH FROM letter_date) = number_month)');
        DB::statement('ALTER TABLE letter_drafts ADD CONSTRAINT letter_drafts_position_check CHECK ((numbering_mode = 1 AND page_number IS NULL AND sequence_number IS NULL) OR (numbering_mode = 2 AND page_number > 0 AND sequence_number BETWEEN 1 AND 99 AND page_number IS NOT NULL AND sequence_number IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_drafts');
    }
};
