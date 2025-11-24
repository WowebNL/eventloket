<x-filament-panels::page>
    @push('scripts')
        <script src="{{ config('services.open_forms.base_url') }}/static/sdk/open-forms-sdk.js"></script>
    @endpush
    @push('styles')
        <link rel="stylesheet" href="{{ config('services.open_forms.base_url') }}/static/sdk/open-forms-sdk.css" />
    @endpush
    <div wire:init="$js.checkLocalStorage()"></div>

    <div wire:ignore>
            <div
                id="openforms-root"
                class="utrecht-document openforms-theme"
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
