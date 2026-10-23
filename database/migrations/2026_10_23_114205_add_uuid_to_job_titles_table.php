<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('job_titles', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->index();
        });

        DB::table('job_titles')
            ->whereNull('uuid')
            ->orderBy('id')
            ->chunkById(100, function (Collection $records): void {
                foreach ($records as $record) {
                    DB::table('job_titles')
                        ->where('id', $record->id)
                        ->update([
                            'uuid' => (string) Str::uuid7(),
                        ]);
                }
            });

        Schema::table('job_titles', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_titles', function (Blueprint $table): void {
            $table->dropIndex(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
