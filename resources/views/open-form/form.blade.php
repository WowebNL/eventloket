<x-layout.open-forms>
    <!-- Load an Open Forms form and render it -->
    <div
        id="openforms-root"
        data-base-url="{{ config('services.open_forms.base_url') }}/api/v2/"
        data-form-id="{{ $formId }}"
        data-lang="nl"
    ></div>

    <script>
        var targetNode = document.getElementById('openforms-root');
        var form = new OpenForms.OpenForm(targetNode, targetNode.dataset);
        form.init();
    </script>
</x-layout.open-forms>
