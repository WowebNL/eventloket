<x-filament-widgets::widget>
    <x-filament::section>
        <div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                {{ __('organiser/widgets/shortlink.title') }}
            </h3>
            <p class="dark:text-gray-40 text-sm text-gray-500">
                {{ __('organiser/widgets/shortlink.description') }}
            </p>
        </div>
        <div class="mt-6">
            <div>
                <x-filament::button href="{{ route('filament.organiser.pages.new-request', ['tenant' => Filament\Facades\Filament::getTenant()->id]) }}" size="xl" icon="heroicon-o-document-text" tag="a">
                    {{ __('organiser/widgets/shortlink.button') }}
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
