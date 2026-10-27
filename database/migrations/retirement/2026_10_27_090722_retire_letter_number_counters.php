<?php

use App\Services\LetterNumber\RetireLetterNumberCounters;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        app(RetireLetterNumberCounters::class)->retire();
    }

    public function down(): void
    {
        throw new RuntimeException('Counter lama tidak dapat direkonstruksi dari counter periode yang sudah berjalan. Gunakan pemulihan backup atau migration perbaikan yang eksplisit.');
    }
};
