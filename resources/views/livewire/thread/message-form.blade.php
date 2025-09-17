<div>
    <form wire:submit="submit">
        {{ $this->form }}

        <div class="mt-4 flex items-center justify-end space-x-4">
            <x-filament::button icon="heroicon-o-paper-clip" color="gray">
                Bestand bijvoegen
            </x-filament::button>

            <x-filament::button type="submit" icon="heroicon-o-paper-airplane">
                Comment
            </x-filament::button>
        </div>
    </form>

    <x-filament-actions::modals />
</div>
