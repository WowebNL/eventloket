// Quick check: na onze fix moeten de vervolgvelden op stap 2 pas
// verschijnen wanneer de evenementnaam ingevuld is.

import { chromium } from 'playwright';

const BASE = 'http://localhost';

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1400, height: 900 } });
const page = await context.newPage();

await page.goto(`${BASE}/organiser/login`);
await page.locator('input[wire\\:model="data.email"]').fill('noah.degraaf@example.net');
await page.locator('input[wire\\:model="data.password"]').fill('password');
await page.getByRole('button', { name: /inloggen/i }).click();
await page.waitForLoadState('networkidle');

const tenant = new URL(page.url()).pathname.split('/')[2];
// Ga direct naar stap 2
await page.goto(`${BASE}/organiser/${tenant}/aanvraag?step=form.c3c17c65-0cf1-4a79-a348-75eab01f46ec`);
await page.waitForLoadState('networkidle');
await page.waitForTimeout(1000);

// Controleer: lege naam → zijn de vervolgvelden verborgen?
const beschrijvingZichtbaarLeeg = await page.locator('textarea[wire\\:model\\.defer="data.geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning"]').first().isVisible().catch(() => false);
const soortZichtbaarLeeg = await page.locator('select[wire\\:model\\.defer="data.soortEvenement"], [wire\\:model="data.soortEvenement"]').first().isVisible().catch(() => false);

console.log('Bij LEGE evenementnaam:');
console.log('  omschrijving-veld zichtbaar: ' + (beschrijvingZichtbaarLeeg ? 'JA (bug)' : 'nee (goed)'));
console.log('  soort-veld zichtbaar:        ' + (soortZichtbaarLeeg ? 'JA (bug)' : 'nee (goed)'));

await page.screenshot({ path: '/tmp/step2-leeg.png', fullPage: true });

// Vul de naam in
await page.locator('input[wire\\:model\\.live="data.watIsDeNaamVanHetEvenementVergunning"], input[wire\\:model="data.watIsDeNaamVanHetEvenementVergunning"]').first().fill('Zomerfestival 2026');
await page.waitForTimeout(1500);

const beschrijvingZichtbaarNaam = await page.locator('textarea[wire\\:model\\.defer="data.geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning"]').first().isVisible().catch(() => false);

console.log('\nBij INGEVULDE naam "Zomerfestival 2026":');
console.log('  omschrijving-veld zichtbaar: ' + (beschrijvingZichtbaarNaam ? 'ja (goed)' : 'NEE (bug)'));

await page.screenshot({ path: '/tmp/step2-gevuld.png', fullPage: true });
await browser.close();
