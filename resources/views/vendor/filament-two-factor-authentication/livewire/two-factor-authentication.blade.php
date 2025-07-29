<div>
    <x-filament::section :aside="$aside">
        <x-slot name="heading">
            {{__('filament-two-factor-authentication::section.header')}}
        </x-slot>

        <x-slot name="description">
            {{__('filament-two-factor-authentication::section.description')}}
        </x-slot>

        <div class="">
            @if($this->isConfirmingSetup)
                <x-filament-two-factor-authentication::setup-confirmation />
            @elseif($this->enableTwoFactorAuthentication->isVisible())
                <x-filament-two-factor-authentication::enable />
            @elseif($this->disableTwoFactorAuthentication->isVisible())
                <x-filament-two-factor-authentication::enabled />

                @if($this->showRecoveryCodes)
                    <x-filament-two-factor-authentication::recovery-codes />
                @endif

                <div class="flex flex-wrap gap-4">
                    {{$this->generateNewRecoveryCodes}}

                    {{$this->disableTwoFactorAuthentication}}

                    @if(auth()->user()->hasEnabledTwoFactorAuthentication())
                        <x-filament::button
                            class="w-full"
                            tag="a"
                            :href="filament()->getCurrentPanel()->getUrl(filament()->getTenant())"
                        >
                            {{__('filament-two-factor-authentication::section.dashboard')}}
                        </x-filament::button>
                    @endif
                </div>
            @endif
        </div>
    </x-filament::section>

    <x-filament-actions::modals />
</div>
