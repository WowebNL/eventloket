{{-- Shared DOM observers for Open Forms SDK: JSON hiding, email prefill, save button tracking --}}
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.body.classList.add('utrecht-document', 'openforms-theme');

        const observer = new MutationObserver(function(mutationsList) {
            for (const mutation of mutationsList) {
                if (mutation.type !== 'childList') continue;

                mutation.addedNodes.forEach(function(addedNode) {
                    if (addedNode.nodeType !== Node.ELEMENT_NODE) return;

                    if (addedNode.classList && addedNode.classList.contains('openforms-form-navigation')) {
                        findSaveButton();
                    }
                    const navElements = addedNode.querySelectorAll && addedNode.querySelectorAll('.openforms-form-navigation');
                    if (navElements && navElements.length > 0) {
                        findSaveButton();
                    }

                    // Hide JSON data from display
                    if (addedNode.classList && addedNode.classList.contains('utrecht-data-list__item-value')) {
                        if (addedNode.textContent && addedNode.textContent.includes('{"type":')) {
                            addedNode.style.display = 'none';
                        }
                    }
                    const dataListElements = addedNode.querySelectorAll && addedNode.querySelectorAll('.utrecht-data-list__item-value');
                    if (dataListElements && dataListElements.length > 0) {
                        dataListElements.forEach(function(element) {
                            if (element.textContent && element.textContent.includes('{"type":')) {
                                element.style.display = 'none';
                            }
                        });
                    }
                });
            }
        });

        observer.observe(document.body, { childList: true, subtree: true });

        function setEmail() {
            setTimeout(function() {
                let emails = document.getElementsByName('email');
                if (emails.length > 0 && emails[0]) {
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
            Array.from(savebtns).forEach(function(button) {
                if (!button.hasAttribute('data-email-listener')) {
                    button.addEventListener('click', function() {
                        setEmail();
                    });
                    button.setAttribute('data-email-listener', 'true');
                }
            });
        }
    });
</script>
