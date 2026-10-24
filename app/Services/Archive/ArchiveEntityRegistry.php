<?php

namespace App\Services\Archive;

use InvalidArgumentException;

class ArchiveEntityRegistry
{
    public function __construct(private ReferenceSnapshot $referenceSnapshot) {}

    public function handler(string $entity): SnapshotHandler
    {
        return match ($entity) {
            ReferenceSnapshot::GROUP, ReferenceSnapshot::ITEM => $this->referenceSnapshot,
            default => throw new InvalidArgumentException('Entitas arsip belum didukung.'),
        };
    }
}
