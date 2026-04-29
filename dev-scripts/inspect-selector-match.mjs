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

// Test exact selectoren zoals vulEindigendOp ze bouwt
const MODIFIERS = ['', '.live', '.defer', '.blur', '.debounce.500ms'];
function endsWithSelector(tag, suffix) {
    return MODIFIERS.map((m) => `${tag}[wire\\:model${m.replace(/\./g, '\\.')}$="${suffix}"]`).join(', ');
}

const sel1 = endsWithSelector('input', '.naamVanDeLocatieGebouw');
const sel2 = endsWithSelector('input', '.adresVanHetGebouwWaarUwEvenementPlaatsvindt1.postcode');
const sel3 = endsWithSelector('input', '.adresVanHetGebouwWaarUwEvenementPlaatsvindt1.huisnummer');

console.log('selector 1 (naam):');
console.log(`  ${sel1}`);
console.log(`  matches: ${await page.locator(sel1).count()}`);
console.log();
console.log('selector 2 (postcode):');
console.log(`  ${sel2}`);
console.log(`  matches: ${await page.locator(sel2).count()}`);
console.log();
console.log('selector 3 (huisnummer):');
console.log(`  ${sel3}`);
console.log(`  matches: ${await page.locator(sel3).count()}`);

await browser.close();
