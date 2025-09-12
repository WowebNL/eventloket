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
    {{-- <script>
        // localStorage.setItem("{{ $formId }}", '"550e3a0d-64cd-4809-b75c-5649613504ad"');
        /** 
         * 1. chech local storage for sumbission id every second
         * 2. if found save it on the user 
         * 3. on init contact step call eventloket endpoint to get user data 
        */
        const interval = setInterval(function() {
            let submission = localStorage.getItem("{{ $formId }}");
            if(submission) {
                @this.updateFormsubmissionSession(submission);
                stopInterval();
            }
        }, 1000);

        function stopInterval() {
            clearInterval(interval);
        }
    </script> --}}
    @script
    <script>
        $js('checkLocalStorage', async function() {
            let submission = localStorage.getItem("{{ $formId }}");
            if(submission) {
                @this.checkSubmissionSession(submission);
            } else {
                @this.checkLoadExistingSubmissionSession();
            }
        });

        {{-- @TODO refactor but there is no event so we need to check till the form uuid is added --}}
        $js('listenLocalStorage', function() {
            console.log('listenLocalStorage');
            const interval = setInterval(function() {
                let submission = localStorage.getItem("{{ $formId }}");
                if(submission) {
                    @this.updateFormsubmissionSession(submission);
                    clearInterval(interval);
                }
            }, 1000);
        });

        $js('loadFormWithRef', function(submission) {
            localStorage.setItem("{{ $formId }}", submission);
            @this.$js.checkIfSubmissionChanges(submission);
            @this.$js.loadForm();
        });
        $js('deleteStorageRef', function(submission) {
            localStorage.removeItem("{{ $formId }}");
        });

        $js('checkIfSubmissionChanges', function (submission) {
            const interval = setInterval(function() {
                let currentSubmission = localStorage.getItem("{{ $formId }}");
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
