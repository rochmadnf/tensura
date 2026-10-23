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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->index();
            $table->char('nik', 16)->unique();
            $table->char('nip', 18)->nullable()->unique();
            $table->string('name', 100);
            $table->boolean('gender')->comment('0: Female, 1: Male');
            $table->string('front_title', 50)->nullable();
            $table->string('back_title', 50)->nullable();
            $table->foreignId('job_title_id')->nullable()->constrained('job_titles')->onDelete('set null');
            $table->string('rank', 50)->nullable();
            $table->string('grade', 50)->nullable();
            $table->date('join_date');

            $table->char('str_number', 16)->nullable()->unique();
            $table->unsignedTinyInteger('str_status')->nullable();
            $table->date('str_published_date')->nullable();

            $table->string('sip_number', 30)->nullable()->unique();
            $table->date('sip_published_date')->nullable();
            $table->date('sip_expired_date')->nullable();

            $table->string('email', 100)->nullable()->unique();
            $table->unsignedTinyInteger('contract_status');
            $table->unsignedTinyInteger('status');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
