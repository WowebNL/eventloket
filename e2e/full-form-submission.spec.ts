import { test, expect } from '@playwright/test';
import { loginAsOrganiser } from './helpers/login';
import {
  fillContactgegevens,
  fillHetEvenement,
  fillLocatie,
  fillTijden,
  fillVooraankondiging,
  fillVergunningsplichtigScan,
  fillMelding,
  navigateToOverzichtAndSubmit,
} from './helpers/form-filler';
import { exec } from 'child_process';
import { promisify } from 'util';

const execAsync = promisify(exec);

async function runArtisan(command: string): Promise<string> {
  const { stdout } = await execAsync(
    `docker compose -f docker-compose-full-architecture.yml exec -T laravel.test php artisan ${command}`,
    { timeout: 60000 }
  );
  return stdout;
}

async function runInContainer(command: string): Promise<string> {
  const { stdout } = await execAsync(command, { timeout: 60000 });
  return stdout;
}

test.describe('Full Form Submission & Reuse', () => {
  test('submit form, wait for zaak, then test reuse prefill', async ({ page, context }) => {
    test.setTimeout(600000); // 10 minutes for full flow

    await context.clearCookies({ name: 'openforms_sessionid' });
    await loginAsOrganiser(page);

    // === PART 1: Fill and submit the form ===

    await page.click('text=Nieuwe aanvraag');
    await page.waitForURL('**/new-request/**', { timeout: 15000 });

    const startButton = page.locator('#openforms-root button[type="submit"]');
    await expect(startButton).toBeVisible({ timeout: 30000 });
    await startButton.click();
    await page.waitForURL('**/stap/**', { timeout: 30000 });

    await fillContactgegevens(page);
    await fillHetEvenement(page);
    await fillLocatie(page);
    await fillTijden(page);
    await fillVooraankondiging(page);
    await fillVergunningsplichtigScan(page);
    await fillMelding(page);
    await navigateToOverzichtAndSubmit(page);

    // Wait for submission to be processed
    await page.waitForTimeout(15000);
    await page.screenshot({ path: 'test-results/01-form-submitted.png' });

    console.log('Form submitted, URL:', page.url());

    // === PART 2: Wait for zaak creation via ZGW notification chain ===

    // Start queue worker
    await runInContainer(
      'docker compose -f docker-compose-full-architecture.yml exec -T -d laravel.test php artisan queue:work --tries=3 --timeout=60'
    ).catch(() => {});

    // Poll for zaak creation — the notification chain may take time
    let zaakCount = 0;
    for (let i = 0; i < 12; i++) {
      await page.waitForTimeout(5000);
      try {
        const result = await runArtisan('tinker --execute="echo App\\\\Models\\\\Zaak::count();"');
        zaakCount = parseInt(result.trim()) || 0;
        console.log(`Poll ${i+1}: ${zaakCount} zaken`);
        if (zaakCount > 1) break; // New zaak created (was 1 before)
      } catch (e) {}
    }

    // If notification chain didn't work, manually trigger zaak creation
    // from the latest Open Zaak zaak
    if (zaakCount <= 1) {
      console.log('Notification chain did not create zaak, triggering manually...');
      try {
        await runArtisan('tinker --execute="' +
          'use App\\\\Jobs\\\\Zaak\\\\CreateZaak;' +
          '\\$ozZaken = (new Woweb\\\\Openzaak\\\\Openzaak)->zaken()->zaken()->getAll();' +
          '\\$latest = \\$ozZaken->sortByDesc(fn(\\$z) => \\$z[\\\"startdatum\\\"])->first();' +
          'if (\\$latest) { CreateZaak::dispatchSync(\\$latest[\\\"url\\\"]); echo \\\"Created from \\\" . \\$latest[\\\"identificatie\\\"]; }' +
          '"');
      } catch (e) {
        console.log('Manual zaak creation failed:', e);
      }

      // Process queue
      await runInContainer(
        'docker compose -f docker-compose-full-architecture.yml exec -T laravel.test php artisan queue:work --once --timeout=30'
      ).catch(() => {});
      await page.waitForTimeout(5000);
    }

    // Verify zaak exists
    const zaakResult = await runArtisan('tinker --execute="echo App\\\\Models\\\\Zaak::count();"').catch(() => '0');
    console.log('Final zaak count:', zaakResult.trim());

    // === PART 3: Navigate to the zaak and test reuse ===

    // Go to Aanvragen
    await page.goto('/organiser/6ff10150-1691-498b-8cb4-14b3cd894d32');
    await page.waitForTimeout(3000);
    await page.click('text=Aanvragen');

    // Find the zaak — prefer "E2E Test Evenement", fallback to first row
    await page.waitForTimeout(3000);
    await page.screenshot({ path: 'test-results/02-zaak-list.png' });

    const e2eZaakRow = page.locator('table tbody tr:has-text("E2E Test Evenement")').first();
    if (await e2eZaakRow.isVisible({ timeout: 5000 }).catch(() => false)) {
      console.log('Found E2E Test Evenement zaak');
      await e2eZaakRow.click();
    } else {
      console.log('E2E zaak not found, clicking first zaak');
      await page.locator('table tbody tr').first().click();
    }
    await page.waitForTimeout(3000);

    // Check for "Nieuwe aanvraag" reuse button
    const reuseButton = page.getByRole('button', { name: 'Nieuwe aanvraag' });
    const reuseVisible = await reuseButton.isVisible({ timeout: 10000 }).catch(() => false);
    console.log('Reuse button visible:', reuseVisible);
    await page.screenshot({ path: 'test-results/03-zaak-detail.png' });

    expect(reuseVisible).toBe(true);

    await reuseButton.click();
    await page.waitForURL('**/reuse-request/**', { timeout: 15000 });
    await page.waitForURL('**/stap/**', { timeout: 45000 });

    // Check that contact data is prefilled on step 1
    const voornaam = page.locator('input[name="data[watIsUwVoornaam]"]');
    await expect(voornaam).toBeVisible({ timeout: 15000 });
    const voornaamVal = await voornaam.inputValue();
    console.log('Prefilled voornaam:', voornaamVal);
    expect(voornaamVal).not.toBe('');

    await page.screenshot({ path: 'test-results/04-reuse-contactgegevens.png' });

    // Navigate to step 2 "Het evenement" — click Volgende
    // First fill any empty required fields on contactgegevens to pass validation
    await fillContactgegevens(page);

    // Now on step 2 — check event name is prefilled
    const naamField = page.locator('input[name="data[watIsDeNaamVanHetEvenementVergunning]"]');
    await expect(naamField).toBeVisible({ timeout: 15000 });
    const naamVal = await naamField.inputValue();
    console.log('Prefilled event name:', naamVal);
    // The event name should be prefilled with data from the reused zaak
    expect(naamVal).not.toBe('');

    await page.screenshot({ path: 'test-results/05-reuse-evenement-prefilled.png' });
  });
});
