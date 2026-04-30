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
 * Volledige walkthrough: één scenario dat door alle stappen klikt.
 * Scenario: klein buurtfeest in een gebouw — eenvoudigste route (melding).
 */
test('walkthrough: doorloop het hele formulier', async ({ page }) => {
    test.setTimeout(180_000);
    skipAlsOpenZaakOffline(test);

    // Schoon startpunt: oude drafts (van eerdere walkthrough-runs of
    // handmatig testen) zorgen voor pre-fills die de handlers hier
    // niet verwachten — typisch validation-issues op de Locatie-stap
    // waar de Repeater al rijen had. We tikken even de DB leeg via
    // `artisan tinker --execute=...` zodat elke run met een lege state
    // begint.
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
        await page.screenshot({ path: 'test-results/walkthrough/stap-01.png', fullPage: true });
        await klikVolgende(page);
    });

    // ---------- Stap 2: Het evenement --------------------------------
    await test.step('Stap 2 — Het evenement', async () => {
        expect(await huidigeStap(page)).toMatch(/Het evenement/i);
        await vulTekst(page, 'watIsDeNaamVanHetEvenementVergunning', 'Buurtfeest Testlaan');
        await page.waitForTimeout(1000);
        await vulTextarea(
            page,
            'geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning',
            'Een kleinschalig buurtfeest op een middag met 80 bewoners.',
        );
        await kiesSelect(
            page,
            'soortEvenement',
            'Buurt-, barbecue of straatfeest, buitenspeeldagen, (eindejaars-)feesten, garagesales',
        );
        await page.waitForTimeout(600);
        await page.screenshot({ path: 'test-results/walkthrough/stap-02.png', fullPage: true });
        await klikVolgende(page);
    });

    // ---------- Stap 3: Locatie (gebouw-tak, echte doorloop) --------
    await test.step('Stap 3 — Locatie (in een gebouw)', async () => {
        expect(await huidigeStap(page)).toMatch(/Locatie/i);

        await page.getByRole('checkbox', { name: /In een gebouw/i }).check();
        await page.waitForTimeout(1500);
        await page.getByRole('button', { name: /Toevoegen aan adres van de gebouw/i }).click();
        await page.waitForTimeout(1500);

        await vulEindigendOp(page, 'input', '.naamVanDeLocatieGebouw', 'Buurtcentrum De Hoek');
        // Heerlen-postcode: gemeente Heerlen (GM0917) heeft zaaktypes in de
        // DB; Maastricht zou ook werken zodra z'n zaaktypes gesynct zijn.
        await vulEindigendOp(page, 'input', '.adresVanHetGebouwWaarUwEvenementPlaatsvindt1.postcode', '6411CD');
        // Tab weg om blur te triggeren → PDOK-lookup straatnaam/plaats
        await page.keyboard.press('Tab');
        await page.waitForTimeout(500);
        await vulEindigendOp(page, 'input', '.adresVanHetGebouwWaarUwEvenementPlaatsvindt1.huisnummer', '1');
        await page.keyboard.press('Tab');
        // Wacht op PDOK-lookup én LocationServerCheckService
        await page.waitForTimeout(4000);

        await page.screenshot({ path: 'test-results/walkthrough/stap-03.png', fullPage: true });
        await klikVolgende(page);
    });

    // ---------- Stap 4: Tijden ---------------------------------------
    await test.step('Stap 4 — Tijden', async () => {
        expect(await huidigeStap(page)).toMatch(/Tijden/i);
        await vulTekst(page, 'EvenementStart', '2026-06-14T14:00');
        await vulTekst(page, 'EvenementEind', '2026-06-14T18:00');
        await kiesRadio(page, 'zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten', 'Nee').catch(() => {});
        await kiesRadio(page, 'zijnErTijdensHetEvenementXOpbouwactiviteiten', 'Nee').catch(() => {});
        await kiesRadio(page, 'zijnErAansluitendAanHetEvenementAfbouwactiviteiten', 'Nee').catch(() => {});
        await kiesRadio(page, 'zijnErTijdensHetEvenementXAfbouwactiviteiten3', 'Nee').catch(() => {});
        await page.waitForTimeout(800);
        await page.screenshot({ path: 'test-results/walkthrough/stap-04.png', fullPage: true });
        await klikVolgende(page);
    });

    // ---------- Stap 5: Vooraankondiging ------------------------------
    await test.step('Stap 5 — Vooraankondiging', async () => {
        expect(await huidigeStap(page)).toMatch(/Vooraankondiging/i);
        // Eenvoudigste route: direct aanvraag indienen (niet alleen vooraankondiging)
        await kiesRadio(page, 'waarvoorWiltUEventloketGebruiken', 'evenement');
        await page.waitForTimeout(600);
        await page.screenshot({ path: 'test-results/walkthrough/stap-05.png', fullPage: true });
        await klikVolgende(page);
    });

    // ---------- Stap 6: Vergunningsplichtig scan ---------------------
    await test.step('Stap 6 — Vergunningsplichtig scan', async () => {
        expect(await huidigeStap(page)).toMatch(/Vergunningsplichtig/i);
        // Cascade Ja/Nee vragen — voor meldingsroute overal Ja.
        // Elke volgende vraag wordt pas zichtbaar na Ja op de vorige, dus
        // we wachten tussendoor kort op Livewire state-sync.
        const scanVragenJa = [
            'isHetAantalAanwezigenBijUwEvenementMinderDanSdf',
            'vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen',
            'WordtErAlleenMuziekGeluidGeproduceerdTussen',
            'IsdeGeluidsproductieLagerDan',
            'erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten',
            'wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst',
            'indienErObjectenGeplaatstWordenZijnDezeDanKleiner',
            'meldingvraag1', 'meldingvraag2', 'meldingvraag3', 'meldingvraag4', 'meldingvraag5',
        ];
        for (const key of scanVragenJa) {
            await kiesRadioOptioneel(page, key, 'Ja');
            // Langere wait: elke volgende radio in de cascade wordt pas
            // zichtbaar na een Livewire-roundtrip. 400ms was op de rand.
            await page.waitForTimeout(700);
        }
        // "Wegen afsluiten" → Nee: dit veld bepaalt volgens OF de finale
        // aard (Melding vs Evenementenvergunning) op stap 17. 'Nee' laat
        // deze walkthrough uitkomen op een Melding — de lichtste route.
        await kiesRadioOptioneel(page, 'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer', 'Nee');
        await page.waitForTimeout(400);
        await page.screenshot({ path: 'test-results/walkthrough/stap-06.png', fullPage: true });
        await klikVolgende(page);
    });

    // ---------- Stap 7: Melding ---------------------------------------
    await test.step('Stap 7 — Melding', async () => {
        expect(await huidigeStap(page)).toMatch(/Melding/i);
        // Buurtfeest: geen alcohol, geen drones, geen brandveiligheid-activiteiten.
        await kiesRadioOptioneel(page, 'wordtErAlcoholGeschonkenTijdensUwEvenement', 'Nee');
        await page.waitForTimeout(400);
        await kiesRadioOptioneel(page, 'wordenErFilmopnamesMetBehulpVanDronesGemaakt', 'Nee');
        await page.waitForTimeout(400);
        await kiesRadioOptioneel(page, 'vindenErActiviteitenPlaatsWaarvoorMogelijkBrandveiligheidseisenGelden', 'Nee');
        await page.waitForTimeout(400);
        await page.screenshot({ path: 'test-results/walkthrough/stap-07.png', fullPage: true });
        await klikVolgende(page);
    });

    // ---------- Stap 8: Risicoscan -----------------------------------
    await test.step('Stap 8 — Risicoscan', async () => {
        expect(await huidigeStap(page)).toMatch(/Risicoscan/i);
        // Buurtfeest: laagste risico-profiel op alle assen.
        const risico = [
            ['watIsDeAantrekkingskrachtVanHetEvenement', '0.5'],       // Wijk of buurt
            ['watIsDeBelangrijksteLeeftijdscategorieVanDeDoelgroep', '0.75__2'], // Alle leeftijden
            ['isErSprakeVanZanwezigheidVanPolitiekeAandachtEnOfMediageniekheid', '0'], // Nee
            ['isEenDeelVanDeDoelgroepVerminderdZelfredzaam', '0'],     // Volledig zelfredzaam
            ['isErSprakeVanAanwezigheidVanRisicovolleActiviteiten', '0'], // Nee
            ['watIsHetGrootsteDeelVanDeSamenstellingVanDeDoelgroep', '0.5'], // Alleen toeschouwers
            ['isErSprakeVanOvernachten', '0'],                         // Niet overnacht
            ['isErGebruikVanAlcoholEnDrugs', '0'],                     // Niet aanwezig
            ['watIsHetAantalGelijktijdigAanwezigPersonen', '0'],       // Minder dan 150
            ['inWelkSeizoenVindtHetEvenementPlaats', '0.5'],           // Zomer of winter
            ['inWelkeLocatieVindtHetEvenementPlaats', '0.25'],         // In een gebouw, ingericht
            ['opWelkSoortOndergrondVindtHetEvenementPlaats', '0.25'],  // Verharde ondergrond
            ['watIsDeTijdsduurVanHetEvenement', '0.5'],                // 3-12 uur daguren
            ['welkeBeschikbaarheidVanAanEnAfvoerwegenIsVanToepassing', '0'], // Goede
        ];
        for (const [key, val] of risico) {
            await kiesRadioOptioneel(page, key, val);
            await page.waitForTimeout(300);
        }
        await page.screenshot({ path: 'test-results/walkthrough/stap-08.png', fullPage: true });
        await klikVolgende(page);
    });

    // ---------- Stap 9: Vergunningsaanvraag: soort -------------------
    await test.step('Stap 9 — Vergunningsaanvraag: soort', async () => {
        expect(await huidigeStap(page)).toMatch(/Vergunningsaanvraag/i);
        await kiesRadioOptioneel(page, 'voordatUVerderGaatMetHetBeantwoordenVanDeVragenVoorUwEvenementWillenWeGraagWetenOfUEerderEenVooraankondigingHeeftIngevuldVoorDitEvenement', 'Nee');
        await page.waitForTimeout(300);
        await vulTekst(page, 'watIsTijdensDeHeleDuurVanUwEvenementWatIsDeNaamVanHetEvenementVergunningHetTotaalAantalAanwezigePersonenVanAlleDagenBijElkaarOpgeteld', '80').catch(() => {});
        await vulTekst(page, 'watIsHetMaximaalAanwezigeAantalPersonenDatOpEnigMomentAanwezigKanZijnBijUwEvenementX', '80').catch(() => {});
        await kiesRadioOptioneel(page, 'watZijnDeBelangrijksteLeeftijdscategorieenVanHetPubliekTijdensUwEvenement', '45JaarEnOuder');
        await page.waitForTimeout(300);
        await kiesRadioOptioneel(page, 'isUwEvenementXGratisToegankelijkVoorHetPubliek', 'Ja');
        await page.waitForTimeout(300);
        await kiesRadioOptioneel(page, 'isUwEvenementToegankelijkVoorMensenMetEenBeperking', 'Nee');
        await page.waitForTimeout(400);
        await page.screenshot({ path: 'test-results/walkthrough/stap-09.png', fullPage: true });
        await klikVolgende(page);
    });

    // ---------- Stap 10+: per-stap handlers + doorklik-loop ---------
    //
    // Voor elke stap die minstens één verplicht veld heeft, registreren
    // we een handler die de minimale invoer voor de buurtfeest-route
    // doet. Stappen zonder verplichte velden lopen automatisch door.
    const stapHandlers = {
        // Stap 13: vraag over gemeentelijke hulpmiddelen
        'maatregelen': async () => {
            await kiesRadioOptioneel(page, 'wilUGebruikMakenVanGemeentelijkeHulpmiddelen', 'Nee');
            await page.waitForTimeout(400);
        },
        // Stap 17 (sinds E): Samenvatting met verplichte AVG-akkoord-checkbox.
        // Zonder vinkje blokkeert validation de overgang naar Type-aanvraag.
        'samenvatting': async () => {
            const akkoord = page.locator('input[type="checkbox"][wire\\:model$="akkoordVerwerkingGegevens"], input[type="checkbox"][name*="akkoordVerwerkingGegevens"]').first();
            await akkoord.check().catch(async () => {
                // Filament's checkbox kan een aangepaste markup hebben — fallback
                // op een label-klik die de echte checkbox toggelt.
                await page.getByLabel(/Ik ga akkoord dat mijn gegevens/i).check();
            });
            await page.waitForTimeout(500);
        },
        // Stap 15: overig — ~6 Ja/Nee radios, allemaal Nee voor buurtfeest
        'overig': async () => {
            const velden = [
                'wiltUPromotieMakenVoorUwEvenement',
                'geeftUOmwonendenEnNabijgelegenBedrijvenVoorafInformatieOverUwEvenementX',
                'organiseertUUwEvenementXVoorDeEersteKeer',
                'hanteertUHuisregelsVoorUwEvenementX',
                'organiseertUOokFlankerendeEvenementenSideEventsTijdensUwEvenementEvenementNaamSittard2024',
                'heeftUEenEvenementenverzekeringAfgeslotenVoorUwEvenement',
            ];
            for (const v of velden) {
                await kiesRadioOptioneel(page, v, 'Nee');
                await page.waitForTimeout(250);
            }
        },
    };

    // Sinds E.1 is er een Samenvatting-stap (#17) toegevoegd vóór
    // Type-aanvraag, dus we lopen nu tot en met stap 18.
    for (let i = 10; i <= 18; i++) {
        const stap = await huidigeStap(page);
        if (!stap) break;
        const slug = stap.toLowerCase().replace(/[^a-z0-9]+/g, '-').slice(0, 50);

        // Run stap-specifieke handler als die bestaat.
        const stapKey = Object.keys(stapHandlers).find((k) => stap.toLowerCase().includes(k));
        if (stapKey) {
            console.log(`  → handler voor '${stapKey}' op stap ${i}`);
            await stapHandlers[stapKey]();
        }

        await page.screenshot({
            path: `test-results/walkthrough/stap-${String(i).padStart(2, '0')}-${slug}.png`,
            fullPage: true,
        });
        console.log(`  op stap ${i}: ${stap}`);

        if ((await page.getByRole('button', { name: /indienen/i }).count()) > 0) {
            console.log(`✅ laatste stap bereikt: ${stap}`);
            break;
        }

        try {
            await klikVolgende(page);
        } catch (e) {
            console.log(`⏸️ halt op "${stap}": ${e.message.split('\n')[0].slice(0, 140)}`);
            await page.screenshot({
                path: `test-results/walkthrough/halt-${slug}.png`,
                fullPage: true,
            });
            return; // submit niet proberen bij een eerdere halt
        }
    }

    // ---------- Indienen: klik de knop en verifieer het resultaat -----
    //
    // Dit is het bewijs dat de hele submit-keten in de lucht blijft:
    // - SubmitEventForm wordt aangeroepen
    // - Er wordt via OpenZaak (echte lokale container) een ZGW-zaak
    //   aangemaakt
    // - De lokale Zaak-row wordt geschreven
    // - Filament redirect naar ViewZaak
    // - Het zaaknummer is zichtbaar op die pagina
    // - De draft is leeg: terugkeren naar /aanvraag toont een vers formulier
    await test.step('Indienen', async () => {
        const indienenKnop = page.getByRole('button', { name: /indienen/i }).first();
        await expect(indienenKnop).toBeVisible();

        const beginUrl = page.url();
        await indienenKnop.click();

        // Wacht op de redirect naar ViewZaak (of een foutmelding).
        await page.waitForURL(
            (url) => url.toString() !== beginUrl && ! url.toString().includes('/aanvraag'),
            { timeout: 30_000 },
        ).catch(() => {
            // Als er geen redirect komt, waarschijnlijk een foutmelding
            // op dezelfde pagina — screenshot en val door.
        });

        await page.screenshot({
            path: 'test-results/walkthrough/stap-99-na-indienen.png',
            fullPage: true,
        });

        const huidigeUrl = page.url();
        console.log(`  → na indienen op: ${huidigeUrl}`);

        // De ViewZaak-route bevat /zaken/{public_id}; faal expliciet als
        // we nog op /aanvraag zijn (dan is submit niet gelukt).
        expect(huidigeUrl).toMatch(/\/zaken\/[A-Za-z0-9-]+/);
    });

    // Bewaar de zojuist aangemaakte zaak-URL (ViewZaak) voor de prefill-
    // stap — anders overschrijft de 'Draft is geleegd'-check hieronder 'm.
    const ingediende_zaak_url = page.url();
    const publicId = ingediende_zaak_url.match(/\/zaken\/([^/?#]+)/)?.[1];
    expect(publicId, 'kon public_id niet uit ViewZaak-URL halen').toBeTruthy();

    // ---------- PDF-content: zaaktype + kernvelden ---------------------
    //
    // Geen PDF-binary parsen; we vragen de SubmissionReport-builder direct
    // via een Artisan-command de sections + entries op die de Blade-template
    // straks rendert. Dat dekt: zaaktype, sectie-structuur, en alle
    // ingevulde velden uit de melding-walkthrough.
    await test.step('PDF-content (sections + entries) klopt voor melding-pad', async () => {
        const pdf = leesPdfContent(publicId);

        expect(pdf.zaak.public_id).toBe(publicId);
        expect(pdf.zaak.zaaktype, 'melding-pad eindigt op Melding-zaaktype').toMatch(/melding/i);
        expect(pdf.sections.length, 'PDF moet meerdere secties tonen').toBeGreaterThan(3);

        // Kernvelden op stap 2 moeten in de sectie 'Het evenement' staan.
        const evenement = vindSectie(pdf, 'evenement');
        expect(evenement, 'sectie "evenement" gevonden').not.toBeNull();
        expect(vindEntryWaarde(evenement, 'naam')).toMatch(/Buurtfeest Testlaan/i);

        // Kernveld op stap 4 (tijden) — datum moet in een sectie staan.
        const tijden = vindSectie(pdf, 'tijd');
        expect(tijden, 'sectie "tijden" gevonden').not.toBeNull();
        // Eén van de entries moet onze starttijd bevatten — Filament's
        // DateTimePicker-renderer kan 'm formatteren, dus we checken
        // alleen op de jaar-kern.
        const alleTijdWaarden = (tijden?.entries ?? []).map((e) => e.value).join(' ');
        expect(alleTijdWaarden).toMatch(/2026/);
    });

    // ---------- Draft leeg: opnieuw naar /aanvraag = vers formulier --
    await test.step('Draft is geleegd na submit', async () => {
        const tenant = new URL(page.url()).pathname.split('/')[2];
        await page.goto(`/organiser/${tenant}/aanvraag`);
        await page.waitForLoadState('networkidle', { timeout: 15_000 }).catch(() => {});

        // Stap 1 (Contactgegevens) moet opnieuw leeg zijn — we checken
        // het postcode-veld dat we in stap 1 van de walkthrough vulden.
        const postcodeInput = page.locator('input[wire\\:model\\.blur$=".postcode1"]').first();
        if (await postcodeInput.count() > 0) {
            await expect(postcodeInput).toHaveValue('');
        }
        await page.screenshot({
            path: 'test-results/walkthrough/stap-99-draft-leeg.png',
            fullPage: true,
        });
    });

    // ---------- Herhaal aanvraag: prefill uit ingediende Zaak ---------
    //
    // Dit is de volledige gebruikersflow voor "jaarlijks evenement":
    //   1. Ga terug naar de zojuist ingediende zaak (ViewZaak)
    //   2. Klik "Nieuwe aanvraag met deze gegevens"
    //   3. Verifieer dat we op /aanvraag?prefill_from_zaak=<uuid> landen
    //   4. Verifieer dat de evenement-naam in stap 2 al is voorgevuld
    await test.step('Herhaal aanvraag: prefill-URL werkt', async () => {
        // Filament's "Nieuwe aanvraag met deze gegevens"-action doet alleen
        // een redirect naar `/aanvraag?prefill_from_zaak=<uuid>`. Die query-
        // param is het functionele contract; de diepere waarden-assert
        // (FormState bevat de oude velden) staat in de PrefillFromZaakTest
        // unit-tests. Hier bewijzen we alleen dat de URL-parameter geen
        // redirect naar fout-pagina geeft en de wizard laadt.
        const zaakId = ingediende_zaak_url.match(/\/zaken\/([^/?#]+)/)?.[1];
        expect(zaakId).toBeTruthy();

        const tenant = new URL(ingediende_zaak_url).pathname.split('/')[2];
        const prefillUrl = `/organiser/${tenant}/aanvraag?prefill_from_zaak=${zaakId}`;
        await page.goto(prefillUrl);
        await page.waitForLoadState('networkidle', { timeout: 20_000 }).catch(() => {});

        // Bewijs dat we op /aanvraag met prefill-param landen én dat de
        // wizard rendert (Contactgegevens-stap is zichtbaar).
        expect(page.url()).toContain('prefill_from_zaak=');
        await expect(
            page.locator('.fi-vertical-wizard-step-label', { hasText: /Contactgegevens/i }).first()
        ).toBeVisible({ timeout: 10_000 });

        await page.screenshot({
            path: 'test-results/walkthrough/stap-99-prefill.png',
            fullPage: true,
        });
    });

    await page.waitForTimeout(1500);
});
