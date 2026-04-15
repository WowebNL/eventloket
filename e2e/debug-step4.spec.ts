import { test, expect } from '@playwright/test';
import { loginAsOrganiser } from './helpers/login';
import { fillContactgegevens, fillHetEvenement, fillLocatie } from './helpers/form-filler';

test('debug step 4 tijden', async ({ page, context }) => {
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
  await fillLocatie(page);
  
  await page.waitForTimeout(2000);
  
  const elements = await page.evaluate(() => {
    const els = document.querySelectorAll('#openforms-root input, #openforms-root select, #openforms-root textarea');
    return Array.from(els).filter(el => (el as HTMLElement).offsetParent !== null).map(el => ({
      tag: el.tagName,
      type: (el as HTMLInputElement).type || '',
      name: el.getAttribute('name') || '',
      value: el.getAttribute('value') || '',
      placeholder: el.getAttribute('placeholder') || '',
    }));
  });

  console.log('=== Step 4 Tijden fields ===');
  elements.filter(e => e.name).forEach(e => {
    console.log(`${e.tag} name="${e.name}" type="${e.type}" value="${e.value}" placeholder="${e.placeholder}"`);
  });
  
  await page.screenshot({ path: 'test-results/step4-tijden.png', fullPage: true });
});
