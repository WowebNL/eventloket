<div>
    @if ($unavailableDocumentCount > 0)
        <x-incomplete-read-notice
            :title="trans_choice('resources/zaak.besluiten.unavailable.title', $unavailableDocumentCount, ['count' => $unavailableDocumentCount])"
            :description="__('resources/zaak.besluiten.unavailable.description')"
        />
    @endif

    {{-- No count here, deliberately: see BesluitenInfolist::$hasForbiddenDocuments. --}}
    @if ($hasForbiddenDocuments)
        <x-incomplete-read-notice
            :title="__('resources/zaak.besluiten.forbidden.title')"
            :description="__('resources/zaak.besluiten.forbidden.description')"
        />
    @endif

    {{ $this->infolist }}
</div>
