import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';
import { loginAlsOrganiser } from './helpers/login.mjs';

/**
 * Scenario: per-gemeente aanvullende vragen.
 *
 * Bewijst twee dingen die alleen in een echte browser te zien zijn:
 *
 *   1. Een gemeente zonder vragen krijgt de stap "Aanvullende vragen"
 *      helemaal niet in de zijbalk — ook niet doorgestreept.
 *   2. Een meerkeuzevraag gedraagt zich als een meerkeuzevraag zodra de
 *      gemeente pas tijdens het invullen bekend wordt. Blijft de
 *      veldwaarde dan `null`, dan bindt Livewire de hele groep als één
 *      boolean en zet één vinkje ze allemaal aan (zie
 *      `EventFormPage::hydrateAanvullendeVragenState()`).
 *
 * Het concept wordt met een `inGemeentenResponse` van twee gemeenten
 * neergezet: de organisator moet dan zelf kiezen, dus bij het openen van
 * het formulier is er nog geen gemeente en bestaan de velden nog niet.
 * Dat is precies de situatie waarin de bug zich voordeed.
 *
 * Niet submit'en — dat vereist OpenZaak.
 */

function vindFormPageComponent() {
    if (! window.Livewire) return null;
    const dataInput = document.querySelector('input[wire\\:model^="data."], textarea[wire\\:model^="data."], select[wire\\:model^="data."], input[wire\\:model\\.live^="data."], textarea[wire\\:model\\.live^="data."], select[wire\\:model\\.live^="data."]');
    if (! dataInput) return null;
    const root = dataInput.closest('[wire\\:id]');
    if (! root) return null;
    return window.Livewire.find(root.getAttribute('wire:id'));
}

function tinker(php) {
    return execSync(`./vendor/bin/sail exec laravel.test php -r '
        require "vendor/autoload.php";
        $a = require "bootstrap/app.php";
        $a->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
        ${php}
    '`, { timeout: 40_000 }).toString().trim();
}

/** Een vers concept dat twee kandidaat-gemeenten kent maar nog geen keuze. */
const MAAK_CONCEPT = `
    $user = \\App\\Models\\User::where("email", "noah.degraaf@example.net")->firstOrFail();
    \\App\\EventForm\\Persistence\\Draft::where("user_id", $user->id)->delete();
    $org = $user->organisations()->firstOrFail();

    $d = \\App\\EventForm\\Persistence\\Draft::create([
        "user_id" => $user->id,
        "organisation_id" => $org->id,
        "state" => ["values" => ["inGemeentenResponse" => ["all" => [
            "items" => [
                ["brk_identification" => "GM0917", "name" => "Heerlen"],
                ["brk_identification" => "GM0935", "name" => "Maastricht"],
            ],
            "object" => [
                "GM0917" => ["brk_identification" => "GM0917", "name" => "Heerlen"],
                "GM0935" => ["brk_identification" => "GM0935", "name" => "Maastricht"],
            ],
        ]]], "system" => []],
        "current_step_key" => null,
    ]);

    echo $d->id . "|" . $org->getRouteKey();
`;

async function zet(page, pad, waarde) {
    await page.evaluate(({ pad, waarde, vindSrc }) => {
        const vind = new Function('return ' + vindSrc)();
        vind().set(pad, waarde);
    }, { pad, waarde, vindSrc: vindFormPageComponent.toString() });

    await page.waitForTimeout(2500);
}

const kiesGemeente = (page) => zet(page, 'data.userSelectGemeente', 'GM0917');

/**
 * Vink de eerste optie aan en geef terug welke opties daarna aanstaan plus
 * wat er in de Livewire-state belandde.
 *
 * De stap is nog niet zichtbaar (x-show), dus een echte muisklik registreert
 * niet. We simuleren wat de browser bij een klik doet, zodat Alpine's x-model
 * dezelfde weg loopt als bij een gebruikersklik.
 */
async function vinkEersteOptieAan(page, key, aantal) {
    const sel = `input[type=checkbox][wire\\:model="data.${key}"]`;

    await page.evaluate(({ sel }) => {
        const el = document.querySelector(sel);
        el.checked = true;
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }, { sel });

    await page.waitForTimeout(1500);

    const checked = [];
    for (let i = 0; i < aantal; i++) {
        checked.push(await page.locator(sel).nth(i).isChecked());
    }

    const waarde = await page.evaluate(({ key, vindSrc }) => {
        const vind = new Function('return ' + vindSrc)();
        return vind().get('data.' + key);
    }, { key, vindSrc: vindFormPageComponent.toString() });

    return { checked, waarde };
}

