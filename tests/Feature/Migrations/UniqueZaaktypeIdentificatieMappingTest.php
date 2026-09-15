<?php

declare(strict_types=1);

/**
 * The unique index that forbids mapping one external zaaktype to more than one
 * role, and its pre-check: with duplicates present the migration has to say
 * which rows to clean up instead of failing on a bare constraint violation.
 */

use App\Enums\ZaaktypeRole;
use App\Models\Municipality;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

const PLAIN_MAPPING_INDEX = 'municipality_zaaktype_map_identificatie_index';

const UNIQUE_MAPPING_INDEX = 'municipality_zaaktype_map_identificatie_unique';

/**
 * The name a database carries that created this table before the create
 * migration named the index explicitly: the generated
 * `municipality_zaaktype_mappings_municipality_id_zaaktype_identificatie_index`
 * is 75 characters, which PostgreSQL silently truncates to 63.
 */
const LEGACY_MAPPING_INDEX = 'municipality_zaaktype_mappings_municipality_id_zaaktype_identif';

/** The migration under test. It is an anonymous class, so it has to be required. */
function uniqueMappingMigration(): Migration
{
    return require database_path('migrations/2026_09_01_120000_add_unique_zaaktype_identificatie_to_municipality_zaaktype_mappings.php');
}

function uniqueMappingIndexExists(): bool
{
    return collect(Schema::getIndexes('municipality_zaaktype_mappings'))
        ->pluck('name')
        ->contains(UNIQUE_MAPPING_INDEX);
}

function koppelingRow(Municipality $municipality, ZaaktypeRole $role, ?string $identificatie): void
{
    DB::table('municipality_zaaktype_mappings')->insert([
        'municipality_id' => $municipality->id,
        'role' => $role->value,
        'zaaktype_identificatie' => $identificatie,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

afterEach(function () {
    // These tests add and drop an index. MySQL implicitly commits on DDL, so the
    // transaction RefreshDatabase wraps around a test cannot be relied on here:
    // clean up the rows explicitly and leave the schema as it was found.
    DB::table('municipality_zaaktype_mappings')->delete();
    DB::table('municipalities')->delete();

    if (! uniqueMappingIndexExists()) {
        uniqueMappingMigration()->up();
    }
});

it('adds the index when no zaaktype is mapped twice', function () {
    $municipality = Municipality::factory()->create();
    uniqueMappingMigration()->down();

    koppelingRow($municipality, ZaaktypeRole::Vergunning, 'EXT-1');
    koppelingRow($municipality, ZaaktypeRole::Vooraankondiging, 'EXT-2');

    uniqueMappingMigration()->up();

    // Asserted on the schema rather than by provoking a violation: a failed
    // statement behaves differently per engine inside the transaction a test
    // runs in, while the index itself is the thing this migration adds.
    $index = collect(Schema::getIndexes('municipality_zaaktype_mappings'))
        ->firstWhere('name', UNIQUE_MAPPING_INDEX);

    expect($index)->not->toBeNull()
        ->and($index['unique'])->toBeTrue()
        ->and(collect($index['columns'])->sort()->values()->all())
        ->toBe(['municipality_id', 'zaaktype_identificatie'])
        // The plain index on the same columns has been replaced by it.
        ->and(collect(Schema::getIndexes('municipality_zaaktype_mappings'))->pluck('name'))
        ->not->toContain(PLAIN_MAPPING_INDEX);
});

it('keeps allowing several koppelingen without a zaaktype', function () {
    // A role may be created before its zaaktype is chosen: both engines leave
    // null values out of a unique index.
    $municipality = Municipality::factory()->create();

    koppelingRow($municipality, ZaaktypeRole::Vergunning, null);
    koppelingRow($municipality, ZaaktypeRole::Melding, null);

    expect(DB::table('municipality_zaaktype_mappings')->count())->toBe(2);
});

it('refuses to add the index and names the duplicates', function () {
    $municipality = Municipality::factory()->create();
    uniqueMappingMigration()->down();

    koppelingRow($municipality, ZaaktypeRole::Melding, 'EXT-1');
    koppelingRow($municipality, ZaaktypeRole::Vooraankondiging, 'EXT-1');
    koppelingRow($municipality, ZaaktypeRole::Vergunning, 'EXT-2');

    try {
        uniqueMappingMigration()->up();
        $this->fail('The migration should have refused to add the index.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())
            ->toContain((string) $municipality->id)
            ->toContain('EXT-1')
            ->toContain(ZaaktypeRole::Melding->value)
            ->toContain(ZaaktypeRole::Vooraankondiging->value)
            // The zaaktype that is mapped only once is not part of the problem.
            ->not->toContain('EXT-2');
    }

    expect(uniqueMappingIndexExists())->toBeFalse();
});

it('drops the plain index whatever the database happens to call it', function () {
    // The create migration did not always name this index explicitly, so a
    // database created in that window carries the generated name instead. The
    // migration has to find the index by its columns: dropping a hard-coded
    // name that is not there aborts the deploy, after the duplicate guard and
    // before the unique index, so nothing is added and the release stops.
    $municipality = Municipality::factory()->create();
    uniqueMappingMigration()->down();

    Schema::table('municipality_zaaktype_mappings', function (Blueprint $table): void {
        $table->renameIndex(PLAIN_MAPPING_INDEX, LEGACY_MAPPING_INDEX);
    });

    koppelingRow($municipality, ZaaktypeRole::Vooraankondiging, 'EXT-1');

    uniqueMappingMigration()->up();

    $names = collect(Schema::getIndexes('municipality_zaaktype_mappings'))->pluck('name');

    expect($names)->toContain(UNIQUE_MAPPING_INDEX)
        ->and($names)->not->toContain(LEGACY_MAPPING_INDEX)
        ->and($names)->not->toContain(PLAIN_MAPPING_INDEX);
});
