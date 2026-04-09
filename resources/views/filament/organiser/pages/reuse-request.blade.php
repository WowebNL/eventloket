<x-filament-panels::page>
    @push('scripts')
        <script src="{{ config('services.open_forms.base_url') }}/static/sdk/open-forms-sdk.js"></script>
        <script>
            const isDebug = {{ config('app.debug') ? 'true' : 'false' }};

            // Auto-click "Formulier starten" when it appears
            document.addEventListener('DOMContentLoaded', function() {
                const autoStartObserver = new MutationObserver(function() {
                    const buttons = document.querySelectorAll('#openforms-root button[type="submit"]');
                    for (const btn of buttons) {
                        if (isDebug) console.log('Found button:', btn.textContent.trim());
                        autoStartObserver.disconnect();
                        setTimeout(() => {
                            if (isDebug) console.log('Auto-clicking start button');
                            btn.click();
                        }, 1000);
                        return;
                    }
                });
                autoStartObserver.observe(document.body, { childList: true, subtree: true });
            });
        </script>
        @include('filament.organiser.partials.openforms-form-helpers')
    @endpush
    @push('styles')
        <link rel="stylesheet" href="{{ config('services.open_forms.base_url') }}/static/sdk/open-forms-sdk.css" />
        <style>
            [class*="login-options"] { display: none !important; }
        </style>
    @endpush

    <div wire:ignore>
        <div
            id="openforms-root"
            data-base-url="{{ config('services.open_forms.base_url') }}/api/v2/"
            data-form-id="{{ $formId }}"
            @if($initialDataReference)
                data-initial-data-reference="{{ $initialDataReference }}"
            @endif
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
