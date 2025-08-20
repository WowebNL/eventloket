<x-filament-panels::page.simple>

    @if(auth()->user()->organisations()->doesntExist())
        <x-slot name="subheading">
            {{ __('filament-panels::auth/pages/register.actions.login.before') }}

            {{ $this->noOrganisationAction }}
        </x-slot>
    @endif

    {{ $this->content }}
</x-filament-panels::page.simple>
