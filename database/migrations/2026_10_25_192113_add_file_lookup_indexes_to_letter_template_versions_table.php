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
        Schema::table('letter_template_versions', function (Blueprint $table) {
            $table->index(['source_disk', 'source_path'], 'lt_versions_source_file_index');
            $table->index(['test_disk', 'test_path'], 'lt_versions_test_file_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('letter_template_versions', function (Blueprint $table) {
            $table->dropIndex('lt_versions_source_file_index');
            $table->dropIndex('lt_versions_test_file_index');
        });
    }
};
