<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_fonts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('family');
            $table->string('style');
            $table->char('face_key', 64)->unique();
            $table->boolean('is_bold');
            $table->boolean('is_italic');
            $table->char('sha256', 64)->unique();
            $table->string('status', 16);
            $table->string('disk');
            $table->string('path');
            $table->string('extension', 3);
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_fonts');
    }
};
