<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reference_groups', function (Blueprint $table): void {
            $table->unique('name');
        });
        Schema::table('reference_items', function (Blueprint $table): void {
            $table->unique(['reference_group_id', 'content']);
            $table->index(['reference_group_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('reference_items', function (Blueprint $table): void {
            $table->dropUnique(['reference_group_id', 'content']);
            $table->dropIndex(['reference_group_id', 'sort_order']);
        });
        Schema::table('reference_groups', function (Blueprint $table): void {
            $table->dropUnique(['name']);
        });
    }
};
