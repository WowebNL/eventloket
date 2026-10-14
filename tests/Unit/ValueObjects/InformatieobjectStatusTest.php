<?php

declare(strict_types=1);

use App\ValueObjects\ZGW\Informatieobject;

function informatieobjectWithStatus(?string $status, ?bool $inhoudIsVervallen = null): Informatieobject
{
    return new Informatieobject(
        uuid: '1',
        url: 'https://example.com/doc/1',
        creatiedatum: '2026-01-01',
        titel: 'Doc',
        vertrouwelijkheidaanduiding: 'openbaar',
        auteur: 'Test',
        versie: 1,
        bestandsnaam: 'doc.pdf',
        inhoud: '',
        beschrijving: '',
        informatieobjecttype: 'https://example.com/iot/1',
        formaat: 'application/pdf',
        locked: false,
        status: $status,
        inhoudIsVervallen: $inhoudIsVervallen,
    );
}

test('established, archived and status-less documents may be shown', function (?string $status) {
    expect(informatieobjectWithStatus($status)->isVastgesteld())->toBeTrue();
})->with([
    'definitief' => 'definitief',
    // Archived means frozen for archival purposes, not unavailable: access is
    // governed by the vertrouwelijkheidaanduiding, and a document that may no
    // longer be seen is destroyed or transferred and then gone from the API.
    // Hiding it made every document disappear the moment a zaak was closed on a
    // backend that archives on the final status.
    'gearchiveerd' => 'gearchiveerd',
    'null (own upload)' => null,
    'empty (own upload)' => '',
]);

test('draft and unknown statuses are not shown (strict allowlist)', function (string $status) {
    expect(informatieobjectWithStatus($status)->isVastgesteld())->toBeFalse();
})->with([
    'in_bewerking' => 'in_bewerking',
    'ter_vaststelling' => 'ter_vaststelling',
    'concept' => 'concept',
    // Anything outside the allowlist is hidden, not just the known drafts.
    'unknown status' => 'iets_onbekends',
]);

test('a document whose content has expired is not shown, whatever its status', function (?string $status) {
    // inhoudIsVervallen (Documenten API 1.5.0) is independent of the status: a
    // definitief document can carry it too. It is hidden like a draft.
    expect(informatieobjectWithStatus($status, inhoudIsVervallen: true)->isVastgesteld())->toBeFalse();
})->with([
    'definitief' => 'definitief',
    'gearchiveerd' => 'gearchiveerd',
    'null (own upload)' => null,
]);

test('a document whose content has not expired follows its status', function (?bool $inhoudIsVervallen) {
    expect(informatieobjectWithStatus('definitief', $inhoudIsVervallen)->isVastgesteld())->toBeTrue()
        ->and(informatieobjectWithStatus('in_bewerking', $inhoudIsVervallen)->isVastgesteld())->toBeFalse();
})->with([
    'false' => false,
    'not sent (API before 1.5.0)' => null,
]);

test('inhoudIsVervallen survives a round trip through toArray()', function () {
    // Besluit documents are rebuilt from toArray(); the flag must not get lost.
    $document = new Informatieobject(...informatieobjectWithStatus('definitief', inhoudIsVervallen: true)->toArray());

    expect($document->inhoudIsVervallen)->toBeTrue()
        ->and($document->isVastgesteld())->toBeFalse();
});

test('a null beschrijving is accepted (some ZGW backends omit it)', function () {
    // RX Mission returns beschrijving = null where OpenZaak returns an empty
    // string; constructing the document must not throw a TypeError.
    $document = new Informatieobject(...[
        'uuid' => '1',
        'url' => 'https://example.com/doc/1',
        'creatiedatum' => '2026-01-01',
        'titel' => 'Doc',
        'vertrouwelijkheidaanduiding' => 'openbaar',
        'auteur' => 'Test',
        'versie' => 1,
        'bestandsnaam' => 'doc.pdf',
        'inhoud' => '',
        'beschrijving' => null,
        'informatieobjecttype' => 'https://example.com/iot/1',
        'formaat' => 'application/pdf',
        'locked' => false,
    ]);

    expect($document->beschrijving)->toBeNull();
    expect($document->toArray()['beschrijving'])->toBeNull();
});
