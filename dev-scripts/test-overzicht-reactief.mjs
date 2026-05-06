import { chromium } from 'playwright';

const BASE = 'http://localhost';
const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1400, height: 1200 } });
const page = await context.newPage();

await page.goto(`${BASE}/organiser/login`);
await page.locator('input[wire\\:model="data.email"]').fill('noah.degraaf@example.net');
await page.locator('input[wire\\:model="data.password"]').fill('password');
await page.getByRole('button', { name: /inloggen/i }).click();
await page.waitForLoadState('networkidle');
const tenant = new URL(page.url()).pathname.split('/')[2];

// Ga naar Tijden
await page.goto(`${BASE}/organiser/${tenant}/aanvraag?step=form.00f09aee-fedd-44d6-b82c-3e3754d67b7a`);
await page.waitForLoadState('networkidle');
await page.waitForTimeout(800);

// Vul EvenementStart in
const evenementStart = page.locator('input[wire\\:model\\.live="data.EvenementStart"], input[wire\\:model="data.EvenementStart"]').first();
await evenementStart.fill('2026-06-15T12:00');
await page.waitForTimeout(1500);

await page.screenshot({ path: '/tmp/tijden-reactief.png', fullPage: true });

// Check of de EvenementStart waarde in de tabel staat
const html = await page.content();
const heeftStart = html.includes('15-06-2026') || html.includes('2026-06-15') || html.includes('15 juni 2026');
console.log(`Datum 2026-06-15 in rendered overzicht: ${heeftStart ? 'JA (goed)' : 'nee — overzicht blijft leeg'}`);

await browser.close();
