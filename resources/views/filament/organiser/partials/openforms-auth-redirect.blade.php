{{-- Auth redirect: redirects to Open Forms auth endpoint before the SDK loads --}}
<script>
    const isDebug = {{ config('app.debug') ? 'true' : 'false' }};
    const openFormsBaseUrl = '{{ config('services.open_forms.base_url') }}';
    const formSlug = '{{ config('services.open_forms.form_slug') }}';
    const authPluginId = 'eventloket';
    const autoStart = {{ $autoStart ?? 'false' }};

    (function() {
        const urlParams = new URLSearchParams(window.location.search);
        const isPostAuth = urlParams.has('_of_auth_done');

        if (!isPostAuth) {
            const returnUrl = new URL(window.location.href);
            returnUrl.searchParams.set('_of_auth_done', '1');

            const authStartUrl = new URL(`${openFormsBaseUrl}/auth/${formSlug}/${authPluginId}/start`);
            authStartUrl.searchParams.set('next', returnUrl.toString());

            if (isDebug) console.log('Redirecting to Open Forms auth');
            window.location.href = authStartUrl.toString();
            return;
        }

        if (isDebug) console.log('Post-auth: loading form');

        if (autoStart) {
            // Hide login options and auto-click "Formulier starten"
            const style = document.createElement('style');
            style.textContent = '[class*="login-options"] { display: none !important; }';
            document.head.appendChild(style);

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
        }
    })();
</script>
