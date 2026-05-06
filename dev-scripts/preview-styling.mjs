import { chromium } from 'playwright';

const BASE = 'http://localhost';
const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1400, height: 1400 } });
const page = await context.newPage();

await page.goto(`${BASE}/organiser/login`);
await page.locator('input[wire\\:model="data.email"]').fill('noah.degraaf@example.net');
await page.locator('input[wire\\:model="data.password"]').fill('password');
await page.getByRole('button', { name: /inloggen/i }).click();
await page.waitForLoadState('networkidle');
const tenant = new URL(page.url()).pathname.split('/')[2];

// Stap 1 — fieldsets
await page.goto(`${BASE}/organiser/${tenant}/aanvraag`);
await page.waitForLoadState('networkidle');
await page.waitForTimeout(500);
await page.screenshot({ path: '/tmp/styling-stap1.png', fullPage: true });

// Stap 4 — tabel-content
await page.goto(`${BASE}/organiser/${tenant}/aanvraag?step=form.00f09aee-fedd-44d6-b82c-3e3754d67b7a`);
await page.waitForLoadState('networkidle');
await page.waitForTimeout(500);
await page.screenshot({ path: '/tmp/styling-stap4-tijden.png', fullPage: true });

await browser.close();
console.log('Screenshots: /tmp/styling-stap1.png, /tmp/styling-stap4-tijden.png');
