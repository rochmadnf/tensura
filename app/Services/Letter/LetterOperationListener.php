<?php

namespace App\Services\Letter;

use Illuminate\Support\Facades\DB;
use PDO;
use Pdo\Pgsql;
use RuntimeException;

class LetterOperationListener
{
    private ?PDO $listener = null;

    private ?string $connectionName = null;

    public function listen(?string $channel = null): void
    {
        $connection = DB::connection();
        $configuration = $connection->getConfig();
        if (($configuration['driver'] ?? null) !== 'pgsql' || ($configuration['pooled'] ?? false)) {
            throw new RuntimeException('SSE Surat memerlukan koneksi PostgreSQL langsung tanpa transaction pooling.');
        }
        unset($configuration['name'], $configuration['read'], $configuration['write']);
        $configuration['options'][PDO::ATTR_PERSISTENT] = false;
        $configuration['name'] = 'letter_operations_listener';
        $this->connectionName = $configuration['name'];
        $this->listener = DB::build($configuration)->getPdo();
        if (!$this->listener instanceof Pgsql) {
            throw new RuntimeException('SSE Surat memerlukan driver PDO PostgreSQL PHP 8.4.');
        }
        $channel ??= config('letters.sse.channel');
        if (!preg_match('/\A[a-z_][a-z0-9_]*\z/D', $channel)) {
            throw new RuntimeException('Invalid operation notification channel.');
        }
        $this->listener->exec('LISTEN ' . $channel);
    }

    public function wait(int $milliseconds): ?string
    {
        if (!$this->listener instanceof Pgsql) {
            throw new RuntimeException('Listener not connected.');
        }
        $event = $this->listener->getNotify(PDO::FETCH_ASSOC, $milliseconds);

        return $event === false ? null : $event['payload'];
    }

    public function probe(): void
    {
        $channel = 'letter_health_' . bin2hex(random_bytes(8));
        try {
            $this->listen($channel);
            $statement = $this->listener->prepare('select pg_notify(?, ?)');
            $statement->execute([$channel, 'health']);
            if ($this->wait(1000) !== 'health') {
                throw new RuntimeException('Probe LISTEN/NOTIFY belum berhasil.');
            }
        } finally {
            $this->close();
        }
    }

    public function close(): void
    {
        $this->listener = null;
        if ($this->connectionName !== null) {
            DB::purge($this->connectionName);
            $this->connectionName = null;
        }
    }
}
