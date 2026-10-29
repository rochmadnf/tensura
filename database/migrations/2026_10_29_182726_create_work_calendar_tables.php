<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('work_calendar_years', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->integer('year')->unique();
            $t->json('working_weekdays');
            $t->integer('revision')->default(1);
            $t->integer('work_revision')->default(1);
            $t->integer('confirmed_work_revision')->nullable();
            $t->timestamp('confirmed_at')->nullable();
            $t->uuid('confirmed_by_uuid')->nullable();
            $t->string('confirmed_by_name')->nullable();
            $t->timestamps();
        });
        Schema::create('calendar_events', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->integer('revision')->default(1);
            $t->string('title', 150);
            $t->string('kind', 20);
            $t->date('start_date');
            $t->date('end_date');
            $t->boolean('all_day');
            $t->string('start_time', 5)->nullable();
            $t->string('end_time', 5)->nullable();
            $t->string('location')->nullable();
            $t->text('description')->nullable();
            $t->string('origin', 30)->default('manual');
            $t->text('source_url')->nullable();
            $t->timestamp('imported_at')->nullable();
            $t->timestamps();
            $t->index(['start_date', 'end_date']);
        });
        Schema::create('work_calendar_exceptions', function (Blueprint $t): void {
            $t->id();
            $t->date('date')->unique();
            $t->foreignId('calendar_event_id')->constrained('calendar_events')->cascadeOnDelete();
            $t->foreignId('work_calendar_year_id')->constrained('work_calendar_years')->restrictOnDelete();
            $t->string('kind', 20);
        });
        Schema::create('work_calendar_audits', function (Blueprint $t): void {
            $t->id();
            $t->uuid('actor_uuid');
            $t->string('actor_name');
            $t->string('operation');
            $t->json('changes');
            $t->timestamp('occurred_at');
        });
        Schema::create('holiday_import_previews', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->string('token_hash', 64);
            $t->uuid('actor_uuid');
            $t->foreignId('work_calendar_year_id')->constrained('work_calendar_years')->restrictOnDelete();
            $t->integer('calendar_revision');
            $t->json('snapshot');
            $t->timestamp('expires_at');
            $t->json('result')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['holiday_import_previews', 'work_calendar_audits', 'work_calendar_exceptions', 'calendar_events', 'work_calendar_years'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
