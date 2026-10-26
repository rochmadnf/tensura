<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('letter_template_versions', function (Blueprint $table): void {
            $table->char('test_font_fingerprint', 64)->nullable();
            $table->jsonb('test_font_requirements')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('letter_template_versions', function (Blueprint $table): void {
            $table->dropColumn(['test_font_fingerprint', 'test_font_requirements']);
        });
    }
};
