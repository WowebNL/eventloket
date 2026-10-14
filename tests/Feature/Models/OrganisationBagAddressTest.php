<?php

declare(strict_types=1);

/**
 * `bag_address` is an appended attribute, so it resolves during any
 * serialisation of an Organisation, including the zaak detail page rendering
 * its infolist. That makes it a synchronous PDOK call on a render path: when
 * the Locatieserver blipped, the mutator threw and the whole page died with a
 * fatal error. It has to render without an address instead, and a failed
 * lookup must not be remembered forever.
 */

use App\Models\Organisation;
use App\ValueObjects\Pdok\BagObject;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Config::set('services.locatieserver.base_url', 'https://locatieserver.test');
});

/**
 * @return array<string, mixed>
 */
function bagLookupPayload(): array
{
    return [
        'response' => [
            'docs' => [[
                'id' => '0123456789',
                'type' => 'adres',
                'centroide_ll' => 'POINT(5.88 50.91)',
                'weergavenaam' => 'Deweverplein 1, 6361BZ Nuth',
                'straatnaam' => 'Deweverplein',
                'postcode' => '6361BZ',
                'huisnummer' => 1,
                'woonplaatsnaam' => 'Nuth',
                'gemeentecode' => '1954',
            ]],
        ],
    ];
}

function organisationWithBagId(): Organisation
{
    return Organisation::factory()->create(['bag_id' => '0123456789']);
}

test('a successful lookup is returned and cached without an expiry', function () {
    $attempts = 0;
    Http::fake(function () use (&$attempts) {
        $attempts++;

        return Http::response(bagLookupPayload());
    });
    $organisation = organisationWithBagId();

    $bagAddress = $organisation->bag_address;

    expect($bagAddress)->toBeInstanceOf(BagObject::class)
        ->and($bagAddress->straatnaam)->toBe('Deweverplein')
        ->and($bagAddress->woonplaatsnaam)->toBe('Nuth')
        ->and(Cache::get("organisation.{$organisation->id}.0123456789"))->toBeInstanceOf(BagObject::class);

    // A second read is served from the cache, exactly as before.
    $organisation->refresh()->bag_address;
    expect($attempts)->toBe(1);

    // And it stays cached: a successful lookup gets no expiry.
    $this->travel(1)->days();

    expect($organisation->refresh()->bag_address)->toBeInstanceOf(BagObject::class)
        ->and($attempts)->toBe(1);
});

test('the organisation still serialises when the Locatieserver cannot be reached', function () {
    Http::fake(function () {
        throw new ConnectionException('cURL error 28: Operation timed out');
    });
    $organisation = organisationWithBagId();

    // This is the call the zaak page makes while rendering its infolist.
    $array = $organisation->toArray();

    expect($array)->toHaveKey('bag_address')
        ->and($array['bag_address'])->toBeNull()
        ->and($organisation->bag_address)->toBeNull();
});

test('a failed lookup is not cached forever and resolves again once PDOK recovers', function () {
    $attempts = 0;
    Http::fake(function () use (&$attempts) {
        $attempts++;

        if ($attempts === 1) {
            throw new ConnectionException('cURL error 28: Operation timed out');
        }

        return Http::response(bagLookupPayload());
    });
    $organisation = organisationWithBagId();

    expect($organisation->bag_address)->toBeNull();

    // The address returns by itself, without anyone clearing the cache.
    $this->travel(2)->minutes();

    expect($organisation->refresh()->bag_address)->toBeInstanceOf(BagObject::class)
        ->and($attempts)->toBe(2);
});

test('a failed lookup is not repeated on every render while the outage lasts', function () {
    $attempts = 0;
    Http::fake(function () use (&$attempts) {
        $attempts++;

        throw new ConnectionException('cURL error 28: Operation timed out');
    });
    $organisation = organisationWithBagId();

    $organisation->bag_address;
    $organisation->refresh()->bag_address;
    $organisation->refresh()->bag_address;

    expect($attempts)->toBe(1);
});

test('an organisation without a BAG id never calls the Locatieserver', function () {
    $attempts = 0;
    Http::fake(function () use (&$attempts) {
        $attempts++;

        return Http::response(bagLookupPayload());
    });
    $organisation = Organisation::factory()->create(['bag_id' => null]);

    expect($organisation->bag_address)->toBeNull()
        ->and($attempts)->toBe(0);
});
