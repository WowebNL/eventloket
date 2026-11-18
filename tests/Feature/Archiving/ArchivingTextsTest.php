<?php

use App\Enums\DataObjectDestructionStatus;

/**
 * These texts end up on permanent destruction reports or in the confirmation
 * shown before a destruction. Pinned here so a change to any of them is a
 * deliberate edit of this test as well.
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

test('the form submission object statuses start with a capital like the other status labels', function () {
    expect(collect(DataObjectDestructionStatus::cases())->mapWithKeys(fn (DataObjectDestructionStatus $status) => [$status->value => $status->getLabel()])->all())
        ->toBe([
            'deleted' => 'Verwijderd',
            'not_present' => 'Niet aanwezig',
            'refused' => 'Niet verwijderd (geweigerd)',
            'failed' => 'Niet verwijderd (fout)',
        ]);
});

test('the destruction confirmation says the eventloket data follows the confirmation of the zaaksysteem', function () {
    expect(__('municipality/resources/destruction_list.actions.confirm_destruction.modal_description'))
        ->toBe('Let op: dit kan niet ongedaan worden gemaakt. Alle zaken op deze lijst worden permanent vernietigd in OpenZaak. Daarna worden de bijbehorende Eventloket-gegevens permanent verwijderd, zodra het zaaksysteem bevestigt dat de zaak is vernietigd.');
});
