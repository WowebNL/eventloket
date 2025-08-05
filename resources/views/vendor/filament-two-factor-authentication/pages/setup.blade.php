@php
    use Stephenjude\FilamentTwoFactorAuthentication\Livewire\PasskeyAuthentication;
    use Stephenjude\FilamentTwoFactorAuthentication\Livewire\TwoFactorAuthentication;
    use Stephenjude\FilamentTwoFactorAuthentication\TwoFactorAuthenticationPlugin;

    $plugin = TwoFactorAuthenticationPlugin::get();

    /** @var \Filament\Models\Contracts\FilamentUser $user */
    $user = filament()->auth()->user();
@endphp
<x-filament-panels::page.simple>

    @if($plugin->hasEnabledTwoFactorAuthentication())
        @livewire(TwoFactorAuthentication::class, ['aside' => false, 'redirectTo' => filament()->getCurrentPanel()->getUrl(filament()->getTenant())])
    @endif

    @if($plugin->hasEnabledPasskeyAuthentication())
        @livewire(PasskeyAuthentication::class, ['aside' => false])
    @endif

</x-filament-panels::page.simple>
