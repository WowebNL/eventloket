/**
 * Gedeelde login-flow voor de walkthrough-tests. Filament's login-form
 * heeft naast het wachtwoord-inputveld ook twee knoppen met "Wachtwoord"
 * in de aria-label (tonen/verbergen), dus we targeten de inputs op hun
 * expliciete id die Filament genereert: `data.email` / `data.password`.
 */
export async function loginAlsOrganiser(page, user = 'noah.degraaf@example.net', pass = 'password') {
    await page.goto('/organiser/login');
    await page.locator('input[wire\\:model="data.email"]').fill(user);
    await page.locator('input[wire\\:model="data.password"]').fill(pass);
    await page.getByRole('button', { name: /inloggen/i }).click();
    await page.waitForLoadState('networkidle');
}

/**
 * Navigeer rechtstreeks naar het aanvraag-formulier van de huidige tenant.
 * Leidt de tenant-UUID af uit de URL na login — dat is robuuster dan een
 * vaste UUID die na een nieuwe seed niet meer klopt.
 */
export async function openFormulier(page) {
    const tenant = new URL(page.url()).pathname.split('/')[2];
    await page.goto(`/organiser/${tenant}/aanvraag`);
    await page.waitForLoadState('networkidle');
    return tenant;
}
