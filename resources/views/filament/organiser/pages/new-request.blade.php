<x-filament-panels::page>
    @push('scripts')
        <script src="{{ config('services.open_forms.base_url') }}/static/sdk/open-forms-sdk.js"></script>
    @endpush
    @push('styles')
        <link rel="stylesheet" href="{{ config('services.open_forms.base_url') }}/static/sdk/open-forms-sdk.css" />
    @endpush

    <div
        id="openforms-root"
        class="utrecht-document openforms-theme"
        data-base-url="{{ config('services.open_forms.base_url') }}/api/v2/"
        data-form-id="{{ $formId }}"
        data-lang="nl"
        data-use-hash-routing="true"
    ></div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
        var targetNode = document.getElementById('openforms-root');
        var form = new OpenForms.OpenForm(targetNode, targetNode.dataset);
        form.init()
        // TODO send base info about loged in user to submission
        // .then(function() {
        //     fetch(targetNode.dataset.baseUrl + 'submissions',{
        //         method: 'GET',
        //         credentials: 'include'
        //         }).then(function(response) {
        //                         return response.json();
        //                     }).then(function(data) {
        //                         console.log(data);
        //                     }).catch(function(error) {
        //                         console.error('Error fetching submissions:', error);
        //                     });
        //                 });
    });
    </script>
</x-filament-panels::page>
