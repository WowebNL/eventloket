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
    @if ($unreadableDocumentCount > 0)
        <x-incomplete-read-notice
            :title="trans_choice('resources/zaak.documents.unreadable.title', $unreadableDocumentCount, ['count' => $unreadableDocumentCount])"
            :description="__('resources/zaak.documents.unreadable.description')"
        />
    @endif

    {{ $this->table }}
</div>
