<?php

namespace Database\Factories;

use App\Models\Archive;
use App\Models\ArchiveRecord;
use App\Services\Archive\ReferenceSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ArchiveRecord> */
class ArchiveRecordFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $sourceId = fake()->unique()->numberBetween(1, 100000000);
        $sourceUuid = fake()->uuid();
        $name = fake()->words(3, true);

        return [
            'archive_id' => Archive::factory(),
            'entity' => ReferenceSnapshot::GROUP,
            'source_key' => (string) $sourceId,
            'source_uuid' => $sourceUuid,
            'parent_entity' => null,
            'parent_source_key' => null,
            'parent_source_uuid' => null,
            'label' => $name,
            'schema_version' => ReferenceSnapshot::VERSION,
            'payload' => ['id' => $sourceId, 'uuid' => $sourceUuid, 'name' => $name, 'description' => null, 'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString()],
        ];
    }
}
