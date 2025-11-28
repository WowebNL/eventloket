<x-filament-panels::page>
    @push('scripts')
        <script src="{{ config('services.open_forms.base_url') }}/static/sdk/open-forms-sdk.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.body.classList.add('utrecht-document', 'openforms-theme');
                const observer = new MutationObserver(function(mutationsList, observer) {
                    for(const mutation of mutationsList) {
                        if (mutation.type === 'childList') {
                            mutation.addedNodes.forEach(function(addedNode) {
                                if (addedNode.nodeType === Node.ELEMENT_NODE) {
                                    // Check if the added node itself has the class
                                    if(addedNode.classList && addedNode.classList.contains('openforms-form-navigation')) {
                                        console.log('Navigation added');
                                        findSaveButton();
                                    }
                                    // Check if any descendant has the class
                                    const navigationElements = addedNode.querySelectorAll && addedNode.querySelectorAll('.openforms-form-navigation');
                                    if(navigationElements && navigationElements.length > 0) {
                                        console.log('Navigation found in descendants');
                                        findSaveButton();
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
                            
                            console.log('Email field:', emailField);
                            console.log('Email form:', emailForm);
                            
                            emailField.setAttribute('value', '{{ auth()->user()->email }}');
                            // Trigger change event to ensure form validation updates
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
    <div wire:init="$js.checkLocalStorage()"></div>

    <div wire:ignore>
            <div
                id="openforms-root"
                data-base-url="{{ config('services.open_forms.base_url') }}/api/v2/"
                data-form-id="{{ $formId }}"
                data-lang="nl"
            ></div>

        </div>
        
    @script
    <script>
        $js('checkLocalStorage', function() {
            let submission = sessionStorage.getItem("{{ $formId }}");
            if(submission) {
                @this.checkSubmissionSession(submission);
            } else {
                @this.checkLoadExistingSubmissionSession();
            }
        });

        {{-- @TODO refactor but there is no event so we need to check till the form uuid is added --}}
        $js('listenLocalStorage', function() {
            const interval = setInterval(function() {
                console.log('listenLocalStorage');
                let submission = sessionStorage.getItem("{{ $formId }}");
                if(submission) {
                    @this.updateFormsubmissionSession(submission);
                    @this.$js.checkIfSubmissionChanges(submission);
                    clearInterval(interval);
                }
            }, 1000);
        });

        $js('loadFormWithRef', function(submission) {
            sessionStorage.setItem("{{ $formId }}", submission);
            @this.$js.checkIfSubmissionChanges(submission);
            @this.$js.loadForm();
        });
        $js('deleteStorageRef', function(submission) {
            sessionStorage.removeItem("{{ $formId }}");
        });

        $js('checkIfSubmissionChanges', function (submission) {
            const interval = setInterval(function() {
                console.log('checkIfSubmissionChanges');
                let currentSubmission = sessionStorage.getItem("{{ $formId }}");
                if(currentSubmission && currentSubmission !== submission) {
                    @this.updateFormsubmissionSession(currentSubmission);
                }
            }, 5000);
        });

        $js('loadForm', function() {
            var targetNode = document.getElementById('openforms-root');
            var form = new OpenForms.OpenForm(targetNode, targetNode.dataset);
            form.init();
        });
    </script>
    @endscript

</x-filament-panels::page>
