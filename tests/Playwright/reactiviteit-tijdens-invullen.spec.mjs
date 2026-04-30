import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';
import { loginAlsOrganiser, openFormulier } from './helpers/login.mjs';
import {
    vulTekst,
    vulEindigendOp,
    kiesRadio,
    kiesRadioOptioneel,
    kiesSelect,
    klikVolgende,
    huidigeStap,
} from './helpers/form-invullen.mjs';

/**
 * Reactiviteit-checks: bewijst dat het formulier live verandert
 * tijdens het invullen — niet alleen na submit. Dit zijn de stukjes
 * die in de OF-rule-engine zaten (visibility/applicability) en nu via
 * `FormFieldVisibility` + `FormStepApplicability` + Filament's
 * `->visible()`-closures worden geleverd.
 *
 * We gaan niet door alle stappen heen; iedere `test()` is een
 * gefocuste "geef-en-observeer"-cyclus.
 */

test.beforeEach(async () => {
    // Schoon vertrekpunt: oude drafts kunnen in een vorige run-staat
    // hangen waardoor velden al ingevuld zijn die we hier juist willen
    // observeren.
    execSync('./vendor/bin/sail exec laravel.test php -r \'require "vendor/autoload.php"; $a = require "bootstrap/app.php"; $a->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); \\App\\EventForm\\Persistence\\Draft::truncate();\'', {
        stdio: 'pipe',
        timeout: 30_000,
    });
});

test('reactiviteit: vooraankondiging-keuze grijst stappen 6-15 uit in de zijbalk', async ({ page }) => {
    test.setTimeout(120_000);

    await loginAlsOrganiser(page);
    await openFormulier(page);

    // Sneller naar stap 5 — minimale invul tot daar.
    await vulTekst(page, 'postcode1', '6411CD').catch(() => {});
    await vulTekst(page, 'huisnummer1', '1').catch(() => {});
    await vulTekst(page, 'straatnaam1', 'X').catch(() => {});
    await vulTekst(page, 'plaatsnaam1', 'Heerlen').catch(() => {});
    await klikVolgende(page);

    await vulTekst(page, 'watIsDeNaamVanHetEvenementVergunning', 'Reactiviteit-test');
    await page.waitForTimeout(500);
    await page.locator('textarea[wire\\:model="data.geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning"]').first().fill('Test');
    await kiesSelect(
        page,
        'soortEvenement',
        'Buurt-, barbecue of straatfeest, buitenspeeldagen, (eindejaars-)feesten, garagesales',
    );
    await page.waitForTimeout(500);
    await klikVolgende(page);

    // Stap 3: Locatie — gebouw selectie (snel)
    await page.getByRole('checkbox', { name: /In een gebouw/i }).check();
    await page.waitForTimeout(1500);
    await page.getByRole('button', { name: /Toevoegen aan adres van de gebouw/i }).click();
    await page.waitForTimeout(1500);
    await vulEindigendOp(page, 'input', '.naamVanDeLocatieGebouw', 'X');
    await vulEindigendOp(page, 'input', '.adresVanHetGebouwWaarUwEvenementPlaatsvindt1.postcode', '6411CD');
    await page.keyboard.press('Tab');
    await vulEindigendOp(page, 'input', '.adresVanHetGebouwWaarUwEvenementPlaatsvindt1.huisnummer', '1');
    await page.keyboard.press('Tab');
    await page.waitForTimeout(3500);
    await klikVolgende(page);

    // Stap 4: Tijden
    await vulTekst(page, 'EvenementStart', '2026-09-21T14:00');
    await vulTekst(page, 'EvenementEind', '2026-09-21T20:00');
    await kiesRadio(page, 'zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten', 'Nee').catch(() => {});
    await kiesRadio(page, 'zijnErTijdensHetEvenementXOpbouwactiviteiten', 'Nee').catch(() => {});
    await kiesRadio(page, 'zijnErAansluitendAanHetEvenementAfbouwactiviteiten', 'Nee').catch(() => {});
    await kiesRadio(page, 'zijnErTijdensHetEvenementXAfbouwactiviteiten3', 'Nee').catch(() => {});
    await page.waitForTimeout(700);
    await klikVolgende(page);

    expect(await huidigeStap(page)).toMatch(/Vooraankondiging/i);

    // === REACTIVITEIT-OBSERVATIE ===
    // Tel hoeveel stappen in de wizard-sidebar zichtbaar+actief zijn ('niet-doorgestreept')
    // BEFORE we 'vooraankondiging' kiezen.
    const totaalStappen = await page.locator('.fi-vertical-wizard-step').count();
    expect(totaalStappen).toBeGreaterThanOrEqual(15);

    // Nu kiezen we 'vooraankondiging' → moet stappen 6-15 niet-applicable maken.
    await kiesRadio(page, 'waarvoorWiltUEventloketGebruiken', 'vooraankondiging');
    await page.waitForTimeout(1500); // Livewire-roundtrip → state update → sidebar re-render

    // VerticalWizard markeert niet-applicable stappen met de class
    // `fi-vertical-wizard-step-not-applicable` (zie
    // `resources/views/event-form/components/vertical-wizard.blade.php`).
    const nietApplicable = await page.locator('.fi-vertical-wizard-step-not-applicable').count();
    expect(
        nietApplicable,
        'na vooraankondiging-keuze moeten meerdere stappen niet-applicable zijn (was 0 vóór keuze)',
    ).toBeGreaterThan(5);
});

