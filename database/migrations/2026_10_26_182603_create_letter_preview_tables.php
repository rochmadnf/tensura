<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('letter_operations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('letter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('letter_draft_id')->constrained()->cascadeOnDelete();
            $table->uuid('actor_uuid');
            $table->string('actor_name');
            $table->string('kind', 30)->default('preview');
            $table->uuid('idempotency_key');
            $table->char('request_hash', 64);
            $table->unsignedInteger('draft_revision');
            $table->smallInteger('status')->default(1);
            $table->unsignedInteger('status_revision')->default(1);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->uuid('lease_token')->nullable();
            $table->timestampTz('lease_expires_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->string('message')->nullable();
            $table->jsonb('payload');
            $table->timestampsTz();
            $table->unique(['actor_uuid', 'idempotency_key']);
            $table->index(['letter_id', 'draft_revision', 'status']);
            $table->index(['actor_uuid', 'updated_at']);
            $table->index(['status', 'lease_expires_at']);
        });
        Schema::create('letter_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('letter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('letter_draft_id')->constrained()->cascadeOnDelete();
            $table->foreignId('letter_operation_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->default('docx');
            $table->unsignedInteger('draft_revision');
            $table->char('state_fingerprint', 64);
            $table->string('disk');
            $table->string('path');
            $table->char('sha256', 64);
            $table->unsignedBigInteger('size');
            $table->char('font_fingerprint', 64);
            $table->jsonb('font_usage');
            $table->timestampTz('expires_at')->index();
            $table->timestampsTz();
            $table->index(['letter_id', 'draft_revision', 'id']);
        });
        Schema::create('letter_preview_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('letter_artifact_id')->constrained()->cascadeOnDelete();
            $table->uuid('actor_uuid');
            $table->string('actor_name');
            $table->string('preview_kind', 10)->default('docx');
            $table->char('fingerprint', 64);
            $table->timestampTz('rendered_at');
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['letter_artifact_id', 'actor_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_preview_checks');
        Schema::dropIfExists('letter_artifacts');
        Schema::dropIfExists('letter_operations');
    }
};
