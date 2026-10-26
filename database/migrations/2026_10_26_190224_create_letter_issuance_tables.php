<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('letter_number_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('letter_id')->constrained()->restrictOnDelete();
            $table->foreignId('letter_number_period_id')->constrained()->restrictOnDelete();
            $table->integer('page_number');
            $table->smallInteger('sequence_number');
            $table->smallInteger('mode');
            $table->timestampTz('claimed_at');
            $table->timestampTz('released_at')->nullable();
            $table->timestampsTz();
            $table->index(['letter_number_period_id', 'page_number', 'sequence_number']);
        });
        /** PostgreSQL partial uniqueness preserves released claims while preventing active collisions. */
        DB::statement('CREATE UNIQUE INDEX letter_number_active_position_unique ON letter_number_assignments (letter_number_period_id, page_number, sequence_number) WHERE released_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX letter_number_active_letter_unique ON letter_number_assignments (letter_id) WHERE released_at IS NULL');
        DB::statement('ALTER TABLE letter_number_assignments ADD CONSTRAINT letter_number_assignment_position_check CHECK (page_number > 0 AND sequence_number BETWEEN 1 AND 99 AND mode IN (1, 2, 3))');
        Schema::create('letter_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('letter_id')->constrained()->restrictOnDelete();
            $table->integer('version_number');
            $table->foreignId('template_version_id')->constrained('letter_template_versions')->restrictOnDelete();
            $table->foreignId('letter_number_assignment_id')->constrained()->restrictOnDelete();
            $table->foreignId('letter_operation_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('word_artifact_id')->nullable()->constrained('letter_artifacts')->restrictOnDelete();
            $table->string('subject');
            $table->string('number');
            $table->date('letter_date');
            $table->smallInteger('number_year');
            $table->smallInteger('number_month');
            $table->jsonb('snapshot');
            $table->jsonb('inspection');
            $table->uuid('actor_uuid');
            $table->string('actor_name');
            $table->timestampTz('issued_at');
            $table->timestampsTz();
            $table->unique(['letter_id', 'version_number']);
            $table->index(['number_year', 'number_month', 'letter_id']);
        });
        Schema::table('letter_artifacts', function (Blueprint $table): void {
            $table->foreignId('letter_draft_id')->nullable()->change();
            $table->timestampTz('expires_at')->nullable()->change();
            $table->foreignId('letter_version_id')->nullable()->constrained()->restrictOnDelete();
            $table->unique(['letter_version_id', 'kind']);
        });
        Schema::table('letter_operations', function (Blueprint $table): void {
            $table->dropForeign(['letter_draft_id']);
            $table->foreignId('letter_draft_id')->nullable()->change();
            $table->foreign('letter_draft_id')->references('id')->on('letter_drafts')->nullOnDelete();
        });
        Schema::table('letters', function (Blueprint $table): void {
            $table->foreignId('current_version_id')->nullable()->constrained('letter_versions')->restrictOnDelete();
            $table->date('official_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('letters', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_version_id');
            $table->dropColumn('official_date');
        });
        Schema::table('letter_artifacts', function (Blueprint $table): void {
            $table->dropUnique(['letter_version_id', 'kind']);
            $table->dropConstrainedForeignId('letter_version_id');
        });
        Schema::dropIfExists('letter_versions');
        Schema::dropIfExists('letter_number_assignments');
    }
};