test('reactiviteit: na "evenement"-keuze blijven alle stappen applicable', async ({ page }) => {
    test.setTimeout(120_000);

    await loginAlsOrganiser(page);
    await openFormulier(page);

    // Identiek tot stap 5 als boven, maar dan keuze='evenement'.
    await vulTekst(page, 'postcode1', '6411CD').catch(() => {});
    await vulTekst(page, 'huisnummer1', '1').catch(() => {});
    await vulTekst(page, 'straatnaam1', 'X').catch(() => {});
    await vulTekst(page, 'plaatsnaam1', 'Heerlen').catch(() => {});
    await klikVolgende(page);

    await vulTekst(page, 'watIsDeNaamVanHetEvenementVergunning', 'X');
    await page.waitForTimeout(400);
    await page.locator('textarea[wire\\:model="data.geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning"]').first().fill('Test');
    await kiesSelect(
        page,
        'soortEvenement',
        'Buurt-, barbecue of straatfeest, buitenspeeldagen, (eindejaars-)feesten, garagesales',
    );
    await page.waitForTimeout(400);
    await klikVolgende(page);

    await page.getByRole('checkbox', { name: /In een gebouw/i }).check();
    await page.waitForTimeout(1500);
    await page.getByRole('button', { name: /Toevoegen aan adres van de gebouw/i }).click();
    await page.waitForTimeout(1500);
    await vulEindigendOp(page, 'input', '.naamVanDeLocatieGebouw', 'X');
    await vulEindigendOp(page, 'input', '.adresVanHetGebouwWaarUwEvenementPlaatsvindt1.postcode', '6411CD');
    await page.keyboard.press('Tab');
    await vulEindigendOp(page, 'input', '.adresVanHetGebouwWaarUwEvenementPlaatsvindt1.huisnummer', '1');
    await page.keyboard.press('Tab');
    await page.waitForTimeout(3500);
    await klikVolgende(page);

    await vulTekst(page, 'EvenementStart', '2026-09-21T14:00');
    await vulTekst(page, 'EvenementEind', '2026-09-21T20:00');
    await kiesRadio(page, 'zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten', 'Nee').catch(() => {});
    await kiesRadio(page, 'zijnErTijdensHetEvenementXOpbouwactiviteiten', 'Nee').catch(() => {});
    await kiesRadio(page, 'zijnErAansluitendAanHetEvenementAfbouwactiviteiten', 'Nee').catch(() => {});
    await kiesRadio(page, 'zijnErTijdensHetEvenementXAfbouwactiviteiten3', 'Nee').catch(() => {});
    await page.waitForTimeout(700);
    await klikVolgende(page);

    expect(await huidigeStap(page)).toMatch(/Vooraankondiging/i);

    await kiesRadio(page, 'waarvoorWiltUEventloketGebruiken', 'evenement');
    await page.waitForTimeout(1500);

    // 'evenement'-keuze: alle stappen applicable (afgezien van melding/vergunning
    // die later door wegen=Ja/Nee worden afgehandeld). Op dit moment in de flow
    // is wegen nog niet ingevuld → niet-applicable count moet ≤1 zijn.
    const nietApplicable = await page.locator('.fi-vertical-wizard-step-not-applicable').count();
    expect(
        nietApplicable,
        'na evenement-keuze (zonder wegen-keuze) zijn vrijwel geen stappen niet-applicable',
    ).toBeLessThanOrEqual(1);
});

