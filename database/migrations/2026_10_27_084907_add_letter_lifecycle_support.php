<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('letter_drafts', function (Blueprint $table): void {
            $table->foreignId('base_version_id')->nullable()->constrained('letter_versions')->restrictOnDelete();
            $table->string('subject')->nullable();
            $table->text('reason')->nullable();
        });
        Schema::table('letter_versions', function (Blueprint $table): void {
            $table->text('reason')->nullable();
        });
        Schema::table('letters', function (Blueprint $table): void {
            $table->timestampTz('cancelled_at')->nullable();
            $table->uuid('cancelled_by_uuid')->nullable();
            $table->string('cancelled_by_name')->nullable();
            $table->text('cancellation_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('letters', fn(Blueprint $table) => $table->dropColumn(['cancelled_at', 'cancelled_by_uuid', 'cancelled_by_name', 'cancellation_reason']));
        Schema::table('letter_versions', fn(Blueprint $table) => $table->dropColumn('reason'));
        Schema::table('letter_drafts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('base_version_id');
            $table->dropColumn(['subject', 'reason']);
        });
    }
};
