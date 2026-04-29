import { chromium } from 'playwright';

const browser = await chromium.launch({ headless: true });
const ctx = await browser.newContext({ viewport: { width: 1400, height: 1200 } });
const page = await ctx.newPage();

await page.goto('http://localhost/organiser/login');
await page.locator('input[wire\\:model="data.email"]').fill('noah.degraaf@example.net');
await page.locator('input[wire\\:model="data.password"]').fill('password');
await page.getByRole('button', { name: /inloggen/i }).click();
await page.waitForURL(/\/organiser\/[0-9a-f]{8}/, { timeout: 15000 });

const tenant = new URL(page.url()).pathname.split('/')[2];
await page.goto(`http://localhost/organiser/${tenant}/aanvraag?step=form.2186344f-9821-45d1-bd52-9900ae15fcb6`);
await page.waitForLoadState('networkidle');

await page.getByRole('checkbox', { name: /In een gebouw/i }).check();
await page.waitForTimeout(1500);
await page.getByRole('button', { name: /Toevoegen aan adres van de gebouw/i }).click();
await page.waitForTimeout(2000);

const html = await page.content();
// Dump ook het exacte modifier (live/defer/blur/etc) per wire:model
const full = [...html.matchAll(/(wire:model(?:\.\w+)?)="([^"]+)"/g)];
console.log('Exacte wire:model attrs op adres-velden:');
for (const m of full) {
    if (m[2].toLowerCase().includes('adresvanhetgebouw')) {
        console.log(`  ${m[1]}="${m[2]}"`);
    }
}
console.log();
const alles = full.map(m => m[2]);
const gebouw = alles.filter(p => p.toLowerCase().includes('adresvandegebouwen') || p.toLowerCase().includes('naamvandelocatiegebouw') || p.toLowerCase().includes('adresvanhetgebouw'));
console.log('Wire-paden rond gebouw-tak:');
for (const p of gebouw) console.log('  ' + p);

// Test de selectoren die de walkthrough gebruikt
console.log('\nSelectoren-counts:');
for (const sel of [
    'input[wire\\:model^="data.adresVanDeGebouwEn."][wire\\:model$=".naamVanDeLocatieGebouw"]',
    'input[wire\\:model*="adresVanHetGebouwWaarUwEvenementPlaatsvindt1.postcode"]',
    'input[wire\\:model*="adresVanHetGebouwWaarUwEvenementPlaatsvindt1"][wire\\:model$=".postcode"]',
    'input[wire\\:model$=".postcode"]',
    'input[wire\\:model$="huisnummer"]',
]) {
    const c = await page.locator(sel).count();
    console.log(`  [${c}] ${sel}`);
}

await browser.close();
