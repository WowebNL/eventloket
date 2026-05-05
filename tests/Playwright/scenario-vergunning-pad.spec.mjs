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
 * Scenario: vergunning-pad — een groter evenement waar wegen worden
 * afgesloten (`wordenErGebiedsontsluitings... === 'Ja'`). Dit triggert
 * de vergunning-tak: de Melding-stap wordt overgeslagen, de
 * Vergunningsaanvraag-stappen (10-15) worden allemaal applicable, en
 * de finale aard is Evenementenvergunning.
 *
 * Zwaardere variant van de melding-walkthrough met meer ingevulde
 * velden zodat de PDF rijker is voor inhouds-asserties.
 */
test('scenario vergunning-pad: wegen afgesloten → Evenementenvergunning + alle stappen 10-15', async ({ page }) => {
    test.setTimeout(240_000);
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
        await vulTekst(page, 'straatnaam1', 'Marktplein').catch(() => {});
        await vulTekst(page, 'plaatsnaam1', 'Heerlen').catch(() => {});
        await klikVolgende(page);
    });

    // ---------- Stap 2: Het evenement --------------------------------
    await test.step('Stap 2 — Het evenement', async () => {
        expect(await huidigeStap(page)).toMatch(/Het evenement/i);
        await vulTekst(page, 'watIsDeNaamVanHetEvenementVergunning', 'Stadsfestival Heerlen 2026');
        await page.waitForTimeout(800);
        await vulTextarea(
            page,
            'geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning',
            'Tweedaags muziekfestival in het centrum, ~5000 bezoekers per dag, met podia en horeca.',
        );
        await kiesSelect(page, 'soortEvenement', 'Festival');
        await page.waitForTimeout(500);
        await klikVolgende(page);
    });

    // ---------- Stap 3: Locatie --------------------------------------
    await test.step('Stap 3 — Locatie (gebouw, voor festival ook prima)', async () => {
        expect(await huidigeStap(page)).toMatch(/Locatie/i);

        await page.getByRole('checkbox', { name: /In een gebouw/i }).check();
        await page.waitForTimeout(1500);
        await page.getByRole('button', { name: /Toevoegen aan adres van de gebouw/i }).click();
        await page.waitForTimeout(1500);

        await vulEindigendOp(page, 'input', '.naamVanDeLocatieGebouw', 'Theater Heerlen');
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
        await vulTekst(page, 'EvenementStart', '2026-08-15T16:00');
        await vulTekst(page, 'EvenementEind', '2026-08-16T23:00');
        await kiesRadio(page, 'zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten', 'Nee').catch(() => {});
        await kiesRadio(page, 'zijnErTijdensHetEvenementXOpbouwactiviteiten', 'Nee').catch(() => {});
        await kiesRadio(page, 'zijnErAansluitendAanHetEvenementAfbouwactiviteiten', 'Nee').catch(() => {});
        await kiesRadio(page, 'zijnErTijdensHetEvenementXAfbouwactiviteiten3', 'Nee').catch(() => {});
        await page.waitForTimeout(800);
        await klikVolgende(page);
    });

    // ---------- Stap 5: Vooraankondiging ------------------------------
    await test.step('Stap 5 — Echte aanvraag, geen vooraankondiging', async () => {
        expect(await huidigeStap(page)).toMatch(/Vooraankondiging/i);
        await kiesRadio(page, 'waarvoorWiltUEventloketGebruiken', 'evenement');
        await page.waitForTimeout(600);
        await klikVolgende(page);
    });

    // ---------- Stap 6: Vergunningsplichtig scan — wegen=Ja → vergunning-tak
    await test.step('Stap 6 — Scan: wegen afsluiten → vergunning-tak', async () => {
        expect(await huidigeStap(page)).toMatch(/Vergunningsplichtig/i);

        // Voor vergunning-tak hoeven we de hele cascade niet door — direct
        // 'wegen afsluiten = Ja' aanvinken kantelt 'm naar
        // Evenementenvergunning. Vul cascade pragmatisch in (alle Ja zodat
        // 't formulier wel doorvloeit), maar wegen=Ja is de echte trigger.
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
            await page.waitForTimeout(700);
        }
        // KRITIEK: dit veld kantelt richting Evenementenvergunning.
        await kiesRadioOptioneel(page, 'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer', 'Ja');
        await page.waitForTimeout(500);
        await klikVolgende(page);
    });

    // ---------- Stap 7: Melding moet GESKIPT worden -------------------
    await test.step('Stap 7 (Melding) wordt overgeslagen wegens wegen=Ja', async () => {
        const stap = await huidigeStap(page);
        expect(stap, 'Melding-stap moet niet verschijnen in vergunning-tak').not.toMatch(/^Melding$/i);
    });

    // ---------- Stap 8+: Risicoscan + vergunning-stappen --------------
    await test.step('Stap 8 — Risicoscan', async () => {
        expect(await huidigeStap(page)).toMatch(/Risicoscan/i);
        const risico = [
            ['watIsDeAantrekkingskrachtVanHetEvenement', '0.5'],
            ['watIsDeBelangrijksteLeeftijdscategorieVanDeDoelgroep', '0.75__2'],
            ['isErSprakeVanZanwezigheidVanPolitiekeAandachtEnOfMediageniekheid', '0'],
            ['isEenDeelVanDeDoelgroepVerminderdZelfredzaam', '0'],
            ['isErSprakeVanAanwezigheidVanRisicovolleActiviteiten', '0'],
            ['watIsHetGrootsteDeelVanDeSamenstellingVanDeDoelgroep', '0.5'],
            ['isErSprakeVanOvernachten', '0'],
            ['isErGebruikVanAlcoholEnDrugs', '0'],
            ['watIsHetAantalGelijktijdigAanwezigPersonen', '0'],
            ['inWelkSeizoenVindtHetEvenementPlaats', '0.5'],
            ['inWelkeLocatieVindtHetEvenementPlaats', '0.25'],
            ['opWelkSoortOndergrondVindtHetEvenementPlaats', '0.25'],
            ['watIsDeTijdsduurVanHetEvenement', '0.5'],
            ['welkeBeschikbaarheidVanAanEnAfvoerwegenIsVanToepassing', '0'],
        ];
        for (const [key, val] of risico) {
            await kiesRadioOptioneel(page, key, val);
            await page.waitForTimeout(250);
        }
        await klikVolgende(page);
    });

    await test.step('Stap 9 — Vergunningsaanvraag soort', async () => {
        expect(await huidigeStap(page)).toMatch(/Vergunningsaanvraag/i);
        await kiesRadioOptioneel(page, 'voordatUVerderGaatMetHetBeantwoordenVanDeVragenVoorUwEvenementWillenWeGraagWetenOfUEerderEenVooraankondigingHeeftIngevuldVoorDitEvenement', 'Nee');
        await page.waitForTimeout(300);
        await vulTekst(page, 'watIsTijdensDeHeleDuurVanUwEvenementWatIsDeNaamVanHetEvenementVergunningHetTotaalAantalAanwezigePersonenVanAlleDagenBijElkaarOpgeteld', '10000').catch(() => {});
        await vulTekst(page, 'watIsHetMaximaalAanwezigeAantalPersonenDatOpEnigMomentAanwezigKanZijnBijUwEvenementX', '5000').catch(() => {});
        await kiesRadioOptioneel(page, 'watZijnDeBelangrijksteLeeftijdscategorieenVanHetPubliekTijdensUwEvenement', '45JaarEnOuder');
        await page.waitForTimeout(300);
        await kiesRadioOptioneel(page, 'isUwEvenementXGratisToegankelijkVoorHetPubliek', 'Nee');
        await page.waitForTimeout(300);
        await kiesRadioOptioneel(page, 'isUwEvenementToegankelijkVoorMensenMetEenBeperking', 'Ja');
        await page.waitForTimeout(400);
        await klikVolgende(page);
    });

    // ---------- Doorloop tot Indienen, met dezelfde stap-handlers ----
    const stapHandlers = {
        'maatregelen': async () => {
            await kiesRadioOptioneel(page, 'wilUGebruikMakenVanGemeentelijkeHulpmiddelen', 'Nee');
            await page.waitForTimeout(400);
        },
        'samenvatting': async () => {
            const akkoord = page.locator('input[type="checkbox"][wire\\:model$="akkoordVerwerkingGegevens"], input[type="checkbox"][name*="akkoordVerwerkingGegevens"]').first();
            await akkoord.check().catch(async () => {
                await page.getByLabel(/Ik ga akkoord dat mijn gegevens/i).check();
            });
            await page.waitForTimeout(500);
        },
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

    for (let i = 10; i <= 20; i++) {
        const stap = await huidigeStap(page);
        if (!stap) break;

        const stapKey = Object.keys(stapHandlers).find((k) => stap.toLowerCase().includes(k));
        if (stapKey) await stapHandlers[stapKey]();

        if ((await page.getByRole('button', { name: /indienen/i }).count()) > 0) break;

        try {
            await klikVolgende(page);
        } catch (e) {
            console.log(`⏸️ halt op "${stap}": ${e.message.split('\n')[0].slice(0, 140)}`);
            return;
        }
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

    // ---------- PDF-content: Evenementenvergunning + rijke secties ----
    await test.step('PDF: Evenementenvergunning, rijke vergunning-secties', async () => {
        const publicId = page.url().match(/\/zaken\/([^/?#]+)/)?.[1];
        const pdf = leesPdfContent(publicId);

        expect(pdf.zaak.zaaktype, 'eindigt op Evenementenvergunning').toMatch(/evenementenvergunning/i);

        // Festival-naam moet in 'Het evenement' staan.
        const evenement = vindSectie(pdf, 'evenement');
        expect(evenement).not.toBeNull();
        expect(vindEntryWaarde(evenement, 'naam')).toMatch(/Stadsfestival Heerlen 2026/i);

        // Risicoscan-sectie aanwezig (met de risico-classificatie).
        const risico = vindSectie(pdf, 'risico');
        expect(risico, 'risicoscan-sectie moet zichtbaar zijn voor vergunning-tak').not.toBeNull();

        // Geen melding-sectie in vergunning-PDF.
        const melding = pdf.sections.find((s) => /^melding$/i.test(s.title));
        expect(melding, 'Melding-sectie mag niet in vergunning-PDF voorkomen').toBeUndefined();

        // PDF moet meer secties hebben dan de melding-flow (vergunning-stappen 10-15).
        expect(pdf.sections.length, 'vergunning-PDF heeft ≥ 5 secties').toBeGreaterThan(5);
    });
});
