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

it('labels every level with the enum defaults on the default connection', function () {
    Config::set('zgw.connections.main.vertrouwelijkheid_map', null);

    // Regression anchor: the "main" OpenZaak connection keeps the fixed
    // {zaakvertrouwelijk, vertrouwelijk, confidentieel} ladder unchanged.
    expect(DocumentAudience::uploadOptions('main', Role::Reviewer))->toBe([
        DocumentVertrouwelijkheden::Zaakvertrouwelijk->value => 'Gemeente, Adviseur, Organisator',
        DocumentVertrouwelijkheden::Vertrouwelijk->value => 'Gemeente, Adviseur',
        DocumentVertrouwelijkheden::Confidentieel->value => 'Gemeente',
    ]);
});

it('keeps the fixed ladder on the default connection even when a map is present', function () {
    // The default connection never derives its ladder from a map: even a map
    // using openbaar/beperkt_openbaar/intern leaves it on the fixed upload
    // choices, none of which this map makes visible, so no real choice remains.
    Config::set('zgw.connections.main.vertrouwelijkheid_map.visibility', [
        Role::Organiser->value => 'openbaar',
        Role::Advisor->value => 'beperkt_openbaar',
        Role::Reviewer->value => 'intern',
    ]);

    expect(DocumentAudience::uploadOptions('main', Role::Reviewer))->toBe([]);
});

it('derives an oplopende ladder from the maxima a connection configures', function () {
    // Three maxima, three nested audiences, one rung each. The levels in between
    // (nothing here) would reach the same audience as the maximum above them.
    Config::set('zgw.connections.gemeente_9.vertrouwelijkheid_map.visibility', [
        Role::Organiser->value => 'openbaar',
        Role::Advisor->value => 'beperkt_openbaar',
        Role::Reviewer->value => 'intern',
    ]);

    expect(DocumentAudience::uploadOptions('gemeente_9', Role::Reviewer))->toBe([
        'openbaar' => 'Gemeente, Adviseur, Organisator',
        'beperkt_openbaar' => 'Gemeente, Adviseur',
        'intern' => 'Gemeente',
    ]);
});

it('offers a rung per distinct maximum, not per level in between', function () {
    // The gemeente maximum spans four levels the other groups cannot see, but
    // they all reach the same audience, so they form a single rung keyed by the
    // maximum itself.
    Config::set('zgw.connections.gemeente_9.vertrouwelijkheid_map.visibility', [
        Role::Organiser->value => 'zaakvertrouwelijk',
        Role::Advisor->value => 'zaakvertrouwelijk',
        Role::Reviewer->value => 'confidentieel',
    ]);

    expect(DocumentAudience::uploadOptions('gemeente_9', Role::Reviewer))->toBe([
        'zaakvertrouwelijk' => 'Gemeente, Adviseur, Organisator',
        'confidentieel' => 'Gemeente',
    ]);
});

it('offers no choice when every group has the same maximum', function () {
    Config::set('zgw.connections.gemeente_9.vertrouwelijkheid_map.visibility', [
        Role::Organiser->value => 'zaakvertrouwelijk',
        Role::Advisor->value => 'zaakvertrouwelijk',
        Role::Reviewer->value => 'zaakvertrouwelijk',
    ]);

    expect(DocumentAudience::uploadOptions('gemeente_9', Role::Reviewer))->toBe([]);
});

it('offers no choice when the uploader may see at most one distinct audience', function () {
    // The uploader (an advisor here) tops out at openbaar, which everyone sees,
    // so there is a single rung and no choice.
    Config::set('zgw.connections.gemeente_9.vertrouwelijkheid_map.visibility', [
        Role::Organiser->value => 'openbaar',
        Role::Advisor->value => 'openbaar',
        Role::Reviewer->value => 'intern',
    ]);

    expect(DocumentAudience::uploadOptions('gemeente_9', Role::Advisor))->toBe([]);
});

it('derives the same ladder from a legacy map stored as sets of levels', function () {
    // Compatibility: a map written before the maximum was introduced still reads
    // as the maximum each set expressed, so the ladder is unchanged.
    Config::set('zgw.connections.gemeente_9.vertrouwelijkheid_map.visibility', [
        Role::Organiser->value => ['openbaar'],
        Role::Advisor->value => ['openbaar', 'beperkt_openbaar'],
        Role::Reviewer->value => ['openbaar', 'beperkt_openbaar', 'intern'],
    ]);

    expect(DocumentAudience::uploadOptions('gemeente_9', Role::Reviewer))->toBe([
        'openbaar' => 'Gemeente, Adviseur, Organisator',
        'beperkt_openbaar' => 'Gemeente, Adviseur',
        'intern' => 'Gemeente',
    ]);
});

it('shows an openbaar document to every role group on a connection with maxima', function () {
    // openbaar is the least confidential level, so it sits at or below every
    // maximum. A backend that labels its own uploads openbaar is therefore
    // visible to all three groups here, which is what the maximum model buys.
    Config::set('zgw.connections.gemeente_9.vertrouwelijkheid_map.visibility', [
        Role::Organiser->value => 'openbaar',
        Role::Advisor->value => 'beperkt_openbaar',
        Role::Reviewer->value => 'intern',
    ]);

    expect(DocumentAudience::audienceFor('gemeente_9', DocumentVertrouwelijkheden::Openbaar->value))
        ->toBe(['Gemeente', 'Adviseur', 'Organisator']);
});

it('reads the audience of a single level from the connection', function () {
    Config::set('zgw.connections.gemeente_9.vertrouwelijkheid_map.visibility', [
        Role::Organiser->value => 'zaakvertrouwelijk',
        Role::Advisor->value => 'openbaar',
        Role::Reviewer->value => 'zaakvertrouwelijk',
    ]);

    expect(DocumentAudience::audienceFor('gemeente_9', DocumentVertrouwelijkheden::Zaakvertrouwelijk->value))
        ->toBe(['Gemeente', 'Organisator'])
        ->and(DocumentAudience::audienceFor('gemeente_9', DocumentVertrouwelijkheden::Confidentieel->value))
        ->toBe([]);
});
