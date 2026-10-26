<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('letter_artifacts', function (Blueprint $table): void {
            $table->foreignId('source_artifact_id')->nullable()->constrained('letter_artifacts')->cascadeOnDelete();
            $table->index(['source_artifact_id', 'kind']);
        });
        Schema::table('letter_operations', function (Blueprint $table): void {
            $table->foreignId('source_artifact_id')->nullable()->constrained('letter_artifacts')->nullOnDelete();
            $table->index(['source_artifact_id', 'kind', 'status']);
        });
        Schema::create('letter_pdf_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('letter_operation_id')->constrained()->cascadeOnDelete();
            $table->uuid('actor_uuid');
            $table->uuid('idempotency_key');
            $table->char('request_hash', 64);
            $table->timestampsTz();
            $table->unique(['actor_uuid', 'idempotency_key']);
            $table->index(['letter_operation_id', 'actor_uuid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letter_pdf_requests');
        Schema::table('letter_operations', function (Blueprint $table): void {
            $table->dropIndex(['source_artifact_id', 'kind', 'status']);
            $table->dropConstrainedForeignId('source_artifact_id');
        });
        Schema::table('letter_artifacts', function (Blueprint $table): void {
            $table->dropIndex(['source_artifact_id', 'kind']);
            $table->dropConstrainedForeignId('source_artifact_id');
        });
    }
};
