{{--
    Map picker field view.

    Same markup as the upstream field, with one difference: the Alpine object
    is `basemapMapPicker`, which wraps the upstream `mapPicker` and adds the
    two behaviours this application needs (see the script below).
--}}
@once
    <script>
        window.basemapMapPicker = function ($wire, config) {
            const picker = window.mapPicker($wire, config)

            const baseInit = picker.init
            const baseCreateMap = picker.createMap
            const baseUpdateGeoJson = picker.updateGeoJson
            const baseSetCoordinates = picker.setCoordinates

            const fingerprint = function (value) {
                try {
                    return JSON.stringify(value ?? null)
                } catch (error) {
                    return null
                }
            }

            return Object.assign(picker, {
                renderedGeoJson: null,

                init: function () {
                    baseInit.call(this)

                    this.renderedGeoJson = fingerprint(this.getGeoJson())

                    // The upstream field reads the geometry out of the state
                    // once, while building the map, and its own watcher only
                    // moves the marker. Geometry that reaches the state from
                    // the server instead of from a drawing action -- a route
                    // read out of an uploaded file, for instance -- would
                    // therefore never show up until the page was loaded again.
                    $wire.watch(config.statePath, () => {
                        const incoming = fingerprint(this.getGeoJson())

                        if (incoming === this.renderedGeoJson) {
                            return
                        }

                        this.renderedGeoJson = incoming

                        this.redrawGeoJson()
                    })
                },

                createMap: function (el) {
                    baseCreateMap.call(this, el)

                    this.renderedGeoJson = fingerprint(this.getGeoJson())
                },

                updateGeoJson: function () {
                    baseUpdateGeoJson.call(this)

                    this.renderedGeoJson = fingerprint(this.getGeoJson())
                },

                // Moving the map writes the new centre back to the state as a
                // bare `{lat, lng}`, which drops whatever geometry the same
                // state key was holding. Carry the geometry along, so moving
                // the map cannot lose a drawn or loaded route.
                //
                // Everything else this method does is left as the upstream
                // object does it, including the live-location refresh below:
                // only the value written to the state differs.
                setCoordinates: function (coords) {
                    const current = $wire.get(config.statePath)
                    const geojson = (current && typeof current === 'object') ? current.geojson : undefined

                    if (geojson === undefined) {
                        return baseSetCoordinates.call(this, coords)
                    }

                    this.setFormRestorationState(coords)

                    if (config.type === 'field') {
                        $wire.set(config.statePath, {
                            lat: coords.lat,
                            lng: coords.lng,
                            geojson: geojson,
                        })

                        this.renderedGeoJson = fingerprint(geojson)
                    }

                    if (this.config.liveLocation && this.config.liveLocation.send) {
                        $wire.refresh()
                    }

                    return coords
                },

                redrawGeoJson: function () {
                    const el = (this.$refs && this.$refs.map) ? this.$refs.map : null

                    if (!el) {
                        return
                    }

                    if (this.map) {
                        this.removeMap(el)
                    }

                    this.createMap(el)
                },
            })
        }
    </script>
@endonce

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div x-data="basemapMapPicker($wire, {{ $getMapConfig() }})"
            x-init="async () => {
            do {
                await (new Promise(resolve => setTimeout(resolve, 100)));
            } while (!$refs.map);
            attach($refs.map);
        }"
            wire:ignore
    >
        <div
            x-ref="map"
            class="w-full" style="min-height: 30vh; {{ $getExtraStyle() }}">
        </div>
        <input type="text" id="{{ $getStatePath() }}_fmrest" style="display:none"/>
    </div>
</x-dynamic-component>
