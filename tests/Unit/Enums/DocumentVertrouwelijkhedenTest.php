<?php

use App\Enums\DocumentVertrouwelijkheden;
use App\Enums\Role;

it('orders the levels as the standard does, least confidential first', function () {
    expect(DocumentVertrouwelijkheden::order())->toBe([
        'openbaar',
        'beperkt_openbaar',
        'intern',
        'zaakvertrouwelijk',
        'vertrouwelijk',
        'confidentieel',
        'geheim',
        'zeer_geheim',
    ])
        ->and(DocumentVertrouwelijkheden::rank('openbaar'))->toBe(0)
        ->and(DocumentVertrouwelijkheden::rank('zeer_geheim'))->toBe(7)
        ->and(DocumentVertrouwelijkheden::rank('geen_niveau'))->toBeNull();
});

it('reads a maximum as inclusive over that order', function () {
    expect(DocumentVertrouwelijkheden::atMost('intern'))
        ->toBe(['openbaar', 'beperkt_openbaar', 'intern'])
        ->and(DocumentVertrouwelijkheden::atMost('openbaar'))->toBe(['openbaar'])
        ->and(DocumentVertrouwelijkheden::atMost('zeer_geheim'))
        ->toBe(DocumentVertrouwelijkheden::order())
        // An unknown maximum yields nothing rather than a guess.
        ->and(DocumentVertrouwelijkheden::atMost('geen_niveau'))->toBe([]);
});

it('picks the most confidential level out of a set', function () {
    expect(DocumentVertrouwelijkheden::mostConfidential(['openbaar', 'intern', 'beperkt_openbaar']))
        ->toBe('intern')
        // Ordering inside the set is irrelevant, only confidentiality counts.
        ->and(DocumentVertrouwelijkheden::mostConfidential(['confidentieel', 'openbaar']))
        ->toBe('confidentieel')
        ->and(DocumentVertrouwelijkheden::mostConfidential([]))->toBeNull()
        ->and(DocumentVertrouwelijkheden::mostConfidential(['geen_niveau', 42, null]))->toBeNull()
        // A set mixing known and unknown levels still yields the known maximum.
        ->and(DocumentVertrouwelijkheden::mostConfidential(['geen_niveau', 'openbaar']))
        ->toBe('openbaar');
});

it('keeps the legacy defaults outside the maximum model', function () {
    // The defaults are a fixed set, not a maximum: they skip the three levels
    // below zaakvertrouwelijk, so a connection wanting those configures a map.
    expect(DocumentVertrouwelijkheden::fromUserRole(Role::Organiser))
        ->toBe(['zaakvertrouwelijk'])
        ->and(DocumentVertrouwelijkheden::fromUserRole(Role::Organiser))
        ->not->toBe(DocumentVertrouwelijkheden::atMost('zaakvertrouwelijk'))
        ->and(DocumentVertrouwelijkheden::uploadChoices())
        ->toBe(['zaakvertrouwelijk', 'vertrouwelijk', 'confidentieel']);
});
