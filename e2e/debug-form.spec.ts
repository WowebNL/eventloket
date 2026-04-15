import { test, expect } from '@playwright/test';
import { loginAsOrganiser } from './helpers/login';

test('debug form fields', async ({ page, context }) => {
  await context.clearCookies({ name: 'openforms_sessionid' });
  await loginAsOrganiser(page);
  await page.click('text=Nieuwe aanvraag');
  await page.waitForURL('**/new-request/**', { timeout: 15000 });
  const startButton = page.locator('#openforms-root button[type="submit"]');
  await expect(startButton).toBeVisible({ timeout: 30000 });
  await startButton.click();
  await page.waitForURL('**/stap/**', { timeout: 30000 });
  
  // Wait for form to fully render
  await page.waitForTimeout(3000);
  
  // Get all input/select/textarea elements with their names
  const fields = await page.evaluate(() => {
    const inputs = document.querySelectorAll('#openforms-root input, #openforms-root select, #openforms-root textarea');
    return Array.from(inputs).map(el => ({
      tag: el.tagName,
      type: (el as HTMLInputElement).type,
      name: el.getAttribute('name'),
      id: el.id,
      value: (el as HTMLInputElement).value,
      visible: el.offsetParent !== null,
    })).filter(f => f.name && f.visible);
  });
  
  console.log('=== Visible form fields ===');
  fields.forEach(f => console.log(`${f.name} (${f.tag}/${f.type}) value="${f.value}"`));
});