test('scenario aanvullende vragen: stap en meerkeuzevraag gedragen zich correct', async ({ page }) => {
    test.setTimeout(240_000);

    try {
        tinker('\\App\\Models\\Municipality::where("brk_identification", "GM0917")->firstOrFail()->formQuestions()->delete(); echo "ok";');
    } catch (e) {
        test.skip(true, 'Sail niet bereikbaar — draai deze test handmatig op de Mac');
    }

    await loginAlsOrganiser(page);

    try {
        // ---- Ronde 1: geen vragen → geen stap, ook niet doorgestreept ----
        const [draftId, orgKey] = tinker(MAAK_CONCEPT).split('|');

        await page.goto(`/organiser/${orgKey}/aanvraag/${draftId}`);
        await page.waitForLoadState('networkidle');
        await kiesGemeente(page);

        await expect(
            page.getByText('Aanvullende vragen', { exact: true }),
            'zonder ingestelde vragen mag de stap nergens in de zijbalk staan',
        ).toHaveCount(0);

        // ---- Ronde 2: drie vragen instellen, vers concept ----
        const vraagId = tinker(`
            $h = \\App\\Models\\Municipality::where("brk_identification", "GM0917")->firstOrFail();
            \\App\\Models\\MunicipalityFormQuestion::create([
                "municipality_id" => $h->id,
                "type" => "text",
                "label" => "Wilt u nog iets kwijt over uw evenement?",
            ]);
            \\App\\Models\\MunicipalityFormQuestion::create([
                "municipality_id" => $h->id,
                "type" => "radio",
                "label" => "Komt er een springkussen?",
                "options" => ["Ja", "Nee"],
            ]);
            $q = \\App\\Models\\MunicipalityFormQuestion::create([
                "municipality_id" => $h->id,
                "type" => "checkboxes",
                "label" => "Welke voorzieningen zijn aanwezig?",
                "options" => ["Toiletten", "EHBO", "Beveiliging"],
            ]);
            echo $q->id;
        `);

        const [draftId2, orgKey2] = tinker(MAAK_CONCEPT).split('|');

        await page.goto(`/organiser/${orgKey2}/aanvraag/${draftId2}`);
        await page.waitForLoadState('networkidle');
        await kiesGemeente(page);

        await expect(
            page.getByText('Aanvullende vragen', { exact: true }).first(),
            'de stap moet in de zijbalk verschijnen zodra de gemeente vragen heeft',
        ).toBeVisible({ timeout: 10_000 });

        await expect(
            page.locator('.fi-vertical-wizard-step-not-applicable').filter({ hasText: 'Aanvullende vragen' }),
            'de stap mag niet doorgestreept zijn wanneer er wél vragen zijn',
        ).toHaveCount(0);

        await expect(page.getByText('Welke voorzieningen zijn aanwezig?')).toBeAttached();
        await expect(page.getByText('Komt er een springkussen?')).toBeAttached();

        // ---- De meerkeuzevraag: één vinkje mag er één zijn ----
        const key = 'extraVraag_' + vraagId;
        await expect(page.locator(`input[type=checkbox][wire\\:model="data.${key}"]`)).toHaveCount(3);

        const eerste = await vinkEersteOptieAan(page, key, 3);

        expect(eerste.checked, 'alleen de aangeklikte optie mag aanstaan').toEqual([true, false, false]);
        expect(eerste.waarde, 'de state moet een lijst zijn, geen boolean').toEqual(['Toiletten']);
    } finally {
        // Cleanup: andere specs gaan uit van een Heerlen zonder extra vragen.
        tinker(`
            \\App\\Models\\Municipality::where("brk_identification", "GM0917")->firstOrFail()->formQuestions()->delete();
            $user = \\App\\Models\\User::where("email", "noah.degraaf@example.net")->firstOrFail();
            \\App\\EventForm\\Persistence\\Draft::where("user_id", $user->id)->delete();
            echo "ok";
        `);
    }
});

test('scenario aanvullende vragen: meerkeuzevraag die pas door een padwijziging verschijnt', async ({ page }) => {
    test.setTimeout(240_000);

    // Deze vraag geldt alleen bij een vooraankondiging. Op het moment dat de
    // gemeente bekend wordt, is het aanvraagpad nog "vergunning" (de veilige
    // default van DetermineAanvraagType), dus de vraag bestaat dan nog niet.
    // Ze ontstaat pas zodra de organisator het pad omzet — ver na de laatste
    // gemeente-fetch. Precies daarom hangt de hydratie aan de roundtrip en
    // niet aan die fetch.
    let vraagId;

    try {
        vraagId = tinker(`
            $h = \\App\\Models\\Municipality::where("brk_identification", "GM0917")->firstOrFail();
            $h->formQuestions()->delete();
            $q = \\App\\Models\\MunicipalityFormQuestion::create([
                "municipality_id" => $h->id,
                "type" => "checkboxes",
                "label" => "Welke voorzieningen zijn aanwezig?",
                "options" => ["Toiletten", "EHBO", "Beveiliging"],
                "show_for_aanvraag_types" => ["vooraankondiging"],
            ]);
            echo $q->id;
        `);
    } catch (e) {
        test.skip(true, 'Sail niet bereikbaar — draai deze test handmatig op de Mac');
    }

    await loginAlsOrganiser(page);

    try {
        const [draftId, orgKey] = tinker(MAAK_CONCEPT).split('|');

        await page.goto(`/organiser/${orgKey}/aanvraag/${draftId}`);
        await page.waitForLoadState('networkidle');

        await kiesGemeente(page);
        await zet(page, 'data.waarvoorWiltUEventloketGebruiken', 'vooraankondiging');

        const key = 'extraVraag_' + vraagId;
        await expect(page.locator(`input[type=checkbox][wire\\:model="data.${key}"]`)).toHaveCount(3);

        const eerste = await vinkEersteOptieAan(page, key, 3);

        expect(eerste.checked, 'alleen de aangeklikte optie mag aanstaan').toEqual([true, false, false]);
        expect(eerste.waarde, 'de state moet een lijst zijn, geen boolean').toEqual(['Toiletten']);
    } finally {
        tinker(`
            \\App\\Models\\Municipality::where("brk_identification", "GM0917")->firstOrFail()->formQuestions()->delete();
            $user = \\App\\Models\\User::where("email", "noah.degraaf@example.net")->firstOrFail();
            \\App\\EventForm\\Persistence\\Draft::where("user_id", $user->id)->delete();
            echo "ok";
        `);
    }
});
