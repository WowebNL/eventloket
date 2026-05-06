import { execSync } from 'node:child_process';

/**
 * Vraag de PDF-content (sections + entries, zoals ze in de Blade-
 * template worden gerendered) op voor een ingediende Zaak. We omzeilen
 * PDF-binary-parsing door direct de SubmissionReport-builder aan te
 * roepen via een Artisan-command — de Blade-PDF is een dunne render
 * bovenop deze data, dus byte-equivalent op inhoud.
 *
 * @param {string} zaakIdentifier  public_id of UUID van de zaak
 * @returns {{zaak: {public_id: string, zaaktype: string|null, organisation: string|null}, sections: Array<{title: string, entries: Array<{label: string, value: string}>}>}}
 */
export function leesPdfContent(zaakIdentifier) {
    const cmd = `./vendor/bin/sail exec laravel.test php artisan eventform:dump-pdf-content ${JSON.stringify(zaakIdentifier)} --no-interaction --no-ansi`;
    const stdout = execSync(cmd, {
        stdio: ['pipe', 'pipe', 'pipe'],
        timeout: 30_000,
        encoding: 'utf8',
    });
    // De command prints first stdout-noise van Sail (warnings), dan een
    // JSON-regel. Pak de laatste regel die met `{` begint.
    const lines = stdout.split('\n').filter((l) => l.trim().startsWith('{'));
    if (lines.length === 0) {
        throw new Error(`dump-pdf-content gaf geen JSON terug:\n${stdout}`);
    }

    return JSON.parse(lines[lines.length - 1]);
}

/**
 * Zoek een entry op label-tekst (case-insensitive substring-match) in
 * één sectie van de PDF.
 *
 * @returns {string|null}  de waarde, of null als 't entry niet bestaat
 */
export function vindEntryWaarde(sectie, labelSubstring) {
    const sub = labelSubstring.toLowerCase();
    const entry = (sectie?.entries ?? []).find((e) => (e.label ?? '').toLowerCase().includes(sub));

    return entry ? entry.value : null;
}

/**
 * Zoek een sectie op titel (substring, case-insensitive).
 */
export function vindSectie(pdfData, titelSubstring) {
    const sub = titelSubstring.toLowerCase();

    return (pdfData?.sections ?? []).find((s) => (s.title ?? '').toLowerCase().includes(sub)) ?? null;
}
