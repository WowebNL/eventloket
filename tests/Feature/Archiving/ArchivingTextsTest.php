<?php

/**
 * Both texts end up on permanent destruction reports. Pinned here so a change
 * to either one is a deliberate edit of this test as well.
 */
test('the default destruction method names only the zaakdata in OpenZaak', function () {
    expect(config('archiving.destruction_method'))
        ->toBe('Permanente verwijdering van zaakgegevens uit OpenZaak (hard delete)');
});

test('the eventloket data destruction method names what eventloket held', function () {
    expect(config('archiving.eventloket_destruction_method'))
        ->toBe('Permanente verwijdering van de Eventloket-gegevens (threads, berichten, notificaties, logboek en, waar aanwezig, het formulierinzending-object; zie per zaak), nadat het zaaksysteem bevestigde dat de zaak is vernietigd');
});

test('the eventloket data report says the zaaksysteem confirmed the destruction', function () {
    expect(__('pdf/destruction-report.eventloket_data.intro'))
        ->toContain('nadat het zaaksysteem bevestigde dat de zaak daar is vernietigd');
});
