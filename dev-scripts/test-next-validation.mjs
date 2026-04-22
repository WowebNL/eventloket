// Eind-to-eind check: spookmelding weg, sidebar-forward geblokkeerd,
// Volgende-knop visueel disabled bij incomplete required velden.

import { chromium } from 'playwright';

const BASE = 'http://localhost';
const USER = 'noah.degraaf@example.net';
const PASS = 'password';

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1400, height: 900 } });
const page = await context.newPage();

page.on('pageerror', (err) => console.log(`pageerror: ${err.message}`));

await page.goto(`${BASE}/organiser/login`);
await page.fill('input[type=email]', USER);
await page.fill('input[type=password]', PASS);
await page.click('button[type=submit]');
await page.waitForLoadState('networkidle');

const tenantMatch = page.url().match(/organiser\/([^\/]+)/);
await page.goto(`${BASE}/organiser/${tenantMatch[1]}/aanvraag`);
await page.waitForLoadState('networkidle');
await page.waitForTimeout(700);

// 1. Spookmelding weg?
const ghost = await page.locator('text=Een ogenblik geduld').count();
console.log(`1. Spookmelding aanwezig: ${ghost > 0 ? 'JA (bug)' : 'nee (goed)'}`);

// 2. Volgende-knop initial state: moet zijn "not-clickable" omdat Postcode etc. leeg zijn
const nextWrapper = page.locator('div.fi-next-blocked, div:has(> button:text("Volgende"))').first();
// Eenvoudiger: check of de div met Volgende de class fi-next-blocked heeft
const blocked = await page.evaluate(() => {
    const btn = Array.from(document.querySelectorAll('button')).find(b => b.textContent?.trim() === 'Volgende');
    if (!btn) return 'no-next-button';
    const wrapper = btn.closest('.fi-next-blocked');
    return wrapper ? 'blocked' : 'open';
});
console.log(`2. Volgende-knop state: ${blocked}`);

// 3. Vul verplichte velden in en kijk of de knop actief wordt
console.log('→ vul missende adresvelden in');
await page.fill('input[aria-label="Postcode"], input[name*="postcode1"]', '6211AB').catch(() => {});

// Gebruik een label-based fill voor de overige velden
const fillByLabel = async (label, value) => {
    const input = page.getByLabel(label, { exact: false }).first();
    await input.fill(value).catch(() => {});
};
await fillByLabel('Postcode', '6211AB');
await fillByLabel('Huisnummer', '1');
await fillByLabel('Straatnaam', 'Teststraat');
await fillByLabel('Plaatsnaam', 'Maastricht');
await page.waitForTimeout(500);

const blockedAfter = await page.evaluate(() => {
    const btn = Array.from(document.querySelectorAll('button')).find(b => b.textContent?.trim() === 'Volgende');
    if (!btn) return 'no-next-button';
    const wrapper = btn.closest('.fi-next-blocked');
    return wrapper ? 'blocked' : 'open';
});
console.log(`3. Volgende-knop na invullen: ${blockedAfter}`);

// 4. Sidebar stap 6 nog steeds disabled?
const step6Disabled = await page.locator('.fi-vertical-wizard-step-btn').nth(5).isDisabled();
console.log(`4. Sidebar stap 6 disabled: ${step6Disabled ? 'ja (goed)' : 'NEE (bug)'}`);

await page.screenshot({ path: '/tmp/form-after-fill.png', fullPage: true });
await browser.close();