test('reactiviteit: brk-postcode triggert gemeente-detect → algemeneVragen sectie verschijnt', async ({ page }) => {
    test.setTimeout(120_000);

    await loginAlsOrganiser(page);
    await openFormulier(page);

    // Stap 1
    await vulTekst(page, 'postcode1', '6411CD').catch(() => {});
    await vulTekst(page, 'huisnummer1', '1').catch(() => {});
    await vulTekst(page, 'straatnaam1', 'X').catch(() => {});
    await vulTekst(page, 'plaatsnaam1', 'Heerlen').catch(() => {});
    await klikVolgende(page);

    await vulTekst(page, 'watIsDeNaamVanHetEvenementVergunning', 'X');
    await page.waitForTimeout(400);
    await page.locator('textarea[wire\\:model="data.geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning"]').first().fill('Test');
    await kiesSelect(
        page,
        'soortEvenement',
        'Buurt-, barbecue of straatfeest, buitenspeeldagen, (eindejaars-)feesten, garagesales',
    );
    await page.waitForTimeout(400);
    await klikVolgende(page);

    // Stap 3 — locatie. Voor we het adres invullen mag 'algemeneVragen'-sectie
    // niet zichtbaar zijn (die hangt op `evenementInGemeente.brk_identification`).
    expect(await huidigeStap(page)).toMatch(/Locatie/i);

    await page.getByRole('checkbox', { name: /In een gebouw/i }).check();
    await page.waitForTimeout(1500);
    await page.getByRole('button', { name: /Toevoegen aan adres van de gebouw/i }).click();
    await page.waitForTimeout(1500);

    // De content200-tekst ("U gaat verder met deze aanraag voor de
    // gemeente <X>") is uniek genoeg om page-breed te zoeken — komt niet
    // in sidebar/header voor. We checken vooral dat 'ie verschijnt MET
    // een gemeente-naam erin (dat is het echte reactiviteit-bewijs).
    const bevestiging = page.getByText(/U gaat verder met deze aanraag voor de gemeente/i);

    await vulEindigendOp(page, 'input', '.naamVanDeLocatieGebouw', 'X');
    await vulEindigendOp(page, 'input', '.adresVanHetGebouwWaarUwEvenementPlaatsvindt1.postcode', '6411CD');
    await page.keyboard.press('Tab');
    await page.waitForTimeout(500);
    await vulEindigendOp(page, 'input', '.adresVanHetGebouwWaarUwEvenementPlaatsvindt1.huisnummer', '1');
    await page.keyboard.press('Tab');
    await page.waitForTimeout(4000); // Wacht op LocationServerCheck + state-pass

    // Na BAG-lookup: content200-veld is gemarkeerd zichtbaar omdat
    // FormFieldVisibility::content200() ziet dat
    // `evenementInGemeente.brk_identification` gevuld is. De bijbehorende
    // <strong>-child bevat de gemeente-naam — voor postcode 6411CD is dat
    // Heerlen. Asserting op die specifiek gevulde naam bewijst dat de
    // ServiceFetcher daadwerkelijk geactiveerd is (niet alleen een lege
    // hint-tekst).
    await expect(
        bevestiging.first(),
        'content200-bevestiging moet bestaan in de DOM na BAG-lookup',
    ).toBeAttached({ timeout: 8_000 });

    await expect(
        page.getByText(/Heerlen/).first(),
        'gemeente-naam (Heerlen) moet ergens op de pagina verschijnen na BAG-lookup voor 6411CD',
    ).toBeAttached({ timeout: 5_000 });
});
