import { execSync } from 'node:child_process';
import { expect, request as playwrightRequest } from '@playwright/test';
import { loginAlsOrganiser, openFormulier } from './login.mjs';
import {
    vulTekst,
    vulTextarea,
    vulEindigendOp,
    kiesRadio,
    kiesRadioOptioneel,
    kiesSelect,
    klikVolgende,
    huidigeStap,
} from './form-invullen.mjs';

/**
 * Gedeelde wizard-flows voor Playwright-specs zodat de standaard-stappen
 * (Contactgegevens → Vergunningsoort) niet in elke spec opnieuw uit-
 * geschreven hoeven worden. Elke helper dekt één wizard-stap; specs
 * componeren ze in volgorde en injecteren afwijkingen via parameters.
 */

/**
 * Leeg de Draft-DB voor de test-organisator. Probeer eerst het HTTP-
 * endpoint (POST /_test/reset-draft, alleen actief in local/testing)
 * zodat de helper werkt in zowel directe `npx playwright test`-runs op
 * de Mac als in de Docker-runner (scripts/run-playwright.sh) — die
 * laatste heeft geen toegang tot `./vendor/bin/sail` (nested Docker).
 * Valt terug op execSync sail bij netwerk-issues.
 */
export async function leegDraftDb() {
    const baseUrl = process.env.EF_BASE_URL || 'http://localhost';
    try {
        const ctx = await playwrightRequest.newContext({ baseURL: baseUrl });
        const resp = await ctx.post('/_test/reset-draft', {
            form: { email: 'noah.degraaf@example.net' },
            timeout: 10_000,
        });
        await ctx.dispose();
        if (resp.ok()) {
            return;
        }
    } catch {
        // endpoint niet bereikbaar — val terug op sail
    }
    execSync(
        './vendor/bin/sail exec laravel.test php -r \'require "vendor/autoload.php"; $a = require "bootstrap/app.php"; $a->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); \\App\\EventForm\\Persistence\\Draft::whereHas("user", fn ($q) => $q->where("email", "noah.degraaf@example.net"))->delete();\'',
        { stdio: 'pipe', timeout: 30_000 },
    );
}

export async function verseStart(page) {
    await leegDraftDb();
    await loginAlsOrganiser(page);
    await openFormulier(page);
}

export async function stap1Contactgegevens(page) {
    await vulTekst(page, 'postcode1', '6411CD').catch(() => {});
    await vulTekst(page, 'huisnummer1', '1').catch(() => {});
    await vulTekst(page, 'straatnaam1', 'Marktplein').catch(() => {});
    await vulTekst(page, 'plaatsnaam1', 'Heerlen').catch(() => {});
    await klikVolgende(page);
}

export async function stap2HetEvenement(page, { naam = 'Test Evenement', omschrijving = 'Test omschrijving', soort = 'Festival' } = {}) {
    expect(await huidigeStap(page)).toMatch(/Het evenement/i);
    await vulTekst(page, 'watIsDeNaamVanHetEvenementVergunning', naam);
    await page.waitForTimeout(800);
    await vulTextarea(page, 'geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning', omschrijving);
    await kiesSelect(page, 'soortEvenement', soort);
    await page.waitForTimeout(500);
    await klikVolgende(page);
}

export async function stap3LocatieGebouw(page, { naamLocatie = 'Theater Heerlen' } = {}) {
    expect(await huidigeStap(page)).toMatch(/Locatie/i);
    await page.getByRole('checkbox', { name: /In een gebouw/i }).check();
    await page.waitForTimeout(1500);
    await page.getByRole('button', { name: /Adres toevoegen/i }).click();
    await page.waitForTimeout(1500);
    await vulEindigendOp(page, 'input', '.naamVanDeLocatieGebouw', naamLocatie);
    await vulEindigendOp(page, 'input', '.adresVanHetGebouwWaarUwEvenementPlaatsvindt1.postcode', '6411CD');
    await page.keyboard.press('Tab');
    await page.waitForTimeout(500);
    await vulEindigendOp(page, 'input', '.adresVanHetGebouwWaarUwEvenementPlaatsvindt1.huisnummer', '1');
    await page.keyboard.press('Tab');
    await page.waitForTimeout(4000);
    await klikVolgende(page);
}

/**
 * Tijden-stap. Default: alleen Publiek-tijden. Met `metOpbouw=true` en
 * `metAfbouw=true` worden extra datetime-velden ingevuld zodat de PDF
 * de 3-rijen-tijdentabel toont.
 */
