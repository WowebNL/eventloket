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

    Daarnaast zetten we GeoMan op Nederlands via `map.pm.setLang('nl')`
    zodat de toolbar-tooltips ("Klik om eerste hoekpunt te plaatsen",
    "Klaar", "Annuleren" etc.) niet in het Engels staan. Dotswan biedt
    daar geen config voor, vandaar de directe instance-aanroep.

    Verder is de markup identiek aan upstream zodat we automatisch
    profiteren van toekomstige fixes — alleen de extra @{} `init`-stap
    is van ons.

    Aanvullend staat er boven elke kaart een adres-zoekwidget (Alpine,
    puur client-side). Die roept PDOK Locatieserver `/free` aan en
    dispatcht een browser-CustomEvent `map-fly-to` met de lat/lng en het
    `statePath` van de kaart. De map-Alpine-component luistert op
    `window` en roept `setView()` aan als het statePath overeenkomt —
    zodat twee kaarten op dezelfde pagina elkaar niet storen.
    De flyToHandler gebruikt `$data.map` (Alpine reactieve data-proxy) in
    plaats van `this.map` om gegarandeerd de juiste Leaflet-instantie te
    bereiken, ongeacht hoe Alpine `this` bindt in async arrow-functions.
    De `window`-listener wordt niet expliciet opgeruimd: `$cleanup` is
    niet beschikbaar in async `x-init` in deze Alpine-versie, en
    cleanup is hier overbodig omdat de statePath-guard onbedoelde
    aanroepen voorkomt en de browser alles opruimt bij page-unload.
    De dropdown is position:absolute (relatief aan de container) met
    z-index:9999, zodat hij boven de Leaflet-kaart zweeft maar vast
    onder het invoerveld blijft zitten bij scrollen.
--}}
<x-filament-forms::field-wrapper
    :id="$getId()"
    :label="$getLabel()"
    :label-sr-only="$isLabelHidden()"
    :hint="$getHint()"
    :required="$isRequired()"
    :state-path="$getStatePath()"
