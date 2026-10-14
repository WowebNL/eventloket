<div
    x-data="{
        _stopped: false,
        init() {
            const self = this;
            const poll = async () => {
                if (self._stopped) return;
                await $wire.$refresh();
                if (!self._stopped) {
                    setTimeout(poll, $wire.hasDocuments ? 30000 : 5000);
                }
            };
            setTimeout(poll, $wire.hasDocuments ? 30000 : 5000);
        },
        destroy() {
            this._stopped = true;
        }
    }"
>
    @if ($unavailableDocumentCount > 0)
        <x-incomplete-read-notice
            :title="trans_choice('resources/zaak.documents.unavailable.title', $unavailableDocumentCount, ['count' => $unavailableDocumentCount])"
            :description="__('resources/zaak.documents.unavailable.description')"
        />
    @endif

    {{-- No count here, deliberately: see ZaakDocumentsTable::$hasForbiddenDocuments. --}}
    @if ($hasForbiddenDocuments)
        <x-incomplete-read-notice
            :title="__('resources/zaak.documents.forbidden.title')"
            :description="__('resources/zaak.documents.forbidden.description')"
        />
    @endif

    {{ $this->table }}
</div>
