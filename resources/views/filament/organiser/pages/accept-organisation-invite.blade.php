<x-filament-panels::page.simple>
    @if(auth()->check())

        <x-filament::button wire:click="acceptInvite">
            Accept Invite
        </x-filament::button>
    @else
        <x-filament-panels::form wire:submit="create">
            {{ $this->form }}

            <x-filament::button type="submit">
                Accept Invite
            </x-filament::button>

            {{--        <x-filament-panels::form.actions--}}
            {{--            :actions="$this->getCachedFormActions()"--}}
            {{--            :full-width="true"--}}
            {{--        />--}}
        </x-filament-panels::form>
    @endif
</x-filament-panels::page.simple>
