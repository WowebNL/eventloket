<x-filament-panels::page>
    @push('scripts')
        <script src="{{ config('services.open_forms.base_url') }}/static/sdk/open-forms-sdk.js"></script>
        @include('filament.organiser.partials.openforms-auth-redirect', ['autoStart' => 'false'])
        @include('filament.organiser.partials.openforms-form-helpers')
    @endpush
    @push('styles')
        <link rel="stylesheet" href="{{ config('services.open_forms.base_url') }}/static/sdk/open-forms-sdk.css" />
        <style>
            .openforms-login-options .openforms-login-button:has(a) { display: none !important; }
        </style>
    @endpush

    <div wire:ignore>
        <div
            id="openforms-root"
            data-base-url="{{ config('services.open_forms.base_url') }}/api/v2/"
            data-form-id="{{ $formId }}"
            data-lang="nl"
        ></div>
    </div>

    <div wire:init="checkInitialLoad()"></div>

    @script
    <script>
        $js('loadForm', function() {
            const targetNode = document.getElementById('openforms-root');
            if (targetNode && window.OpenForms) {
                new window.OpenForms.OpenForm(targetNode, targetNode.dataset).init();
            }
        });
    </script>
    @endscript

</x-filament-panels::page>
