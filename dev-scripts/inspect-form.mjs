// Playwright-inspectie-script voor de Filament event-form. Logt in als
// organiser, klikt door naar stap 3, en maakt screenshots op kritieke
// momenten zodat we (Claude) visueel kunnen debuggen zonder steeds om
// user-screenshots te hoeven vragen.
//
// Run: node dev-scripts/inspect-form.mjs
// Screenshots komen in /tmp/ef-*.png.

import { chromium } from 'playwright';

const BASE = 'http://localhost';
const USER = process.env.EF_USER || 'noah.degraaf@example.net';
const PASS = process.env.EF_PASS || 'password';

const shot = async (page, name) => {
    const path = `/tmp/ef-${name}.png`;
    await page.screenshot({ path, fullPage: true });
    console.log(`  📸  ${path}`);
};

const run = async () => {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ viewport: { width: 1400, height: 900 } });
    const page = await context.newPage();

    // Log JS-errors voor debugging.
    page.on('pageerror', (err) => console.log(`  ⚠ pageerror: ${err.message}`));
    page.on('console', (msg) => {
        if (msg.type() === 'error') console.log(`  ⚠ console.error: ${msg.text()}`);
    });

    console.log('→ login');
    await page.goto(`${BASE}/organiser/login`);
    await page.fill('input[type=email]', USER);
    await page.fill('input[type=password]', PASS);
    await page.click('button[type=submit]');
    await page.waitForLoadState('networkidle');
    await shot(page, '01-after-login');

    console.log('→ navigeren naar /aanvraag via eerste beschikbare tenant');
    const url = page.url();
    console.log(`   current: ${url}`);
    // Probeer direct naar /aanvraag te navigeren op huidige tenant-URL
    const tenantMatch = url.match(/organiser\/([^\/]+)/);
    if (tenantMatch) {
        await page.goto(`${BASE}/organiser/${tenantMatch[1]}/aanvraag`);
        await page.waitForLoadState('networkidle');
    }
    await shot(page, '02-aanvraag-step1');

    // Navigeer direct naar stap 3 via query-string om validatie-gedoe
    // van stap 1/2 te omzeilen. De URL-state zet stap 3 actief; Livewire
    // re-hydrateerd met correct form-state.
    console.log('→ direct naar stap 3 (Locatie) via query-param');
    const step3Uuid = '2186344f-9821-45d1-bd52-9900ae15fcb6';
    const base = page.url().split('?')[0];
    await page.goto(`${base}?step=form.${step3Uuid}`);
    await page.waitForLoadState('networkidle');
    await shot(page, '04-step3-fresh');

    // CheckboxList options hebben expliciete <input type=checkbox> elements;
    // Filament zet de label naast een input met value="gebouw"/"buiten"/"route".
    const clickOption = async (value) => {
        try {
            const input = page.locator(`input[type=checkbox][value="${value}"]`).first();
            await input.click({ timeout: 3000 });
            await page.waitForTimeout(1200); // Livewire roundtrip
            return true;
        } catch (e) {
            console.log(`  (kon optie ${value} niet klikken: ${e.message.split('\n')[0]})`);
            return false;
        }
    };

    console.log('→ klik "In een gebouw"');
    await clickOption('gebouw');
    await shot(page, '05-step3-gebouw');

    console.log('→ uit-gebouw, aan-buiten');
    await clickOption('gebouw');
    await clickOption('buiten');
    await shot(page, '06-step3-buiten');

    console.log('→ uit-buiten, aan-route');
    await clickOption('buiten');
    await clickOption('route');
    await shot(page, '07-step3-route');

    await browser.close();
    console.log('klaar.');
};

run().catch((e) => { console.error(e); process.exit(1); });
