<x-filament-panels::page>
    @push('scripts')
        <script src="{{ config('services.open_forms.base_url') }}/static/sdk/open-forms-sdk.js"></script>
        <script>
            const isDebug = {{ config('app.debug') ? 'true' : 'false' }};
            const formId = '{{ $formId }}';
            let summaryProgressFound = false;
            
            document.addEventListener('DOMContentLoaded', function() {
                document.body.classList.add('utrecht-document', 'openforms-theme');
                const observer = new MutationObserver(function(mutationsList, observer) {
                    for(const mutation of mutationsList) {
                        if (mutation.type === 'childList') {
                            mutation.addedNodes.forEach(function(addedNode) {
                                if (addedNode.nodeType === Node.ELEMENT_NODE) {
                                    // Check if the added node itself has the class
                                    if(addedNode.classList && addedNode.classList.contains('openforms-form-navigation')) {
                                        if (isDebug) console.log('Navigation added');
                                        findSaveButton();
                                    }
                                    // Check if any descendant has the class
                                    const navigationElements = addedNode.querySelectorAll && addedNode.querySelectorAll('.openforms-form-navigation');
                                    if(navigationElements && navigationElements.length > 0) {
                                        if (isDebug) console.log('Navigation found in descendants');
                                        findSaveButton();
                                    }
                                    
                                    // Check for utrecht-data-list__item-value elements containing JSON
                                    if(addedNode.classList && addedNode.classList.contains('utrecht-data-list__item-value')) {
                                        if(addedNode.textContent && addedNode.textContent.includes('{"type":')) {
                                            addedNode.style.display = 'none';
                                        }
                                    }
                                    // Check descendants for utrecht-data-list__item-value
                                    const dataListElements = addedNode.querySelectorAll && addedNode.querySelectorAll('.utrecht-data-list__item-value');
                                    if(dataListElements && dataListElements.length > 0) {
                                        dataListElements.forEach(function(element) {
                                            if(element.textContent && element.textContent.includes('{"type":')) {
                                                element.style.display = 'none';
                                            }
                                        });
                                    }

                                    // Check for login button container and attach click listener to first button
                                    const loginContainers = addedNode.classList && addedNode.classList.contains('openforms-login-button')
                                        ? [addedNode]
                                        : (addedNode.querySelectorAll ? Array.from(addedNode.querySelectorAll('.openforms-login-button')) : []);

                                    if (loginContainers.length > 0) {
                                        loginContainers.forEach(function(container) {
                                            const firstButton = container.querySelector('button');
                                            if (firstButton && !firstButton.hasAttribute('data-login-listener')) {
                                                firstButton.addEventListener('click', function() {
                                                    if(isDebug) console.log('clicked login button');
                                                    
                                                    const waitForSummary = setInterval(function() {
                                                        if (summaryProgressFound) {
                                                            clearInterval(waitForSummary);
                                                            const currentSubmission = sessionStorage.getItem(formId);
                                                            if (currentSubmission) {
                                                                if(isDebug) console.log('Update vanaf click listener ref:', currentSubmission);
                                                                @this.updateFormsubmissionSession(currentSubmission);
                                                            }
                                                        }
                                                    }, 100);
                                                });
                                                firstButton.setAttribute('data-login-listener', 'true');
                                            }
                                        });
                                    }

                                    // Check for openforms-summary-progress to set flag
                                    if(addedNode.classList && addedNode.classList.contains('openforms-summary-progress')) {
                                        summaryProgressFound = true;
                                        if (isDebug) console.log('Summary progress found');
                                    }
                                    const summaryProgressElements = addedNode.querySelectorAll && addedNode.querySelectorAll('.openforms-summary-progress');
                                    if(summaryProgressElements && summaryProgressElements.length > 0) {
                                        summaryProgressFound = true;
                                        if (isDebug) console.log('Summary progress found in descendants');
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
                            
                            if (isDebug) {
                                console.log('Email field:', emailField);
                                console.log('Email form:', emailForm);
                            }
                            
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
    {{-- TDODO needs cleanup but leave it here for now if version gets declined --}}
    <script>
        const isDebug = {{ config('app.debug') ? 'true' : 'false' }};
        const intervals = new Map();
        const startTimes = new Map();
        const maxDuration = 10 * 60 * 1000; // 10 minutes
        let isPageVisible = true;
        let currentSubmission = null;
        const formId = '{{ $formId }}';
        
        document.addEventListener('visibilitychange', () => {
            isPageVisible = !document.hidden;
            if (isDebug) console.log('Page visibility:', isPageVisible ? 'visible' : 'hidden');
        });
        
        window.addEventListener('beforeunload', () => {
            clearAllIntervals();
        });

        $js('listenLocalStorage', function() {
            createInterval('listenLocalStorage', () => {
                if (isDebug) console.log('listenLocalStorage check');
                let submission = sessionStorage.getItem(formId);
                if (submission) {
                    currentSubmission = submission;
                    @this.updateFormsubmissionSession(submission);
                    @this.$js.checkIfSubmissionChanges(submission);
                    clearIntervalByName('listenLocalStorage');
                }
            }, 1000);
        });
        
        $js('loadFormWithRef', function(submission) {
            currentSubmission = submission;
            sessionStorage.setItem(formId, submission);
            @this.$js.loadForm();
            if(isDebug) console.log('Loaded form with submission ref:', submission);
        });
        
        $js('deleteStorageRef', function() {
            sessionStorage.removeItem(formId);
            currentSubmission = null;
            clearAllIntervals();
        });
        
        $js('checkIfSubmissionChanges', function(submission) {
            createInterval('checkIfSubmissionChanges', () => {
                if (isDebug) console.log('checkIfSubmissionChanges check');
                let currentSubmission = sessionStorage.getItem(formId);
                if (currentSubmission != null && currentSubmission !== submission) {
                    if (isDebug) console.log('Submission changed in storage:', currentSubmission);
                    @this.updateFormsubmissionSession(currentSubmission);
                }
            }, 5000);
        });
        
        $js('loadForm', function() {
            if (isDebug) console.log('Loading form');
            const targetNode = document.getElementById('openforms-root');
            if (targetNode && window.OpenForms) {
                const form = new window.OpenForms.OpenForm(targetNode, targetNode.dataset);
                form.init();
            }
        });
        
        function createInterval(name, callback, delay) {
            // Clear existing interval with the same name
            if (intervals.has(name)) {
                clearInterval(intervals.get(name));
                intervals.delete(name);
                startTimes.delete(name);
            }
            
            const startTime = Date.now();
            startTimes.set(name, startTime);
            
            const wrappedCallback = () => {
                // Check if 10 minutes have passed
                if (Date.now() - startTime > maxDuration) {
                    if (isDebug) console.log(`Interval "${name}" reached 10-minute limit, stopping.`);
                    clearIntervalByName(name);
                    return;
                }
                
                // Only execute if page is visible
                if (isPageVisible) {
                    callback();
                }
            };
            
            const interval = setInterval(wrappedCallback, delay);
            intervals.set(name, interval);
            if (isDebug) console.log(`Interval "${name}" created with ${delay}ms delay`);
        }
        
        function clearIntervalByName(name) {
            const interval = intervals.get(name);
            if (interval) {
                clearInterval(interval);
                intervals.delete(name);
                startTimes.delete(name);
                if (isDebug) console.log(`Cleared interval: "${name}"`);
            }
        }
        
        function clearAllIntervals() {
            intervals.forEach((interval) => {
                clearInterval(interval);
            });
            intervals.clear();
            startTimes.clear();
            if (isDebug) console.log('All intervals cleared');
        }
        </script>
    @endscript
        
</x-filament-panels::page>