export async function stap4Tijden(page, {
    publiekStart = '2026-08-15T16:00',
    publiekEind = '2026-08-16T23:00',
    metOpbouw = false,
    opbouwStart = '2026-08-15T08:00',
    opbouwEind = '2026-08-15T15:00',
    metAfbouw = false,
    afbouwStart = '2026-08-16T23:00',
    afbouwEind = '2026-08-17T03:00',
} = {}) {
    expect(await huidigeStap(page)).toMatch(/Tijden/i);

    await vulTekst(page, 'EvenementStart', publiekStart);
    await vulTekst(page, 'EvenementEind', publiekEind);

    await kiesRadio(page, 'zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten', metOpbouw ? 'Ja' : 'Nee').catch(() => {});
    if (metOpbouw) {
        await page.waitForTimeout(800);
        await vulTekst(page, 'OpbouwStart', opbouwStart).catch(() => {});
        await vulTekst(page, 'OpbouwEind', opbouwEind).catch(() => {});
    }
    await kiesRadio(page, 'zijnErTijdensHetEvenementXOpbouwactiviteiten', 'Nee').catch(() => {});
    await kiesRadio(page, 'zijnErAansluitendAanHetEvenementAfbouwactiviteiten', metAfbouw ? 'Ja' : 'Nee').catch(() => {});
    if (metAfbouw) {
        await page.waitForTimeout(800);
        await vulTekst(page, 'AfbouwStart', afbouwStart).catch(() => {});
        await vulTekst(page, 'AfbouwEind', afbouwEind).catch(() => {});
    }
    await kiesRadio(page, 'zijnErTijdensHetEvenementXAfbouwactiviteiten3', 'Nee').catch(() => {});
    await page.waitForTimeout(800);
    await klikVolgende(page);
}

export async function stap5Vooraankondiging(page, keuze = 'evenement') {
    expect(await huidigeStap(page)).toMatch(/Vooraankondiging/i);
    await kiesRadio(page, 'waarvoorWiltUEventloketGebruiken', keuze);
    await page.waitForTimeout(600);
    await klikVolgende(page);
}

const SCAN_VRAGEN_JA = [
    'isHetAantalAanwezigenBijUwEvenementMinderDanSdf',
    'vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen',
    'WordtErAlleenMuziekGeluidGeproduceerdTussen',
    'IsdeGeluidsproductieLagerDan',
    'erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten',
    'wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst',
    'indienErObjectenGeplaatstWordenZijnDezeDanKleiner',
    'meldingvraag1', 'meldingvraag2', 'meldingvraag3', 'meldingvraag4', 'meldingvraag5',
];

/** Cascade alle Ja → wegen=Ja → vergunning-tak */
export async function stap6ScanVergunning(page) {
    expect(await huidigeStap(page)).toMatch(/Vergunningsplichtig/i);
    for (const key of SCAN_VRAGEN_JA) {
        await kiesRadioOptioneel(page, key, 'Ja');
        await page.waitForTimeout(700);
    }
    await kiesRadioOptioneel(page, 'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer', 'Ja');
    await page.waitForTimeout(500);
    await klikVolgende(page);
}

/** Cascade alle Ja → wegen=Nee → meldings-tak */
export async function stap6ScanMelding(page) {
    expect(await huidigeStap(page)).toMatch(/Vergunningsplichtig/i);
    for (const key of SCAN_VRAGEN_JA) {
        await kiesRadioOptioneel(page, key, 'Ja');
        await page.waitForTimeout(700);
    }
    await kiesRadioOptioneel(page, 'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer', 'Nee');
    await page.waitForTimeout(400);
    await klikVolgende(page);
}

