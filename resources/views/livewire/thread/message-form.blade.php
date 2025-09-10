<div>
    <form wire:submit="submit">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit">
                Bestand bijvoegen
            </x-filament::button>
            
            <x-filament::button type="submit" icon="heroicon-o-paper-airplane">
                Comment
            </x-filament::button>
        </div>
    </form>

    <x-filament-actions::modals />
</div>
