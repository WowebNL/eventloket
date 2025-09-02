<div>
    <x-filament-panels::page.simple>
        @if(auth()->check())
            <x-filament::button wire:click="acceptInvite">
                {{ __('advisor/pages/auth/accept-advisory-invite.button') }}
            </x-filament::button>
        @else
            <form wire:submit="create">
                {{ $this->form }}

            <div class="mt-6">
                <x-filament::button type="submit">
                    {{ __('advisor/pages/auth/accept-advisory-invite.button') }}
                </x-filament::button>
            </div>
            </form>
        @endif
    </x-filament-panels::page.simple>
</div>
