<x-filament::card :compact="true">
    <div class="flex gap-4 flex-col sm:flex-row justify-between">
        <div class="flex gap-2 items-center">
            <x-heroicon-s-document class="shrink-0 size-8 text-gray-400"/>
            <div>
                <p class="text-sm font-semibold text-gray-900 dark:text-white/80 whitespace-nowrap overflow-hidden text-ellipsis">
                    {{ $this->document->titel }}
                </p>
                <p class="text-sm text-gray-500">
                    {{ $this->document->bestandsnaam }}
                </p>
            </div>
        </div>
        <div class="flex gap-3">
            {{ $this->viewAction }}
            {{ $this->downloadAction }}
        </div>
    </div>
    <p class="sm:ml-10 text-xs text-gray-500 mt-2 sm:mt-1">
        Aangemaakt op: {{ \Illuminate\Support\Carbon::parse($this->document->creatiedatum)->translatedFormat('j M Y') }}
        <span class="inline-block size-0.5 mx-1 bg-gray-400 rounded-full align-middle"></span>
        Auteur: {{ $this->document->auteur }}
        <span class="inline-block size-0.5 mx-1 bg-gray-400 rounded-full align-middle"></span>
        <span>Versie: {{ $this->versie }}@if($this->versie != $this->latestVersion) (Nieuwste versie is {{ $this->latestVersion }})@endif</span>
        <span class="inline-block size-0.5 mx-1 bg-gray-400 rounded-full align-middle"></span>
        <span>Type: {{ $this->zaak->zaaktype->document_types->firstWhere('url', $this->document->informatieobjecttype)->omschrijving ?? ''  }}</span>
    </p>
</x-filament::card>
