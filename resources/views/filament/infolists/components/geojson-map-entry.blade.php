<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    @php
        $geoJson = $entry->getGeoJsonData();
        $defLoc  = $entry->getDefaultLocation();
        $mapId   = $getId();
    @endphp

    {{--
        Standalone read-only Leaflet-kaart zonder dotswan Alpine-component.
        window.L is globaal beschikbaar via de dotswan/filament-map-picker UMD-bundel.
        GeoJSON en fitBounds worden ingesteld vóór de tile layer wordt toegevoegd,
        zodat tile-loading de SVG-overlay-positionering niet verstoort.
    --}}
    <div
        x-data="{}"
        x-init="async () => {
            while (!$el.offsetParent) {
                await new Promise(r => setTimeout(r, 100));
            }
            const L = window.L;
            if (!L || $refs.map._leaflet_id) return;

            const map = L.map($refs.map, { zoomControl: true });

            const dataEl = document.getElementById('{{ $mapId }}-data');
            const geoJson = dataEl ? JSON.parse(dataEl.textContent) : null;

            if (geoJson) {
                const geoLayer = L.geoJSON(geoJson, {
                    style: () => ({ color: '#3388ff', fillColor: '#3388ff', fillOpacity: 0.3, weight: 2 }),
                    pointToLayer: (f, latlng) => L.circleMarker(latlng, {
                        radius: 8, color: '#3388ff', fillColor: '#3388ff', fillOpacity: 0.6
                    }),
                    onEachFeature: (f, layer) => {
                        if (f.properties && f.properties.title) layer.bindPopup(f.properties.title);
                    }
                }).addTo(map);

                const b = geoLayer.getBounds();
                if (b.isValid()) {
                    map.fitBounds(b, { padding: [20, 20], animate: false });
                } else {
                    map.setView([{{ $defLoc['lat'] }}, {{ $defLoc['lng'] }}], 13);
                }
            } else {
                map.setView([{{ $defLoc['lat'] }}, {{ $defLoc['lng'] }}], 13);
            }

            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '\u00a9 OpenStreetMap contributors'
            }).addTo(map);

            new ResizeObserver(() => map.invalidateSize({ animate: false })).observe($refs.map);
        }"
        {{ $getExtraAttributeBag() }}
        wire:ignore
    >
        <div x-ref="map" style="min-height: 30vh;"></div>

        @if($geoJson)
            <script type="application/json" id="{{ $mapId }}-data">
{!! json_encode($geoJson, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
            </script>
        @endif
    </div>
</x-dynamic-component>
