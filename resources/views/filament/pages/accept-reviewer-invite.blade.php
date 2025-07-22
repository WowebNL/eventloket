<x-filament-panels::page.simple>
    @if(auth()->check())
        <x-filament::button wire:click="acceptInvite">
            {{ __('admin/pages/auth/accept-reviewer-invite.button') }}
        </x-filament::button>
    @else
        <x-filament-panels::form wire:submit="create">
            {{ $this->form }}

            <x-filament::button type="submit">
                {{ __('admin/pages/auth/accept-reviewer-invite.button') }}
            </x-filament::button>
        </x-filament-panels::form>
    @endif
</x-filament-panels::page.simple>
