<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('letters', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('subject');
            $table->foreignId('letter_template_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('creator_uuid');
            $table->string('creator_name');
            $table->smallInteger('status')->default(1);
            $table->string('official_number')->nullable();
            $table->timestamps();
            $table->index(['updated_at', 'id']);
            $table->index(['letter_template_id', 'updated_at', 'id']);
            $table->index(['creator_uuid', 'updated_at', 'id']);
            $table->index(['status', 'updated_at', 'id']);
        });
        DB::statement('ALTER TABLE letters ADD CONSTRAINT letters_status_check CHECK (status IN (1, 2, 3))');
    }

    public function down(): void
    {
        Schema::dropIfExists('letters');
    }
};
