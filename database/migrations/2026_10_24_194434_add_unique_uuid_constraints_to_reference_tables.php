<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['reference_groups', 'reference_items'] as $tableName) {
            if (DB::table($tableName)->count() !== DB::table($tableName)->distinct()->count('uuid')) {
                throw new RuntimeException("UUID duplikat pada {$tableName}. Selesaikan konflik sebelum migrasi.");
            }
        }
        foreach (['reference_groups', 'reference_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropIndex(['uuid']);
                $table->unique('uuid');
            });
        }
    }

    public function down(): void
    {
        foreach (['reference_groups', 'reference_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropUnique(['uuid']);
                $table->index('uuid');
            });
        }
    }
};
