<x-filament-panels::page.simple>
    @if(auth()->check())
        <x-filament::button wire:click="acceptInvite">
            {{ __('organiser/pages/auth/accept-organisation-invite.button') }}
        </x-filament::button>
    @else
        <form wire:submit="create">
            {{ $this->form }}

            <x-filament::button type="submit">
                {{ __('organiser/pages/auth/accept-organisation-invite.button') }}
            </x-filament::button>
        </form>
    @endif
</x-filament-panels::page.simple>
