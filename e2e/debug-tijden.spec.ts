import { test, expect } from '@playwright/test';
import { loginAsOrganiser } from './helpers/login';
import { fillContactgegevens, fillHetEvenement, fillLocatie } from './helpers/form-filler';

test('debug tijden fields', async ({ page, context }) => {
  test.setTimeout(180000);
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

  const fields = await page.evaluate(() => {
    const els = document.querySelectorAll('#openforms-root input, #openforms-root select');
    return Array.from(els).filter(el => (el as HTMLElement).offsetParent !== null).map(el => ({
      tag: el.tagName,
      type: (el as HTMLInputElement).type || '',
      name: el.getAttribute('name') || '',
      value: el.getAttribute('value') || '',
      placeholder: el.getAttribute('placeholder') || '',
    }));
  });

  console.log('=== Tijden visible fields ===');
  fields.filter(e => e.name).forEach(e => {
    console.log(`${e.tag} name="${e.name}" type="${e.type}" value="${e.value}" placeholder="${e.placeholder}"`);
  });
});
