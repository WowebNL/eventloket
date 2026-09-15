<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    @php
        $geoJson = $entry->getGeoJsonData();
        $defLoc  = $entry->getDefaultLocation();
        $mapId   = $getId();
        $tilesUrl = $entry->getTilesUrl();
        $tileOptions = $entry->getTileLayerOptions();
    @endphp

    {{--
        Standalone read-only Leaflet map without the map picker's Alpine
        component. window.L is available globally through the map picker's
        UMD bundle. GeoJSON and fitBounds are applied before the tile layer
        is added, so tile loading does not disturb the positioning of the
        SVG overlay. The tile layer itself comes from config/maps.php, so
        this map uses the same basemap and attribution as the form fields.
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

            L.tileLayer(@js($tilesUrl), @js($tileOptions)).addTo(map);

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
