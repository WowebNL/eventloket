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
    {{ $this->table }}
</div>
