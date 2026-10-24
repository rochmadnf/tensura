<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('letter_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('active_version_id')->nullable();
            $table->timestamps();
        });

        Schema::create('letter_template_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('letter_template_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->foreignId('letter_number_id')->index()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('status')->default(1);
            $table->string('source_disk')->nullable();
            $table->string('source_path')->nullable();
            $table->string('source_original_name')->nullable();
            $table->unsignedBigInteger('source_size')->nullable();
            $table->char('source_sha256', 64)->nullable();
            $table->char('validated_source_sha256', 64)->nullable();
            $table->char('validated_configuration_sha256', 64)->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->jsonb('validation_result')->nullable();
            $table->char('tested_source_sha256', 64)->nullable();
            $table->char('tested_configuration_sha256', 64)->nullable();
            $table->string('test_disk')->nullable();
            $table->string('test_path')->nullable();
            $table->timestamp('tested_at')->nullable();
            $table->timestamp('test_expires_at')->nullable()->index();
            $table->timestamp('visually_confirmed_at')->nullable();
            $table->foreignId('visually_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('visually_confirmed_by_uuid')->nullable();
            $table->string('visually_confirmed_by_name')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('first_used_at')->nullable();
            $table->timestamps();
            $table->unique(['letter_template_id', 'version_number'], 'lt_versions_number_unique');
            $table->unique(['id', 'letter_template_id'], 'lt_versions_owner_unique');
        });

        Schema::table('letter_templates', function (Blueprint $table): void {
            $table->foreign(['active_version_id', 'id'], 'lt_active_version_owner_foreign')
                ->references(['id', 'letter_template_id'])->on('letter_template_versions')->restrictOnDelete();
        });

        Schema::create('letter_template_variables', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('version_id')->constrained('letter_template_versions')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('label', 100);
            $table->unsignedSmallInteger('type');
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedSmallInteger('option_source')->nullable();
            $table->foreignId('reference_group_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->boolean('is_multiple')->default(false);
            $table->timestamps();
            $table->unique(['version_id', 'name'], 'lt_variables_name_unique');
            $table->index(['version_id', 'sort_order', 'id'], 'lt_variables_order_index');
        });

        Schema::create('letter_template_variable_columns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('variable_id')->constrained('letter_template_variables')->restrictOnDelete();
            $table->string('source_attribute', 50);
            $table->string('placeholder', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['variable_id', 'placeholder'], 'lt_columns_placeholder_unique');
            $table->index(['variable_id', 'sort_order', 'id'], 'lt_columns_order_index');
        });

        Schema::create('letter_template_signer_slots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('version_id')->constrained('letter_template_versions')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();
            $table->unique(['version_id', 'sort_order'], 'lt_slots_order_unique');
        });

        Schema::create('letter_template_signer_candidates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('slot_id')->constrained('letter_template_signer_slots')->restrictOnDelete();
            $table->foreignId('employee_id')->index()->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['slot_id', 'employee_id'], 'lt_candidates_employee_unique');
        });

        Schema::create('letter_template_audits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('letter_template_id')->constrained()->restrictOnDelete();
            $table->foreignId('version_id')->nullable()->constrained('letter_template_versions')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('actor_uuid');
            $table->string('actor_name');
            $table->timestamp('occurred_at');
            $table->string('operation', 80);
            $table->text('description');
            $table->timestamps();
            $table->index(['letter_template_id', 'occurred_at', 'id'], 'lt_audits_timeline_index');
            $table->index('version_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_template_audits');
        Schema::dropIfExists('letter_template_signer_candidates');
        Schema::dropIfExists('letter_template_signer_slots');
        Schema::dropIfExists('letter_template_variable_columns');
        Schema::dropIfExists('letter_template_variables');
        Schema::table('letter_templates', function (Blueprint $table): void {
            $table->dropForeign('lt_active_version_owner_foreign');
        });
        Schema::dropIfExists('letter_template_versions');
        Schema::dropIfExists('letter_templates');
    }
};
