import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';
import { loginAlsOrganiser, openFormulier } from './helpers/login.mjs';
import {
    vulTekst,
    vulTextarea,
    vulEindigendOp,
    kiesRadio,
    kiesRadioOptioneel,
    kiesSelect,
    klikVolgende,
    huidigeStap,
} from './helpers/form-invullen.mjs';
import { leesPdfContent, vindSectie, vindEntryWaarde } from './helpers/pdf-content.mjs';
import { skipAlsOpenZaakOffline } from './helpers/openzaak-check.mjs';

/**
 * Scenario: vooraankondiging — de organisator wil alleen aankondigen
 * dat 'r een evenement gaat plaatsvinden, niet meteen een vergunning of
 * melding indienen. Dit pad slaat stappen 6-15 over (zie
 * `FormStepApplicability` voor de UUID's met `waarvoor === 'vooraankondiging'`-conditie).
 *
 * Verifieert dat:
 *   - De wizard ná stap 5 direct doorgaat naar de samenvatting/type-aanvraag.
 *   - De ingediende zaak een Vooraankondiging-zaaktype heeft.
 *   - De PDF alleen secties bevat voor stappen die wél applicable waren.
 */
test('scenario vooraankondiging: skipt stappen 6-15, zaaktype = vooraankondiging', async ({ page }) => {
    test.setTimeout(120_000);
    skipAlsOpenZaakOffline(test);

    execSync('./vendor/bin/sail exec laravel.test php -r \'require "vendor/autoload.php"; $a = require "bootstrap/app.php"; $a->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); \\App\\EventForm\\Persistence\\Draft::truncate();\'', {
        stdio: 'pipe',
        timeout: 30_000,
    });

    await loginAlsOrganiser(page);
    await openFormulier(page);

    // ---------- Stap 1: Contactgegevens ------------------------------
    await test.step('Stap 1 — Contactgegevens', async () => {
        await vulTekst(page, 'postcode1', '6411CD').catch(() => {});
        await vulTekst(page, 'huisnummer1', '1').catch(() => {});
        await vulTekst(page, 'straatnaam1', 'Teststraat').catch(() => {});
        await vulTekst(page, 'plaatsnaam1', 'Heerlen').catch(() => {});
        await klikVolgende(page);
    });

    // ---------- Stap 2: Het evenement --------------------------------
    await test.step('Stap 2 — Het evenement', async () => {
        expect(await huidigeStap(page)).toMatch(/Het evenement/i);
        await vulTekst(page, 'watIsDeNaamVanHetEvenementVergunning', 'Vooraankondiging Lentefestival');
        await page.waitForTimeout(800);
        await vulTextarea(
            page,
            'geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning',
            'Vooraankondiging — definitieve aanvraag volgt later.',
        );
        await kiesSelect(
            page,
            'soortEvenement',
            'Buurt-, barbecue of straatfeest, buitenspeeldagen, (eindejaars-)feesten, garagesales',
        );
        await page.waitForTimeout(500);
        await klikVolgende(page);
    });

    // ---------- Stap 3: Locatie --------------------------------------
    await test.step('Stap 3 — Locatie (in een gebouw)', async () => {
        expect(await huidigeStap(page)).toMatch(/Locatie/i);

        await page.getByRole('checkbox', { name: /In een gebouw/i }).check();
        await page.waitForTimeout(1500);
        await page.getByRole('button', { name: /Toevoegen aan adres van de gebouw/i }).click();
        await page.waitForTimeout(1500);

        await vulEindigendOp(page, 'input', '.naamVanDeLocatieGebouw', 'Stadhuis');
        await vulEindigendOp(page, 'input', '.adresVanHetGebouwWaarUwEvenementPlaatsvindt1.postcode', '6411CD');
        await page.keyboard.press('Tab');
        await page.waitForTimeout(500);
        await vulEindigendOp(page, 'input', '.adresVanHetGebouwWaarUwEvenementPlaatsvindt1.huisnummer', '1');
        await page.keyboard.press('Tab');
        await page.waitForTimeout(4000);

        await klikVolgende(page);
    });

    // ---------- Stap 4: Tijden ---------------------------------------
    await test.step('Stap 4 — Tijden', async () => {
        expect(await huidigeStap(page)).toMatch(/Tijden/i);
        await vulTekst(page, 'EvenementStart', '2026-09-21T14:00');
        await vulTekst(page, 'EvenementEind', '2026-09-21T20:00');
        await kiesRadio(page, 'zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten', 'Nee').catch(() => {});
        await kiesRadio(page, 'zijnErTijdensHetEvenementXOpbouwactiviteiten', 'Nee').catch(() => {});
        await kiesRadio(page, 'zijnErAansluitendAanHetEvenementAfbouwactiviteiten', 'Nee').catch(() => {});
        await kiesRadio(page, 'zijnErTijdensHetEvenementXAfbouwactiviteiten3', 'Nee').catch(() => {});
        await page.waitForTimeout(800);
        await klikVolgende(page);
    });

    // ---------- Stap 5: Vooraankondiging — de splits-keuze ------------
    await test.step('Stap 5 — Vooraankondiging-keuze', async () => {
        expect(await huidigeStap(page)).toMatch(/Vooraankondiging/i);
        // KRITIEK: dit veld is wat in FormStepApplicability stappen 6-15
        // op niet-applicable zet voor alle UUID's met conditie
        // `waarvoorWiltUEventloketGebruiken === 'vooraankondiging'`.
        await kiesRadio(page, 'waarvoorWiltUEventloketGebruiken', 'vooraankondiging');
        await page.waitForTimeout(800);
        await klikVolgende(page);
    });

    // ---------- Verifieer dat we direct op de samenvatting/type-aanvraag landen
    await test.step('Stappen 6-15 zijn geskipt', async () => {
        const stap = await huidigeStap(page);
        // Niet-applicable stappen worden door VerticalWizard automatisch
        // overgeslagen — we verwachten Bijlagen, Samenvatting of Type aanvraag.
        expect(stap).not.toMatch(/(Vergunningsplichtig|Melding|Risicoscan|Vergunning)/i);
        expect(stap).toMatch(/(Bijlagen|Samenvatting|Type aanvraag)/i);
    });

    // ---------- Doorklik tot Indienen --------------------------------
    for (let i = 0; i < 6; i++) {
        const stap = await huidigeStap(page);
        if (!stap) break;

        if (stap.toLowerCase().includes('samenvatting')) {
            const akkoord = page.locator('input[type="checkbox"][wire\\:model$="akkoordVerwerkingGegevens"], input[type="checkbox"][name*="akkoordVerwerkingGegevens"]').first();
            await akkoord.check().catch(async () => {
                await page.getByLabel(/Ik ga akkoord dat mijn gegevens/i).check();
            });
            await page.waitForTimeout(400);
        }

        if ((await page.getByRole('button', { name: /indienen/i }).count()) > 0) {
            break;
        }
        await klikVolgende(page);
    }

    // ---------- Indienen ---------------------------------------------
    await test.step('Indienen', async () => {
        const indienenKnop = page.getByRole('button', { name: /indienen/i }).first();
        await expect(indienenKnop).toBeVisible();
        const beginUrl = page.url();
        await indienenKnop.click();
        await page.waitForURL(
            (url) => url.toString() !== beginUrl && ! url.toString().includes('/aanvraag'),
            { timeout: 30_000 },
        );
        expect(page.url()).toMatch(/\/zaken\/[A-Za-z0-9-]+/);
    });

    // ---------- PDF-content: vooraankondiging-zaaktype + skipped sections
    await test.step('PDF-content: vooraankondiging-zaaktype, geen vergunning-secties', async () => {
        const publicId = page.url().match(/\/zaken\/([^/?#]+)/)?.[1];
        const pdf = leesPdfContent(publicId);

        expect(pdf.zaak.zaaktype, 'eindigt op Vooraankondiging-zaaktype').toMatch(/vooraankondiging/i);

        // Geskipte vergunningstappen mogen geen sectie hebben in de PDF.
        // Stap-titels die NIET mogen voorkomen:
        const verbodenSecties = [
            /vergunningsplichtig/i,
            /melding/i,
            /risicoscan/i,
            /vergunningaanvraag.*kenmerken/i,
            /vergunningsaanvraag.*voorzieningen/i,
            /vergunningsaanvraag.*voorwerpen/i,
            /vergunningaanvraag.*maatregelen/i,
            /vergunningsaanvraag.*extra/i,
            /vergunningaanvraag.*overig/i,
        ];
        for (const patroon of verbodenSecties) {
            const gevonden = pdf.sections.find((s) => patroon.test(s.title));
            expect(gevonden, `Sectie matchend ${patroon} mag NIET in vooraankondiging-PDF voorkomen, vond: ${gevonden?.title}`).toBeUndefined();
        }

        // Wel verwacht: evenementnaam in de "Het evenement"-sectie.
        const evenement = vindSectie(pdf, 'evenement');
        expect(evenement).not.toBeNull();
        expect(vindEntryWaarde(evenement, 'naam')).toMatch(/Vooraankondiging Lentefestival/i);
    });
});
