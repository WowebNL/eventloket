<x-filament-panels::page>
    @push('scripts')
        <script src="{{ config('services.open_forms.base_url') }}/static/sdk/open-forms-sdk.js"></script>
        <script>
            const isDebug = {{ config('app.debug') ? 'true' : 'false' }};
            const openFormsBaseUrl = '{{ config('services.open_forms.base_url') }}';
            const formSlug = 'evenementformulier-poc-kopie-a6efc0';
            const authPluginId = 'eventloket';

            (function() {
                const urlParams = new URLSearchParams(window.location.search);
                const isPostAuth = urlParams.has('_of_auth_done');

                if (!isPostAuth) {
                    // First visit: redirect to Open Forms auth before SDK loads
                    const returnUrl = new URL(window.location.href);
                    returnUrl.searchParams.set('_of_auth_done', '1');

                    const authStartUrl = new URL(`${openFormsBaseUrl}/auth/${formSlug}/${authPluginId}/start`);
                    authStartUrl.searchParams.set('next', returnUrl.toString());

                    if (isDebug) console.log('Redirecting to Open Forms auth:', authStartUrl.toString());
                    window.location.href = authStartUrl.toString();
                    return;
                }

                if (isDebug) console.log('Post-auth: loading reuse form');

                // Hide login options — auth is already done
                const style = document.createElement('style');
                style.textContent = '[class*="login-options"] { display: none !important; }';
                document.head.appendChild(style);

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
            })();

            document.addEventListener('DOMContentLoaded', function() {
                document.body.classList.add('utrecht-document', 'openforms-theme');

                const observer = new MutationObserver(function(mutationsList, observer) {
                    for(const mutation of mutationsList) {
                        if (mutation.type === 'childList') {
                            mutation.addedNodes.forEach(function(addedNode) {
                                if (addedNode.nodeType === Node.ELEMENT_NODE) {
                                    if(addedNode.classList && addedNode.classList.contains('openforms-form-navigation')) {
                                        findSaveButton();
                                    }
                                    const navigationElements = addedNode.querySelectorAll && addedNode.querySelectorAll('.openforms-form-navigation');
                                    if(navigationElements && navigationElements.length > 0) {
                                        findSaveButton();
                                    }

                                    // Hide JSON data from display
                                    if(addedNode.classList && addedNode.classList.contains('utrecht-data-list__item-value')) {
                                        if(addedNode.textContent && addedNode.textContent.includes('{"type":')) {
                                            addedNode.style.display = 'none';
                                        }
                                    }
                                    const dataListElements = addedNode.querySelectorAll && addedNode.querySelectorAll('.utrecht-data-list__item-value');
                                    if(dataListElements && dataListElements.length > 0) {
                                        dataListElements.forEach(function(element) {
                                            if(element.textContent && element.textContent.includes('{"type":')) {
                                                element.style.display = 'none';
                                            }
                                        });
                                    }
                                }
                            });
                        }
                    }
                });

                observer.observe(document.body, { childList: true, subtree: true });

                function setEmail() {
                    setTimeout(function() {
                        let emails = document.getElementsByName('email');
                        if(emails.length > 0 && emails[0]) {
                            const emailField = emails[0];
                            const emailForm = emailField.closest('form');

                            emailField.setAttribute('value', '{{ auth()->user()->email }}');
                            emailField.dispatchEvent(new Event('input', { bubbles: true }));
                            emailField.dispatchEvent(new Event('change', { bubbles: true }));
                            emailField.dispatchEvent(new Event('blur', { bubbles: true }));

                            emailForm.addEventListener('submit', function() {
                                @this.formSaved();
                            });
                        }
                    }, 100);
                }

                function findSaveButton() {
                    var savebtns = document.getElementsByClassName('openforms-form-navigation__save-button');
                    Array.from(savebtns).forEach(function(button, index) {
                        if (!button.hasAttribute('data-email-listener')) {
                            button.addEventListener("click", function() {
                                setEmail();
                            });
                            button.setAttribute('data-email-listener', 'true');
                        }
                    });
                }
            });
        </script>
    @endpush
    @push('styles')
        <link rel="stylesheet" href="{{ config('services.open_forms.base_url') }}/static/sdk/open-forms-sdk.css" />
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
        const isDebug = {{ config('app.debug') ? 'true' : 'false' }};

        $js('loadForm', function() {
            if (isDebug) console.log('Loading reuse form');
            const targetNode = document.getElementById('openforms-root');
            if (targetNode && window.OpenForms) {
                const form = new window.OpenForms.OpenForm(targetNode, targetNode.dataset);
                form.init();
            }
        });
    </script>
    @endscript

</x-filament-panels::page>
