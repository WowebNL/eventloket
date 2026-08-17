<?php

use App\Enums\DocumentVertrouwelijkheden;
use App\Enums\Role;
use App\Services\Zgw\DocumentAudience;
use Illuminate\Support\Facades\Config;

it('names the role groups from the broadest to the narrowest audience', function () {
    $groups = DocumentAudience::groups();

    expect(array_column($groups, 'audience'))->toBe(['Gemeente', 'Adviseur', 'Organisator'])
        // The canonical role of each group is the one the connection form binds
        // to, so reading its visibility describes the whole group.
        ->and(array_map(fn (array $group): Role => $group['roles'][0], $groups))
        ->toBe([Role::Reviewer, Role::Advisor, Role::Organiser]);
});

it('labels every level with the enum defaults on a connection without a map', function () {
    Config::set('zgw.connections.main.vertrouwelijkheid_map', null);

    expect(DocumentAudience::uploadOptions('main', Role::Reviewer))->toBe([
        DocumentVertrouwelijkheden::Zaakvertrouwelijk->value => 'Gemeente, Adviseur, Organisator',
        DocumentVertrouwelijkheden::Vertrouwelijk->value => 'Gemeente, Adviseur',
        DocumentVertrouwelijkheden::Confidentieel->value => 'Gemeente',
    ]);
});

it('labels every level with the visibility map of the connection', function () {
    Config::set('zgw.connections.gemeente_9.vertrouwelijkheid_map.visibility', [
        Role::Organiser->value => ['openbaar', 'zaakvertrouwelijk', 'vertrouwelijk'],
        Role::Advisor->value => ['openbaar'],
        Role::Reviewer->value => ['openbaar', 'zaakvertrouwelijk', 'vertrouwelijk', 'confidentieel'],
    ]);

    expect(DocumentAudience::uploadOptions('gemeente_9', Role::Reviewer))->toBe([
        // The organiser sees vertrouwelijk here and the advisor sees none of the
        // three: the hardcoded defaults claim the opposite for both.
        DocumentVertrouwelijkheden::Zaakvertrouwelijk->value => 'Gemeente, Organisator',
        DocumentVertrouwelijkheden::Vertrouwelijk->value => 'Gemeente, Organisator',
        DocumentVertrouwelijkheden::Confidentieel->value => 'Gemeente',
    ]);
});

it('offers no choice when every level reaches the same audience', function () {
    // The VRZL staging situation: the municipal roles see everything, the other
    // groups see none of the three levels on offer.
    Config::set('zgw.connections.gemeente_9.vertrouwelijkheid_map.visibility', [
        Role::Organiser->value => ['openbaar'],
        Role::Advisor->value => ['openbaar'],
        Role::Reviewer->value => ['openbaar', 'zaakvertrouwelijk', 'vertrouwelijk', 'confidentieel'],
    ]);

    expect(DocumentAudience::uploadOptions('gemeente_9', Role::Reviewer))->toBe([]);
});

it('offers no choice when the uploader may see at most one of the levels', function () {
    Config::set('zgw.connections.gemeente_9.vertrouwelijkheid_map.visibility', [
        Role::Organiser->value => ['openbaar', 'zaakvertrouwelijk'],
        Role::Advisor->value => ['openbaar', 'zaakvertrouwelijk'],
        Role::Reviewer->value => ['openbaar', 'zaakvertrouwelijk'],
    ]);

    expect(DocumentAudience::uploadOptions('gemeente_9', Role::Reviewer))->toBe([]);
});

it('reads the audience of a single level from the connection', function () {
    Config::set('zgw.connections.gemeente_9.vertrouwelijkheid_map.visibility', [
        Role::Organiser->value => ['openbaar', 'zaakvertrouwelijk'],
        Role::Advisor->value => ['openbaar'],
        Role::Reviewer->value => ['openbaar', 'zaakvertrouwelijk'],
    ]);

    expect(DocumentAudience::audienceFor('gemeente_9', DocumentVertrouwelijkheden::Zaakvertrouwelijk->value))
        ->toBe(['Gemeente', 'Organisator'])
        ->and(DocumentAudience::audienceFor('gemeente_9', DocumentVertrouwelijkheden::Confidentieel->value))
        ->toBe([]);
});
