<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('letter_audits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('letter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('actor_uuid');
            $table->string('actor_name');
            $table->string('event', 64);
            $table->string('description');
            $table->integer('draft_revision');
            $table->jsonb('changes');
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['letter_id', 'occurred_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_audits');
    }
};
