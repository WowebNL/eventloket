{{--
    Override van vendor/dotswan/filament-map-picker/resources/views/fields/osm-map-picker.blade.php.

    Reden: dotswan's Alpine-component schrijft de Map-state via
    `$wire.set(path, value, true)` (`true` = deferred) op de leaflet
    geoman-events. Daardoor blijft de state hangen totdat een ANDERE
    Livewire-roundtrip 'm meeflusht — wat tegenintuïtief is wanneer
    je verwacht dat een ingetekend polygon meteen tot een
    gemeente-detect leidt.

    We voegen daarom een dunne Alpine-listener toe op `pm:create`,
    `pm:edit` en `pm:remove` van de leaflet-map die — nadat de dotswan-
    handler zijn deferred set heeft gedaan — `$wire.$commit()` aanroept.
    Dat dwingt een onmiddellijke roundtrip naar de server af, waarna
    `ServiceFetcher::fetchInGemeentenResponse` (vanuit
    `AlsBoolEnIsNietGelijkAanNone` rule) de getekende polygon door de
    intersect-check haalt en `inGemeentenResponse` bijwerkt.

    Verder is de markup identiek aan upstream zodat we automatisch
    profiteren van toekomstige fixes — alleen de extra @{} `init`-stap
    is van ons.
--}}
<x-filament-forms::field-wrapper
    :id="$getId()"
    :label="$getLabel()"
    :label-sr-only="$isLabelHidden()"
    :hint="$getHint()"
    :required="$isRequired()"
    :state-path="$getStatePath()"
>
    <div x-data="mapPicker($wire, {{ $getMapConfig() }})"
            x-init="async () => {
            do {
                await (new Promise(resolve => setTimeout(resolve, 100)));
            } while (!$refs.map);
            attach($refs.map);

            // Forceer een directe Livewire-commit zodra de gebruiker een
            // polygon/lijn tekent, bewerkt of verwijdert. Zonder dit blijft
            // de state hangen tot een andere live-actie 'm meepakt en zou
            // de gemeente-detectie pas later zichtbaar worden.
            const flushNu = () => {
                // Microtick zodat dotswan's eigen handler eerst z'n
                // deferred state-set doet; daarna pas committen.
                queueMicrotask(() => $wire.$commit());
            };
            const map = this.map;
            if (map && map.on) {
                map.on('pm:create', flushNu);
                map.on('pm:edit', flushNu);
                map.on('pm:remove', flushNu);
                map.on('pm:update', flushNu);
            }

            // Onderdruk dotswan's `setMarkerRange`: zonder een
            // `rangeSelectField` met daadwerkelijke afstand tekent 'ie
            // bij elke moveend een 1-pixel rode/blauwe dot in het midden
            // van het venster. Wij gebruiken die feature niet — alle
            // input loopt via geojson.features[]. Vervang de methode door
            // een no-op en ruim een eventueel al getekende rangeCircle
            // op.
            this.setMarkerRange = () => {};
            if (this.rangeCircle && this.rangeCircle.remove) {
                this.rangeCircle.remove();
                this.rangeCircle = null;
            }
        }"
            wire:ignore
    >
        <div
            x-ref="map"
            class="w-full" style="min-height: 30vh; {{ $getExtraStyle() }}">
        </div>
        <input type="text" id="{{ $getStatePath() }}_fmrest" style="display:none"/>
    </div>
</x-filament-forms::field-wrapper>
