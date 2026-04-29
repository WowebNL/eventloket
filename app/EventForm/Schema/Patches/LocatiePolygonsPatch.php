<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Patches;

use Dotswan\MapPicker\Fields\Map;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Wizard\Step;
use ReflectionObject;

/**
 * Vervangt na `LocatieVanHetEvenement2Step::make()` de transpiler-
 * gegenereerde Repeater rond `locatieSOpKaart` door één TextInput
 * (`naamVanDeLocatieKaart`) plus één Map (`locatieSOpKaart`) waarop
 * de organisator zoveel polygonen kan tekenen als 'ie wil. De Map
 * ondersteunt zelf al multi-feature in één FeatureCollection, dus
 * de Repeater voegde alleen verwarrende dubbele nesting toe.
 *
 * Waarom een patch i.p.v. de step-file zelf editen: de TranspileEventForm-
 * test wist `app/EventForm/Schema/Steps/` en regenereert 'm bij elke run.
 * Een hand-edit zou daar elke keer uit gewist worden. Deze post-process
 * leeft buiten de wipe-zone en verandert de Step pas op runtime.
 *
 * State-shape effect:
 *
 *   Voor:  locatieSOpKaart = [{ naamVanDeLocatieKaart, buitenLocatieVanHetEvenement: {...} }, ...]
 *   Na:    locatieSOpKaart = { lat, lng, geojson: { features: [...] } }
 *          naamVanDeLocatieKaart = "Eén naam"
 *
 * `ServiceFetcher::collectPolygonsFromEditgrid` en
 * `EventLocationGeometryBuilder::parseMultipolygons` ondersteunen
 * beide shapes (oud + nieuw) zodat bestaande drafts blijven werken.
 */
final class LocatiePolygonsPatch
{
    public static function apply(Step $step): Step
    {
        $reflection = new ReflectionObject($step);
        if (! $reflection->hasProperty('childComponents')) {
            return $step;
        }
        $prop = $reflection->getProperty('childComponents');
        $prop->setAccessible(true);
        $childComponents = $prop->getValue($step);
        if (! is_array($childComponents) || ! isset($childComponents['default']) || ! is_array($childComponents['default'])) {
            return $step;
        }

        $patched = [];
        foreach ($childComponents['default'] as $component) {
            // Route-Fieldset: vervang óók de geneste Repeater(`routesOpKaart`)
            // door één Map met multi-line support.
            if ($component instanceof Fieldset && $component->getLabel() === 'Route') {
                self::replaceRouteRepeaterIn($component);
                $patched[] = $component;

                continue;
            }

            if ($component instanceof Repeater && $component->getName() === 'locatieSOpKaart') {
                // Erve de hidden-closure van de oude Repeater zodat we
                // dezelfde zichtbaarheidsregels behouden.
                $hiddenCallback = static fn ($livewire): bool => $livewire->state()->isFieldHidden('locatieSOpKaart') !== false;

                $patched[] = TextInput::make('naamVanDeLocatieKaart')
                    ->label('Naam van de locatie')
                    ->required()
                    ->maxLength(1000)
                    ->hidden($hiddenCallback);

                $patched[] = Map::make('locatieSOpKaart')
                    ->label('Locatie(s) op kaart')
                    ->defaultLocation(50.8514, 5.6910)
                    ->zoom(11)
                    ->geoMan(true)
                    ->geoManEditable(true)
                    ->drawPolygon(true)
                    ->drawPolyline(false)
                    ->drawMarker(false)
                    ->drawCircle(false)
                    ->drawCircleMarker(false)
                    ->drawRectangle(false)
                    ->cutPolygon(false)
                    ->dragMode(false)
                    ->rotateMode(false)
                    ->extraStyles(['min-height: 25rem', 'border-radius: 0.5rem'])
                    ->columnSpanFull()
                    ->required()
                    ->hidden($hiddenCallback);

                continue;
            }

            $patched[] = $component;
        }

        $childComponents['default'] = $patched;
        $prop->setValue($step, $childComponents);

        // Cached schemas wissen anders blijft Filament de oude (Repeater-)
        // versie tonen tot de page herinitialiseert.
        if ($reflection->hasProperty('cachedDefaultChildSchemas')) {
            $cacheProp = $reflection->getProperty('cachedDefaultChildSchemas');
            $cacheProp->setAccessible(true);
            $cacheProp->setValue($step, null);
        }

        return $step;
    }

    /**
     * Vervang de Repeater(`routesOpKaart`) binnen een Fieldset door één
     * Map(`routesOpKaart`) zodat de organisator meerdere routes op
     * dezelfde kaart kan tekenen. Mut de Fieldset in-place.
     */
    private static function replaceRouteRepeaterIn(Fieldset $fieldset): void
    {
        $reflection = new ReflectionObject($fieldset);
        if (! $reflection->hasProperty('childComponents')) {
            return;
        }
        $prop = $reflection->getProperty('childComponents');
        $prop->setAccessible(true);
        $children = $prop->getValue($fieldset);
        if (! is_array($children) || ! isset($children['default']) || ! is_array($children['default'])) {
            return;
        }

        $new = [];
        foreach ($children['default'] as $component) {
            if ($component instanceof Repeater && $component->getName() === 'routesOpKaart') {
                $new[] = Map::make('routesOpKaart')
                    ->label('Route(s) op kaart')
                    ->defaultLocation(50.8514, 5.6910)
                    ->zoom(11)
                    ->geoMan(true)
                    ->geoManEditable(true)
                    ->drawPolygon(false)
                    ->drawPolyline(true)
                    ->drawMarker(false)
                    ->drawCircle(false)
                    ->drawCircleMarker(false)
                    ->drawRectangle(false)
                    ->cutPolygon(false)
                    ->dragMode(false)
                    ->rotateMode(false)
                    ->extraStyles(['min-height: 25rem', 'border-radius: 0.5rem'])
                    ->columnSpanFull()
                    ->required();

                continue;
            }
            // Forceer alle andere route-velden ook full-width zodat de
            // route-tab consistent breed weergeeft (feedback opdrachtgever).
            if ($component instanceof Component && method_exists($component, 'columnSpanFull')) {
                $component->columnSpanFull();
            }
            $new[] = $component;
        }

        $children['default'] = $new;
        $prop->setValue($fieldset, $children);

        if ($reflection->hasProperty('cachedDefaultChildSchemas')) {
            $cacheProp = $reflection->getProperty('cachedDefaultChildSchemas');
            $cacheProp->setAccessible(true);
            $cacheProp->setValue($fieldset, null);
        }
    }
}
