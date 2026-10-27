<?php

namespace App\Console\Commands;

use App\Services\Letter\LetterHealth;
use Illuminate\Console\Command;

class CheckLettersHealth extends Command
{
    protected $signature = 'letters:health {--json : Keluarkan hasil JSON}';

    protected $description = 'Check letter runtime, converter, database notifications, worker and scheduler readiness';

    public function handle(LetterHealth $health): int
    {
        $checks = $health->check();
        if ($this->option('json')) {
            $this->line(json_encode($checks, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        } else {
            foreach ($checks as $name => $check) {
                $this->line(($check['ok'] ? '[OK] ' : '[FAIL] ') . $name . ': ' . $check['message']);
            }
        }

        return collect($checks)->every(fn(array $check): bool => $check['ok']) ? self::SUCCESS : self::FAILURE;
    }
}
