<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('letter_template_versions', function (Blueprint $table): void {
            $table->string('letter_date_format', 100)->default('d-m-Y');
        });
    }

    public function down(): void
    {
        Schema::table('letter_template_versions', function (Blueprint $table): void {
            $table->dropColumn('letter_date_format');
        });
    }
};