const RISICO_LAAG = [
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

export async function stap8Risicoscan(page) {
    expect(await huidigeStap(page)).toMatch(/Risicoscan/i);
    for (const [key, val] of RISICO_LAAG) {
        await kiesRadioOptioneel(page, key, val);
        await page.waitForTimeout(250);
    }
    await klikVolgende(page);
}

/**
 * Vergunningsaanvraag-soort-stap. Met `extraKenmerken: ['A3', 'A4', 'A5']`
 * vink je extra checkboxes aan op `kruisAanWatVanToepassingIsVoorUwEvenementX`
 * (A5 = alcohol-vergunning-trigger). Met `extraOverigeKenmerken: ['A48', 'A51']`
 * idem op `kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX`.
 */
export async function stap9VergunningSoort(page, {
    extraKenmerken = [],
    extraOverigeKenmerken = [],
} = {}) {
    expect(await huidigeStap(page)).toMatch(/Vergunningsaanvraag/i);
    await kiesRadioOptioneel(page, 'voordatUVerderGaatMetHetBeantwoordenVanDeVragenVoorUwEvenementWillenWeGraagWetenOfUEerderEenVooraankondigingHeeftIngevuldVoorDitEvenement', 'Nee');
    await page.waitForTimeout(300);
    await vulTekst(page, 'watIsTijdensDeHeleDuurVanUwEvenementWatIsDeNaamVanHetEvenementVergunningHetTotaalAantalAanwezigePersonenVanAlleDagenBijElkaarOpgeteld', '500').catch(() => {});
    await vulTekst(page, 'watIsHetMaximaalAanwezigeAantalPersonenDatOpEnigMomentAanwezigKanZijnBijUwEvenementX', '500').catch(() => {});
    await kiesRadioOptioneel(page, 'watZijnDeBelangrijksteLeeftijdscategorieenVanHetPubliekTijdensUwEvenement', '45JaarEnOuder');
    await page.waitForTimeout(300);
    await kiesRadioOptioneel(page, 'isUwEvenementXGratisToegankelijkVoorHetPubliek', 'Ja');
    await page.waitForTimeout(300);
    await kiesRadioOptioneel(page, 'isUwEvenementToegankelijkVoorMensenMetEenBeperking', 'Nee');
    await page.waitForTimeout(400);

    // Extra kenmerk-vinkjes voor ontheffingen-scenario.
    for (const code of extraKenmerken) {
        const cb = page.locator(`input[type=checkbox][value="${code}"]`).first();
        await cb.check({ timeout: 2000 }).catch(() => {});
        await page.waitForTimeout(400);
    }
    for (const code of extraOverigeKenmerken) {
        const cb = page.locator(`input[type=checkbox][value="${code}"]`).first();
        await cb.check({ timeout: 2000 }).catch(() => {});
        await page.waitForTimeout(400);
    }

    await klikVolgende(page);
}

/**
 * Generieke handler-loop die door de resterende stappen klikt tot we
 * de Indienen-knop tegenkomen. Specs kunnen extra stap-handlers
 * meegeven (key = case-insensitive substring van de stap-titel).
 */
export async function klikDoorTotIndienen(page, extraHandlers = {}) {
    const standaardHandlers = {
        'melding': async () => {
            // Drie verplichte radios op de Melding-stap (alcohol /
            // drones / brandveiligheid). Voor de meeste scenarios is
            // 'Nee' op alle drie de kortste route door.
            await kiesRadioOptioneel(page, 'wordtErAlcoholGeschonkenTijdensUwEvenement', 'Nee');
            await page.waitForTimeout(400);
            await kiesRadioOptioneel(page, 'wordenErFilmopnamesMetBehulpVanDronesGemaakt', 'Nee');
            await page.waitForTimeout(400);
            await kiesRadioOptioneel(page, 'vindenErActiviteitenPlaatsWaarvoorMogelijkBrandveiligheidseisenGelden', 'Nee');
            await page.waitForTimeout(400);
        },
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

    const handlers = { ...standaardHandlers, ...extraHandlers };

    for (let i = 0; i <= 25; i++) {
        const stap = await huidigeStap(page);
        if (! stap) break;

        const sleutel = Object.keys(handlers).find((k) => stap.toLowerCase().includes(k));
        if (sleutel) await handlers[sleutel]();

        if ((await page.getByRole('button', { name: /indienen/i }).count()) > 0) break;

        try {
            await klikVolgende(page);
        } catch (e) {
            console.log(`⏸️ halt op "${stap}": ${e.message.split('\n')[0].slice(0, 140)}`);
            return;
        }
    }
}

/**
 * Klikt Indienen, wacht tot we van /aanvraag wegnavigeren, en geeft
 * de zaak-identifier (UUID uit de URL) terug.
 */
export async function indienen(page) {
    const knop = page.getByRole('button', { name: /indienen/i }).first();
    await expect(knop).toBeVisible();
    const beginUrl = page.url();
    await knop.click();
    await page.waitForURL(
        (url) => url.toString() !== beginUrl && ! url.toString().includes('/aanvraag'),
        { timeout: 30_000 },
    );
    expect(page.url()).toMatch(/\/zaken\/[A-Za-z0-9-]+/);

    return page.url().match(/\/zaken\/([^/?#]+)/)?.[1];
}
