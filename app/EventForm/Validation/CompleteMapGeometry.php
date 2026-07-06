<?php

declare(strict_types=1);

namespace App\EventForm\Validation;

use App\Actions\Geospatial\GeoJsonGeometryValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Requires a map field to hold at least one finished shape, and rejects any
 * unfinished one (a polygon with fewer than four ring positions or a line with
 * a single point).
 *
 * Selecting "buiten"/"route" seeds the field with a non-empty placeholder, so
 * `->required()` is fooled into passing even when nothing (or only an
 * unfinished shape that the map never committed) was drawn. This rule only runs
 * when the field is visible (Filament skips hidden fields), i.e. when a shape is
 * actually expected, so demanding a complete geometry here is correct. Reuses
 * {@see GeoJsonGeometryValidator}, the same check that guards the geometry
 * engine server-side.
 */
class CompleteMapGeometry implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->isBlank($value)) {
            // A genuinely empty field is left to the field's own ->required().
            return;
        }

        $geometries = $this->geometriesFromMapState($value);

        if ($geometries === []) {
            $fail('Teken een volledige vorm op de kaart voordat u verder gaat.');

            return;
        }

        foreach ($geometries as $geometry) {
            if (! GeoJsonGeometryValidator::isProcessable($geometry)) {
                $fail('De op de kaart getekende vorm is niet compleet. Maak de tekening af of verwijder hem voordat u verder gaat.');

                return;
            }
        }
    }

    private function isBlank(mixed $value): bool
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : $value;
        }

        return $value === null || $value === '' || $value === [];
    }

    /**
     * Pull the GeoJSON geometry objects out of a Map-state value
     * (`{lat, lng, geojson: {features: [{geometry: {...}}, ...]}}`). The Map
     * field can hand its state over as a JSON string, so decode that first.
     *
     * @return list<array<string, mixed>>
     */
    private function geometriesFromMapState(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value)) {
            return [];
        }

        $features = $value['geojson']['features'] ?? null;
        if (! is_array($features)) {
            return [];
        }

        $geometries = [];
        foreach ($features as $feature) {
            $geometry = is_array($feature) ? ($feature['geometry'] ?? null) : null;
            if (is_array($geometry)) {
                $geometries[] = $geometry;
            }
        }

        return $geometries;
    }
}
