import { test, expect } from '@playwright/test';
import { loginAsOrganiser } from './helpers/login';
import { fillContactgegevens, fillHetEvenement } from './helpers/form-filler';

test('debug step 3 editgrid row', async ({ page, context }) => {
  await context.clearCookies({ name: 'openforms_sessionid' });
  await loginAsOrganiser(page);
  await page.click('text=Nieuwe aanvraag');
  await page.waitForURL('**/new-request/**', { timeout: 15000 });
  const startButton = page.locator('#openforms-root button[type="submit"]');
  await expect(startButton).toBeVisible({ timeout: 30000 });
  await startButton.click();
  await page.waitForURL('**/stap/**', { timeout: 30000 });
  
  await fillContactgegevens(page);
  await fillHetEvenement(page);
  await page.waitForTimeout(2000);
  
  // Check "gebouw"
  await page.locator('input[value="gebouw"][name="data[waarVindtHetEvenementPlaats][]"]').check();
  await page.waitForTimeout(2000);
  
  // Click "Adres van gebouw toevoegen"
  await page.locator('button:has-text("Adres van gebouw toevoegen")').click();
  await page.waitForTimeout(2000);
  
  // Get all visible form elements
  const elements = await page.evaluate(() => {
    const els = document.querySelectorAll('#openforms-root input, #openforms-root select, #openforms-root textarea, #openforms-root button');
    return Array.from(els).filter(el => (el as HTMLElement).offsetParent !== null).map(el => ({
      tag: el.tagName,
      type: (el as HTMLInputElement).type || '',
      name: el.getAttribute('name') || '',
      text: el.textContent?.trim().substring(0, 60) || '',
      placeholder: el.getAttribute('placeholder') || '',
    }));
  });

  console.log('=== After adding gebouw row ===');
  elements.forEach(e => {
    if (e.name || (e.tag === 'BUTTON' && e.text)) {
      console.log(`${e.tag} name="${e.name}" type="${e.type}" text="${e.text}" placeholder="${e.placeholder}"`);
    }
  });
  
  await page.screenshot({ path: 'test-results/step3-editgrid-row.png', fullPage: true });
});
