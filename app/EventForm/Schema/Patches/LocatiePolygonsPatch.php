<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Patches;

use App\EventForm\Components\InfoText;
use Dotswan\MapPicker\Fields\Map;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Contracts\Validation\ValidationRule;
use ReflectionObject;

/**
 * Vervangt na `LocatieVanHetEvenement2Step::make()` de Repeater rond
 * `locatieSOpKaart` door één TextInput (`naamVanDeLocatieKaart`) plus
 * één Map (`locatieSOpKaart`) waarop de organisator zoveel polygonen
 * kan tekenen als 'ie wil. De Map ondersteunt zelf al multi-feature in
 * één FeatureCollection, dus de Repeater voegde alleen verwarrende
 * dubbele nesting toe.
 *
 * Historisch een runtime-patch omdat de (inmiddels verwijderde)
 * transpiler `app/EventForm/Schema/Steps/` bij elke run wiste, waardoor
 * een hand-edit in de step-file niet bleef staan. Die reden is vervallen:
 * deze logica zou nu rechtstreeks in `LocatieVanHetEvenement2Step` kunnen
 * leven. Zolang dat niet gebeurd is, blijft de patch op runtime de Step
 * aanpassen.
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
                    // Direct per keystroke synchroniseren naar Livewire-
                    // state. Nodig omdat de gemeente-response na een
                    // polygon-tekening een form-rerender triggert;
                    // debounce (500ms) en onBlur lieten beide een race-
                    // window open waarin de net getypte naam alsnog werd
                    // platgewalst.
                    ->live()
                    ->hidden($hiddenCallback);

                $patched[] = Map::make('locatieSOpKaart')
                    ->label('Locatie(s) op kaart')
                    ->defaultLocation(50.8514, 5.6910)
                    ->zoom(11)
                    ->maxZoom(19)
                    ->geoMan(true)
                    ->geoManEditable(true)
                    ->drawPolygon(true)
                    ->editPolygon(false)
                    ->drawPolyline(false)
                    ->drawMarker(false)
                    ->drawCircle(false)
                    ->drawCircleMarker(false)
                    ->drawRectangle(false)
                    ->cutPolygon(false)
                    ->dragMode(false)
                    ->rotateMode(false)
                    ->showMarker(false)
                    ->drawText(false)
                    ->deleteLayer(true)
                    ->showFullscreenControl(false)
                    ->extraStyles(['min-height: 25rem', 'border-radius: 0.5rem'])
                    ->columnSpanFull()
                    ->showMyLocationButton(false)
                    ->required()
                    ->rule(new class implements ValidationRule
                    {
                        public function validate(string $attribute, mixed $value, \Closure $fail): void
                        {
                            if (empty($value)) {
                                return;
                            }
                            if (is_string($value)) {
                                $value = json_decode($value, true) ?? [];
                            }
                            $features = $value['geojson']['features'] ?? [];
                            foreach ($features as $feature) {
                                $type = $feature['geometry']['type'] ?? '';
                                if (! in_array($type, ['Polygon', 'MultiPolygon'], strict: true)) {
                                    $fail('De locatiekaart mag alleen vlakken (polygonen) bevatten. Verwijder eventuele lijnen of andere vormen.');

                                    return;
                                }
                            }
                        }
                    })
                    ->hidden($hiddenCallback);

                $patched[] = InfoText::warning('waarschuwingPlattegrondVereist', '<p><strong>Let op: </strong>het intekenen van de locatie of het terrein in het aanvraagformulier vervangt niet de verplichte plattegrond. Voeg daarom steeds een afzonderlijke plattegrond toe. De vereisten voor deze plattegrond kunnen verschillen per gemeente. Hiervoor kunt u terecht op de gemeentelijke website of neemt u contact op met de betreffende gemeente.</p>')->hidden($hiddenCallback);

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
                    ->maxZoom(19)
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
                    ->showMarker(false)
                    ->deleteLayer(true)
                    ->drawText(false)
                    ->showFullscreenControl(false)
                    ->extraStyles(['min-height: 25rem', 'border-radius: 0.5rem'])
                    ->columnSpanFull()
                    ->showMyLocationButton(false)
                    ->required()
                    ->rule(new class implements ValidationRule
                    {
                        public function validate(string $attribute, mixed $value, \Closure $fail): void
                        {
                            if (empty($value)) {
                                return;
                            }
                            if (is_string($value)) {
                                $value = json_decode($value, true) ?? [];
                            }
                            $features = $value['geojson']['features'] ?? [];
                            foreach ($features as $feature) {
                                $type = $feature['geometry']['type'] ?? '';
                                if (! in_array($type, ['LineString', 'MultiLineString'], strict: true)) {
                                    $fail('De routekaart mag alleen lijnen bevatten. Verwijder eventuele vlakken of andere vormen.');

                                    return;
                                }
                            }
                        }
                    });

                continue;
            }
            // Forceer alle andere route-velden ook full-width
            if ($component instanceof Component) {
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
