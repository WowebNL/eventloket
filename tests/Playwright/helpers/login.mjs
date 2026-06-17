/**
 * Gedeelde login-flow voor de walkthrough-tests.
 *
 * Normaal is de organiser al ingelogd via de opgeslagen sessie
 * (auth.setup.mjs + storageState in playwright.config.mjs): dan volstaat
 * navigeren naar `/organiser` en doorredirecten naar de tenant. Alleen als
 * die sessie ontbreekt/verlopen is, vallen we terug op het login-formulier.
 * Zo logt de suite niet bij élke test opnieuw in — wat de login-rate-
 * limiter tripte zodra je de hele suite achter elkaar draaide.
 */
export async function loginAlsOrganiser(page, user = 'noah.degraaf@example.net', pass = 'password') {
    await page.goto('/organiser');
    try {
        await page.waitForURL(/\/organiser\/[0-9a-f]{8}/i, { timeout: 8_000 });
        console.log(`  ✅ hergebruik sessie, landing URL: ${page.url()}`);

        return;
    } catch {
        // Geen geldige sessie → val terug op het login-formulier.
    }

    console.log(`  → form-login: ${user}`);
    await page.goto('/organiser/login');
    await page.waitForLoadState('networkidle', { timeout: 15_000 });
    await page.locator('input[wire\\:model="data.email"]').fill(user);
    await page.locator('input[wire\\:model="data.password"]').fill(pass);
    await page.getByRole('button', { name: /inloggen/i }).click();

    try {
        await page.waitForURL(/\/organiser\/[0-9a-f]{8}/i, { timeout: 15_000 });
    } catch (e) {
        console.log(`  ⚠ login bleef hangen op: ${page.url()}`);
        throw new Error(`Login niet gelukt — huidige URL: ${page.url()}. Check of user '${user}' bestaat én aan een organisatie gekoppeld is.`);
    }
    console.log(`  ✅ ingelogd, landing URL: ${page.url()}`);
}

/**
 * Navigeer naar het aanvraag-formulier van de huidige tenant.
 */
export async function openFormulier(page) {
    const tenant = new URL(page.url()).pathname.split('/')[2];
    if (! tenant || ! tenant.match(/^[0-9a-f-]+$/i)) {
        throw new Error(`Kan geen tenant-UUID afleiden uit URL '${page.url()}'. Verwacht bv. /organiser/<uuid>/...`);
    }
    const url = `/organiser/${tenant}/aanvraag`;
    console.log(`  → GET ${url}`);
    await page.goto(url, { timeout: 30_000 });
    // waitForLoadState timeout kort houden; als mount hangt willen we dat zien.
    await page.waitForLoadState('networkidle', { timeout: 30_000 }).catch(() => {
        console.log(`  ⚠ networkidle timeout — doorgaan met wat er tot nu toe gerendered is`);
    });
    console.log(`  ✅ formulier geladen: ${page.url()}`);
    return tenant;
}

async function baseUrl(page) {
    try { return new URL(page.url()).origin; } catch { return '(nog geen URL)'; }
}