>
    {{-- Adres-zoekwidget: zoekt via PDOK Locatieserver en pant de kaart.
         Dropdown is position:absolute (relatief aan container) met z-index:9999
         zodat hij boven de Leaflet-kaart zweeft maar vast onder het invoerveld
         blijft bij scrollen.
         Toetsenbord: ↓↑ navigeren, Enter selecteren, Escape sluiten.
         Debounce via $watch + setTimeout (betrouwbaarder dan x-on:input.debounce
         in combinatie met x-model in de Livewire-gebundelde Alpine). --}}
    <div
        x-data="{
            query: '',
            suggestions: [],
            open: false,
            loading: false,
            searched: false,
            activeIndex: -1,
            _timer: null,
            _selected: false,
            infoOpen: false,

            init() {
                this.$watch('query', (val) => {
                    // Sla de $watch-ronde over die door select() zelf wordt veroorzaakt,
                    // zodat het invullen van de naam de dropdown niet hereopent.
                    if (this._selected) { this._selected = false; return; }
                    clearTimeout(this._timer);
                    if (val.length < 4) {
                        this.suggestions = [];
                        this.open = false;
                        this.searched = false;
                        this.activeIndex = -1;
                        return;
                    }
                    this._timer = setTimeout(() => this.fetchSuggestions(), 400);
                });
            },

            fetchSuggestions() {
                this.loading = true;
                this.searched = false;
                const params = new URLSearchParams({
                    q: this.query,
                    fq: 'type:(adres woonplaats)',
                    rows: 8,
                    fl: 'id weergavenaam centroide_ll',
                });
                fetch('https://api.pdok.nl/bzk/locatieserver/search/v3_1/free?' + params)
                    .then(r => {
                        if (!r.ok) throw new Error('PDOK HTTP ' + r.status);
                        return r.json();
                    })
                    .then(data => {
                        this.loading = false;
                        this.searched = true;
                        this.suggestions = (data.response?.docs ?? []).filter(d => d.centroide_ll);
                        this.activeIndex = -1;
                        this.open = true;
                    })
                    .catch(err => {
                        this.loading = false;
                        this.searched = false;
                        console.error('[PDOK adreszoek]', err);
                    });
            },

            select(s) {
                const m = s.centroide_ll.match(/POINT\(([0-9.]+) ([0-9.]+)\)/);
                if (!m) return;
                window.dispatchEvent(new CustomEvent('map-fly-to', {
                    detail: {
                        statePath: '{{ $getStatePath() }}',
                        lat: parseFloat(m[2]),
                        lng: parseFloat(m[1]),
                    }
                }));
                // Zet _selected vóór query, zodat $watch de fetch overslaat.
                this._selected = true;
                this.query = s.weergavenaam;
                this.suggestions = [];
                this.searched = false;
                this.open = false;
                this.activeIndex = -1;
                clearTimeout(this._timer);
            },

            moveDown() {
                if (!this.open || this.suggestions.length === 0) return;
                this.activeIndex = Math.min(this.activeIndex + 1, this.suggestions.length - 1);
                this.scrollActiveIntoView();
            },

            moveUp() {
                if (!this.open || this.suggestions.length === 0) return;
                this.activeIndex = Math.max(this.activeIndex - 1, -1);
                this.scrollActiveIntoView();
            },

            scrollActiveIntoView() {
                this.$nextTick(() => {
                    const list = this.$refs.suggestionList;
                    if (!list) return;
                    const item = list.children[this.activeIndex];
                    if (item) item.scrollIntoView({ block: 'nearest' });
                });
            },

            confirmSelection() {
                if (this.activeIndex >= 0 && this.suggestions[this.activeIndex]) {
                    this.select(this.suggestions[this.activeIndex]);
                }
            },
        }"
        class="relative mb-2"
        x-on:click.outside="open = false; activeIndex = -1"
    >
        {{-- Input wrapper met Filament-stijl + informatie-knop naast het zoekveld --}}
        @php
            $isRouteMap = str_contains($getStatePath(), 'routes');
            $_geoMan = json_decode($getMapConfig(), true)['geoMan'] ?? [];
            $isMarkerMap = ($_geoMan['drawMarker'] ?? false) && !($_geoMan['drawPolygon'] ?? false) && !($_geoMan['drawPolyline'] ?? false);
        @endphp
        <div class="flex items-center gap-x-2">
            <div class="fi-input-wrp flex-1">
                <div class="fi-input-wrp-content-ctn min-w-0 flex-1">
                    <input
                        type="text"
                        x-ref="searchInput"
                        x-model="query"
                        x-on:keydown.arrow-down.prevent="moveDown()"
                        x-on:keydown.arrow-up.prevent="moveUp()"
                        x-on:keydown.enter.prevent="confirmSelection()"
                        x-on:keydown.escape="open = false; activeIndex = -1"
                        placeholder="Zoek een adres om de kaart te centreren…"
                        autocomplete="off"
                        role="combobox"
                        :aria-expanded="open"
                        :aria-activedescendant="activeIndex >= 0 ? 'pdok-suggestion-{{ $getStatePath() }}-' + activeIndex : null"
                        aria-autocomplete="list"
                        aria-label="Adres zoeken"
                        class="fi-input block w-full border-none bg-transparent px-3 py-1.5 text-base text-gray-950 outline-none placeholder:text-gray-400 focus:ring-0 dark:text-white dark:placeholder:text-gray-500 sm:text-sm sm:leading-6"
                    />
                </div>
            </div>
            {{-- Informatie-knop: opent uitleg over het gebruik van de kaart --}}
            <button
                type="button"
                x-on:click="infoOpen = true"
                class="shrink-0 flex items-center justify-center w-8 h-8 rounded-full text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                title="{{ $isRouteMap ? 'Informatie: hoe teken ik een route in?' : ($isMarkerMap ? 'Informatie: hoe markeer ik een exacte locatie?' : 'Informatie: hoe teken ik een locatie in?') }}"
                aria-label="{{ $isRouteMap ? 'Informatie: hoe teken ik een route in?' : ($isMarkerMap ? 'Informatie: hoe markeer ik een exacte locatie?' : 'Informatie: hoe teken ik een locatie in?') }}"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                </svg>
            </button>
        </div>

        {{-- Dropdown: position:absolute relatief aan de container (position:relative).
             z-index:9999 zorgt dat de dropdown boven de Leaflet-kaart zweeft. --}}
        <div
            x-show="open"
            style="position:absolute;left:0;top:100%;width:100%;z-index:9999"
            class="mt-1 overflow-hidden rounded-lg border border-gray-300 bg-white shadow-xl dark:border-gray-600 dark:bg-gray-800"
        >
            {{-- Laad-indicator --}}
            <p
                x-show="loading"
                class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400"
            >Zoeken…</p>

            {{-- Geen resultaten --}}
            <p
                x-show="!loading && searched && suggestions.length === 0"
                class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400"
            >Geen resultaten gevonden voor "<span x-text="query" class="font-medium"></span>".</p>

            {{-- Resultatenlijst --}}
            <ul
                x-show="!loading && suggestions.length > 0"
                x-ref="suggestionList"
                role="listbox"
                class="overflow-y-auto max-h-60 py-1"
            >
                <template x-for="(s, i) in suggestions" :key="s.id">
                    <li
                        :id="'pdok-suggestion-{{ $getStatePath() }}-' + i"
                        x-on:mousedown.prevent="select(s)"
                        x-on:mouseover="activeIndex = i"
                        x-text="s.weergavenaam"
                        role="option"
                        :aria-selected="activeIndex === i"
                        :class="activeIndex === i
                            ? 'bg-primary-600 text-white dark:bg-primary-500'
                            : 'text-gray-800 dark:text-gray-100'"
                        class="cursor-pointer px-3 py-2 text-sm leading-5 hover:bg-primary-600 hover:text-white dark:hover:bg-primary-500"
                    ></li>
                </template>
            </ul>
        </div>

        {{-- Informatie-modal: legt in gewone taal uit hoe de kaart werkt.
             Position:fixed zodat de overlay altijd de hele viewport bedekt,
             ook als de kaart scrolt. z-index hoger dan de kaart (9999) en
             de dropdown (9999) zodat de modal daar altijd boven zweeft.
             De content is specifiek per kaarttype (route vs. markering vs. locatievlak). --}}
        <div
            x-show="infoOpen"
            x-on:keydown.escape.window="infoOpen = false"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:click.self="infoOpen = false"
            style="position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,0.5)"
            class="flex items-start justify-center pt-20 px-4"
            role="dialog"
            aria-modal="true"
        >
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-lg w-full max-h-[80vh] flex flex-col">
                {{-- Header --}}
                <div class="flex items-center justify-between px-6 pt-5 pb-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                        @if($isRouteMap)
                            Hoe teken ik een route in?
                        @elseif($isMarkerMap)
                            Hoe markeer ik een exacte locatie?
                        @else
                            Hoe teken ik een locatie in?
                        @endif
                    </h2>
                    <button
                        type="button"
                        x-on:click="infoOpen = false"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors rounded"
                        aria-label="Sluiten"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                {{-- Scrollable content --}}
                <div class="overflow-y-auto flex-1 px-6 py-5 space-y-4 text-sm text-gray-700 dark:text-gray-300">
                    @if($isRouteMap)
                        <p>Op deze kaart tekent u de route(s) van uw evenement in, zoals een optocht, wandeling of fietstocht. Een route wordt als een <strong>lijn</strong> op de kaart getekend.</p>
                        <ol class="list-decimal list-outside ml-4 space-y-2">
                            <li>Gebruik de <strong>zoekbalk</strong> hierboven om de kaart te verplaatsen naar uw startpunt.</li>
                            <li>Klik op het <strong>lijn-icoontje</strong> (een streep met punten) in de werkbalk aan de rechterkant van de kaart.</li>
                            <li>Klik op de kaart om het <strong>beginpunt</strong> van de route te zetten.</li>
                            <li>Klik daarna op alle volgende punten langs de route. Elk klik voegt een nieuw punt toe.</li>
                            <li><strong>Dubbelklik</strong> op het eindpunt om de route af te ronden.</li>
                        </ol>
                        <p>Heeft uw evenement meerdere routes (bijv. een heen- en een terugweg)? Herhaal dan stap 2 t/m 5 voor elke route.</p>
                        <p>Een route verwijderen: klik op het <strong>gum-icoontje</strong> in de werkbalk en klik daarna op de route die u wilt verwijderen.</p>
                        <p class="rounded-lg bg-amber-50 dark:bg-amber-900/30 px-4 py-3 text-amber-700 dark:text-amber-300 font-medium">
                            Let op: op deze kaart kunt u alleen routes (lijnen) intekenen. Vlakken en andere vormen zijn hier niet toegestaan.
                        </p>
                    @elseif($isMarkerMap)
                        <p>Op deze kaart geeft u de <strong>exacte positie</strong> aan door een pin op de kaart te plaatsen.</p>
                        <ol class="list-decimal list-outside ml-4 space-y-2">
                            <li>Gebruik de <strong>zoekbalk</strong> hierboven om de kaart te verplaatsen naar de gewenste locatie.</li>
                            <li>Klik op het <strong>pin-icoontje</strong> (markering) in de werkbalk aan de linkerkant van de kaart.</li>
                            <li>Klik op de kaart op de <strong>exacte plek</strong> waar u de markering wilt plaatsen. De pin verschijnt direct.</li>
                        </ol>
                        <p>Een markering verwijderen: klik op het <strong>gum-icoontje</strong> in de werkbalk en klik daarna op de pin die u wilt verwijderen.</p>
                        <p class="rounded-lg bg-amber-50 dark:bg-amber-900/30 px-4 py-3 text-amber-700 dark:text-amber-300 font-medium">
                            Let op: op deze kaart kunt u alleen een markering (pin) plaatsen. Vlakken en lijnen zijn hier niet toegestaan.
                        </p>
                    @else
                        <p>Op deze kaart tekent u het <strong>gebied</strong> af waar uw evenement plaatsvindt, bijvoorbeeld een festivalterrein of marktplein. U tekent de buitengrens van uw locatie als een <strong>vlak</strong> op de kaart.</p>
                        <ol class="list-decimal list-outside ml-4 space-y-2">
                            <li>Gebruik de <strong>zoekbalk</strong> hierboven om de kaart te verplaatsen naar uw locatie.</li>
                            <li>Klik op het <strong>vlak-icoontje</strong> (een veelhoek) in de werkbalk aan de rechterkant van de kaart.</li>
                            <li>Klik op de kaart om het <strong>eerste hoekpunt</strong> van uw locatie te zetten.</li>
                            <li>Klik daarna op de overige hoekpunten langs de buitenrand van uw locatie.</li>
                            <li>Sluit het vlak door op het <strong>eerste hoekpunt</strong> te klikken. Het vlak wordt blauw ingekleurd.</li>
                        </ol>
                        <p>Heeft uw evenement meerdere afgebakende gebieden (bijv. een hoofdlocatie en een apart parkeerterrein)? Herhaal dan stap 2 t/m 5 voor elk gebied.</p>
                        <p>Een vlak verwijderen: klik op het <strong>gum-icoontje</strong> in de werkbalk en klik daarna op het vlak dat u wilt verwijderen.</p>
                        <p class="rounded-lg bg-amber-50 dark:bg-amber-900/30 px-4 py-3 text-amber-700 dark:text-amber-300 font-medium">
                            Let op: op deze kaart kunt u alleen locatievlakken intekenen. Lijnen en andere vormen zijn hier niet toegestaan.
                        </p>
                    @endif
                </div>
                {{-- Footer --}}
                <div class="px-6 pb-5 pt-4 border-t border-gray-200 dark:border-gray-700 shrink-0">
                    <button
                        type="button"
                        x-on:click="infoOpen = false"
                        class="w-full rounded-lg bg-primary-600 hover:bg-primary-500 text-white px-4 py-2 text-sm font-medium transition-colors"
                    >Begrepen, sluiten</button>
                </div>
            </div>
        </div>
    </div>

    {{--
        Fix voor upstream-bug in dotswan/filament-map-picker v2.3.3:
        in resources/css/index.css regel 33-51 wordt een `z-index: auto
        !important` toegepast op alle Leaflet-panes. Daardoor verdwijnt
        getekende geometry visueel onder de tile-layer. Hier herstellen
        we de Leaflet-default-stacking via hogere specificity (.leaflet-
        container scoping) zodat onze regel wint van dotswan's !important.
    --}}
    <style>
        .leaflet-container .leaflet-tile-pane { z-index: 200 !important; }
        .leaflet-container .leaflet-overlay-pane { z-index: 400 !important; }
        .leaflet-container .leaflet-shadow-pane { z-index: 500 !important; }
        .leaflet-container .leaflet-marker-pane { z-index: 600 !important; }
        .leaflet-container .leaflet-tooltip-pane { z-index: 650 !important; }
        .leaflet-container .leaflet-popup-pane { z-index: 700 !important; }
    </style>
    <div
            wire:key="osm-map-picker-{{ $getStatePath() }}"
            x-data="mapPicker($wire, {{ $getMapConfig() }})"
            x-init="async () => {
            // Symptoom-fix voor multi-instance Alpine state-collision:
            // wanneer er meerdere Map::make() velden op dezelfde Livewire-
            // pagina staan, blijken hun Alpine-componenten op een
            // ondoorzichtige manier dezelfde data-objectreferentie te
            // delen (this.config, this.map, this.drawItems). Daardoor
            // overschrijft de tweede mapPicker-init de eerste en zien
            // beide kaarten dezelfde geojson.
            //
            // De fix omzeilt dotswan's gedeelde state door per kaart:
            // 1) de Leaflet-map aan het DOM-element zelf te koppelen
            //    (mapEl.__leafletMap), zodat we per kaart de juiste
            //    Leaflet-instance terug kunnen vinden;
            // 2) na attach() de getekende layers van DIE specifieke map
            //    weg te halen en te vervangen door de eigen geojson (uit
            //    $wire.get(_expectedStatePath));
            // 3) eigen pm:create/edit/remove/moveend-handlers te binden
            //    die direct naar _expectedStatePath schrijven, los van
            //    dotswan's gedeelde this.config.statePath.
            const _self = $data;
            const _expectedStatePath = '{{ $getStatePath() }}';

            // Fly-to handler: pakt de map via het DOM-element ipv via
            // _self.map (kan overschreven zijn door andere instance).
            const flyToHandler = (e) => {
                if (e.detail.statePath !== _expectedStatePath) return;
                const map = $refs.map && $refs.map.__leafletMap;
                if (map) {
                    map.setView([e.detail.lat, e.detail.lng], 16);
                }
            };
            window.addEventListener('map-fly-to', flyToHandler);

            do {
                await (new Promise(resolve => setTimeout(resolve, 100)));
            } while (!$refs.map);

            // GeoMan-toolbar in het Nederlands. We registreren een eigen
            // language die overerft van 'nl' maar de verwarrende
            // 'Bewaar'-tooltip op de finish-actie van het gummetje
            // overschrijft naar 'Klaar' (Michel #3: 'Verwijder/Bewaar'
            // bij dezelfde knop is dubbelzinnig).
            if (window.L && window.L.PM) {
                try {
                    L.PM.setLang('nlEventloket', { actions: { finish: 'Klaar' } }, 'nl');
                    window.L.PM.activeLang = 'nlEventloket';
                } catch (e) {
                    window.L.PM.activeLang = 'nl';
                }
            }

            // FIX 1: monkey-patch createMap om de Leaflet-instance aan het
            // DOM-element te koppelen. DOM-elementen zijn echt per kaart
            // uniek, dus dit overleeft state-deling op _self.
            const _origCreateMap = _self.createMap;
            _self.createMap = function (el) {
                _origCreateMap.call(this, el);
                el.__leafletMap = this.map;
            };

            // KRITIEK: dotswan's setCoordinates doet
            // `$wire.set(statePath, {lat, lng})` — een object ZONDER geojson.
            // Dat wordt aangeroepen vanuit:
            //   - createMap's initial setView() → moveend → updateLocation()
            //   - initFormRestoration's pageshow-listener (bij elke reload!)
            // Beide pad'en overschrijven onze geojson-state met enkel coords.
            // No-op'en MOET vóór attach() gebeuren — anders is de schade
            // al gedaan bij de eerste moveend van setView().
            _self.setMarkerRange = () => {};
            _self.setCoordinates = () => {};
            _self.updateGeoJson = () => {};
            _self.removeMap = () => {};

            attach($refs.map);

            // FIX 2: vervang de door dotswan geladen (mogelijk verkeerde)
            // features op DEZE map door de juiste geojson uit onze eigen
            // _expectedStatePath. Bind ook eigen draw-event-handlers die
            // naar _expectedStatePath schrijven.
            setTimeout(() => {
                const mapEl = $refs.map;
                const map = mapEl && mapEl.__leafletMap;
                if (!map) return;

                // Verwijder bestaande feature-layers (behoud tile-layer).
                const toRemove = [];
                map.eachLayer((layer) => {
                    if (window.L && layer instanceof window.L.TileLayer) return;
                    if (layer.toGeoJSON || layer.getLatLngs) {
                        toRemove.push(layer);
                    }
                });
                toRemove.forEach(layer => map.removeLayer(layer));

                // Lees onze eigen state, los van dotswan's gedeelde config.
                const state = $wire.get(_expectedStatePath) || {};
                const initialGeoJson = state.geojson || { type: 'FeatureCollection', features: [] };

                // Lokale FeatureGroup voor DEZE kaart. Geen jacht op
                // _self.drawItems — die is gedeeld en niet betrouwbaar.
                const fg = window.L.geoJSON(initialGeoJson, {
                    style: function (feature) {
                        if (feature.geometry.type === 'Polygon' || feature.geometry.type === 'MultiPolygon') {
                            return { color: '#3388ff', fillColor: '#cad9ec', weight: 2, fillOpacity: 0.4 };
                        }
                        return { color: '#3388ff', weight: 3 };
                    },
                    onEachFeature: (feature, layer) => {
                        if (layer.pm) {
                            layer.pm.enable({ allowSelfIntersection: false });
                        }
                    }
                }).addTo(map);
                mapEl.__featureGroup = fg;

                if (initialGeoJson.features && initialGeoJson.features.length) {
                    try { map.fitBounds(fg.getBounds()); } catch (e) { /* empty bounds */ }
                } else {
                    // Nieuw leeg item in een Repeater: overneem center + zoom van het
                    // meest recente sibling-item. Filament gebruikt UUIDs als repeater-keys,
                    // geen integers — zoek daarom naar het UUID-segment en vind alle andere
                    // maps met hetzelfde prefix en suffix.
                    const pathParts = _expectedStatePath.split('.');
                    const uuidRe = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
                    let uuidIdx = -1;
                    for (let i = pathParts.length - 1; i >= 0; i--) {
                        if (uuidRe.test(pathParts[i])) { uuidIdx = i; break; }
                    }
                    if (uuidIdx !== -1) {
                        const keyPrefix = 'osm-map-picker-' + pathParts.slice(0, uuidIdx).join('.') + '.';
                        const keySuffix = '.' + pathParts.slice(uuidIdx + 1).join('.');
                        const myKey = 'osm-map-picker-' + _expectedStatePath;
                        let siblingMapEl = null;
                        for (const el of document.querySelectorAll('[wire\\:key]')) {
                            const k = el.getAttribute('wire:key') || '';
                            if (k !== myKey && k.startsWith(keyPrefix) && k.endsWith(keySuffix)) {
                                const candidate = el.querySelector('[x-ref=map]');
                                if (candidate && candidate.__leafletMap) {
                                    siblingMapEl = candidate;
                                }
                            }
                        }
                        if (siblingMapEl && siblingMapEl.__leafletMap) {
                            map.setView(siblingMapEl.__leafletMap.getCenter(), siblingMapEl.__leafletMap.getZoom());
                        }
                    }
                }

                // Verwijder dotswan's eigen pm:create/edit/remove/update
                // handlers van DEZE map. Anders dual-writen ze naar de
                // gedeelde this.config.statePath via updateGeoJson() en
                // belandt elke getekende layer ook in de andere kaart's
                // state — bij reload zien beide kaarten dan dezelfde
                // geojson.
                map.off('pm:create');
                map.off('pm:edit');
                map.off('pm:remove');
                map.off('pm:update');

                // Sync onze FeatureGroup naar _expectedStatePath.
                const syncToState = async () => {
                    const features = [];
                    fg.eachLayer((layer) => {
                        if (layer.toGeoJSON) {
                            features.push(layer.toGeoJSON());
                        }
                    });
                    const current = $wire.get(_expectedStatePath) || {};
                    const center = map.getCenter();
                    // Map::make() heeft geen ->live(), dus Filament rendert
                    // het veld met wire:model.deferred. Daardoor markeert
                    // $wire.set wel dirty maar commit niet automatisch — we
                    // moeten zelf $commit() aanroepen + await zodat de
                    // polygon écht naar de server is gegaan vóór de
                    // volgende test-actie (reload, navigatie, etc.).
                    $wire.set(_expectedStatePath, {
                        ...current,
                        lat: current.lat ?? center.lat,
                        lng: current.lng ?? center.lng,
                        zoom: map.getZoom(),
                        geojson: { type: 'FeatureCollection', features },
                    });
                    await $wire.$commit();
                };

                map.on('pm:create', (e) => {
                    if (!e.layer) return;
                    // Verwijder uit dotswan's gedeelde drawItems indien aanwezig.
                    if (_self.drawItems && _self.drawItems.hasLayer && _self.drawItems.hasLayer(e.layer)) {
                        _self.drawItems.removeLayer(e.layer);
                    }
                    fg.addLayer(e.layer);
                    if (e.layer.pm) {
                        e.layer.pm.enable({ allowSelfIntersection: false });
                    }
                    e.layer.on('pm:edit', syncToState);
                    syncToState();
                });
                map.on('pm:edit', syncToState);
                map.on('pm:remove', (e) => {
                    if (e.layer && fg.hasLayer(e.layer)) {
                        fg.removeLayer(e.layer);
                    }
                    syncToState();
                });

                // Geen moveend-handler: lat/lng/zoom persistentie van de
                // map-viewport hebben we niet nodig (default-center +
                // fitBounds-na-load doen de UX al goed). Wel zit hier een
                // bug-risk: bij map.fitBounds(...) firet Leaflet meteen
                // moveend, en op dat moment kan onze ...current-spread
                // de geojson-key verliezen (Livewire-3 Proxy-spread-quirk?).
                // Resultaat: state op de server wordt overschreven met
                // {lat, lng, zoom} zonder geojson, en bij reload zijn de
                // tekeningen weg. Tot we dat veilig hebben opgelost is de
                // handler eruit.
            }, 300);

            // Cleanup: een eventueel al getekende rangeCircle weg.
            // (setMarkerRange / setCoordinates / updateGeoJson / removeMap
            // zijn al hierboven, vóór attach(), genullified.)
            if (_self.rangeCircle && _self.rangeCircle.remove) {
                _self.rangeCircle.remove();
                _self.rangeCircle = null;
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
