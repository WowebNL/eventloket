import { execSync } from 'node:child_process';

/**
 * Check of OpenZaak bereikbaar is vanaf de Laravel-container. Voor
 * submit-specs nodig: zonder OpenZaak faalt CreateZaakInZGW met een
 * cryptische cURL-error 7.
 *
 * Returns true wanneer OpenZaak antwoordt (ook bij 401 — dat betekent
 * dat de service draait). False bij connection-refused / timeout.
 */
export function isOpenZaakUp() {
    try {
        // `curl` op de container is sneller en betrouwbaarder dan host-curl
        // omdat de Laravel-app via `host.docker.internal:8001` praat — die
        // hostname resolveert anders binnen vs buiten de container.
        execSync(
            './vendor/bin/sail exec laravel.test curl -sf -o /dev/null -m 3 ' +
            '--connect-timeout 2 ' +
            'http://host.docker.internal:8001/zaken/api/v1/schema/openapi.yaml',
            { stdio: 'pipe', timeout: 10_000 },
        );

        return true;
    } catch {
        return false;
    }
}

/**
 * Skip de huidige test wanneer OpenZaak niet draait, met een duidelijke
 * boodschap — voorkomt cryptische cURL-7 timeouts diep in submit().
 */
export function skipAlsOpenZaakOffline(test) {
    test.skip(! isOpenZaakUp(), 'OpenZaak niet bereikbaar op host.docker.internal:8001 — start de externe ZGW-stack om submit-tests te runnen.');
}
