<div>
    @if ($unreadableDocumentCount > 0)
        <x-incomplete-read-notice
            :title="trans_choice('resources/zaak.besluiten.unreadable.title', $unreadableDocumentCount, ['count' => $unreadableDocumentCount])"
            :description="__('resources/zaak.besluiten.unreadable.description')"
        />
    @endif

    {{ $this->infolist }}
</div>
