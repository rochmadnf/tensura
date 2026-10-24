<?php

namespace Tests\Feature;

use App\Enums\ArchiveKindEnum;
use App\Models\Archive;
use App\Models\ArchiveRecord;
use App\Models\MasterData\ReferenceGroup;
use App\Models\MasterData\ReferenceItem;
use App\Services\Archive\ArchiveEntityRegistry;
use App\Services\Archive\ReferenceSnapshot;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArchiveStorageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_snapshot_keeps_original_columns_and_parent_without_serializing_relations(): void
    {
        $group = ReferenceGroup::factory()->create(['description' => 'Contoh referensi']);
        $item = ReferenceItem::factory()->for($group, 'group')->create(['sort_order' => 7]);
        $group->load('items');
        $handler = app(ReferenceSnapshot::class);
        $archive = Archive::factory()->create(['context' => ['parent' => ['uuid' => $group->uuid], 'target' => null]]);
        $parent = $archive->records()->create($handler->snapshot($group));
        $child = $archive->records()->create($handler->snapshot($item));

        $this->assertSame(ArchiveKindEnum::PACKAGE, $archive->fresh()->kind);
        $this->assertEquals($group->getAttributes(), $parent->fresh()->payload);
        $this->assertEquals($item->getAttributes(), $child->fresh()->payload);
        $this->assertSame($group->uuid, $child->parent_source_uuid);
        $this->assertNotSame($item->uuid, $child->uuid);
        $this->assertTrue($handler->supports($parent));
        $this->assertTrue($handler->supports($child));
        $parent->delete();
        $this->assertModelExists($child);
        $this->assertSame($group->uuid, $archive->fresh()->context['parent']['uuid']);
        $archive->delete();
        $this->assertModelMissing($child);
        $this->assertModelExists($group);
        $this->assertModelExists($item);
    }

    public function test_deleting_actor_keeps_archive_and_actor_name(): void
    {
        $archive = Archive::factory()->create();
        $name = $archive->archived_by_name;
        $archive->archivedBy->delete();
        $this->assertNull($archive->fresh()->archived_by);
        $this->assertSame($name, $archive->fresh()->archived_by_name);
    }

    public function test_source_identity_can_repeat_across_packages_but_not_within_one_package(): void
    {
        $record = ArchiveRecord::factory()->create();
        ArchiveRecord::factory()->create(['entity' => $record->entity, 'source_key' => $record->source_key]);
        $this->expectException(QueryException::class);
        $record->archive->records()->create($record->only($record->getFillable()));
    }

    #[DataProvider('referenceEntities')]
    public function test_active_reference_uuid_is_unique(string $modelClass): void
    {
        $record = $modelClass::factory()->create();
        $this->expectException(QueryException::class);
        $modelClass::factory()->create(['uuid' => $record->uuid]);
    }

    /** @return array<string, array{class-string<ReferenceGroup>|class-string<ReferenceItem>}> */
    public static function referenceEntities(): array
    {
        return ['group' => [ReferenceGroup::class], 'item' => [ReferenceItem::class]];
    }

    public function test_registry_does_not_resolve_arbitrary_classes(): void
    {
        $registry = app(ArchiveEntityRegistry::class);
        $this->assertInstanceOf(ReferenceSnapshot::class, $registry->handler(ReferenceSnapshot::ITEM));
        $this->expectException(InvalidArgumentException::class);
        $registry->handler(ReferenceGroup::class);
    }

    public function test_unsupported_version_and_mismatched_snapshot_are_retained(): void
    {
        $record = ArchiveRecord::factory()->create(['schema_version' => 2]);
        $handler = app(ReferenceSnapshot::class);
        $this->assertFalse($handler->supports($record));
        $record->update(['schema_version' => 1, 'source_uuid' => fake()->uuid()]);
        $this->assertFalse($handler->supports($record->fresh()));
        $this->assertModelExists($record);
    }
}
