<div>
    <form wire:submit="submit">
        {{ $this->form }}

        @if(!empty($this->documents))
            <div class="mt-2 space-y-2">
                @foreach($this->resolvedDocuments as $item)
                <x-filament::card :compact="true">
                    <div class="flex gap-4 flex-col sm:flex-row justify-between">
                        <div class="flex gap-2 items-center">
                            <x-heroicon-s-document class="shrink-0 size-8 text-gray-400"/>
                            <div>
                                <p class="text-sm font-medium text-gray-700 dark:text-white/80 whitespace-nowrap overflow-hidden text-ellipsis">
                                    {{ $item['document']->titel }}
                                </p>
                                <p class="text-sm text-gray-500">
                                    {{ $item['document']->bestandsnaam }}
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-3">

                        </div>
                    </div>
                </x-filament::card>
                @endforeach
            </div>
        @endif

        <div class="mt-4 flex items-center justify-end space-x-4">
            {{ $this->attachAction }}

            <x-filament::button type="submit" icon="heroicon-o-paper-airplane">
                {{ __('Bericht versturen') }}
            </x-filament::button>
        </div>
    </form>

    <x-filament-actions::modals/>
</div>
