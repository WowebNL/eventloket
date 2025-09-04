<x-filament-panels::layout.simple>
    <div class="fi-simple-page-content">
        <x-filament-panels::header.simple
            :heading="$heading"
            :subheading="$subheading"
        />

        <x-filament::button
            tag="a"
            href="{{ route('welcome') }}"
        >
            {{ __('errors/invite-not-found.action') }}
        </x-filament::button>
    </div>
</x-filament-panels::layout.simple>
