<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('archives', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('module', 50);
            $table->string('kind', 20);
            $table->string('entity', 80);
            $table->string('label');
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('archived_by_name');
            $table->timestamp('archived_at');
            $table->unsignedBigInteger('revision')->default(1);
            $table->jsonb('context');
            $table->timestamps();
            $table->index(['archived_at', 'id']);
            $table->index(['module', 'archived_at', 'id']);
        });
        Schema::create('archive_records', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('archive_id')->constrained('archives')->cascadeOnDelete();
            $table->string('entity', 80);
            $table->string('source_key', 100);
            $table->uuid('source_uuid')->nullable();
            $table->string('parent_entity', 80)->nullable();
            $table->string('parent_source_key', 100)->nullable();
            $table->uuid('parent_source_uuid')->nullable();
            $table->string('label');
            $table->unsignedInteger('schema_version');
            $table->jsonb('payload');
            $table->timestamps();
            $table->unique(['archive_id', 'entity', 'source_key'], 'archive_records_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_records');
        Schema::dropIfExists('archives');
    }
};
