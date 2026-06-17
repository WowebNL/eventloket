import { test as setup, expect } from '@playwright/test';
import { fileURLToPath } from 'url';
import path from 'path';

/**
 * Eénmalige login die de organiser-sessie opslaat in een storageState-
 * bestand. Alle specs hergebruiken die sessie (zie playwright.config.mjs
 * `dependencies: ['setup']` + `use.storageState`), zodat ze niet elk
 * opnieuw via het login-formulier hoeven — dat tripte de login-rate-
 * limiter zodra je de hele suite achter elkaar draaide.
 */
const __dirname = path.dirname(fileURLToPath(import.meta.url));
export const ORGANISER_STORAGE = path.join(__dirname, '../../test-results/.auth/organiser.json');

setup('authenticate organiser', async ({ page }) => {
    await page.goto('/organiser/login');
    await page.waitForLoadState('networkidle', { timeout: 15_000 });

    await page.locator('input[wire\\:model="data.email"]').fill('noah.degraaf@example.net');
    await page.locator('input[wire\\:model="data.password"]').fill('password');
    await page.getByRole('button', { name: /inloggen/i }).click();

    await page.waitForURL(/\/organiser\/[0-9a-f]{8}/i, { timeout: 15_000 });
    await page.context().storageState({ path: ORGANISER_STORAGE });
});
