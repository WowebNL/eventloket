import { defineConfig, devices } from '@playwright/test';
import { fileURLToPath } from 'url';
import path from 'path';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ORGANISER_STORAGE = path.join(__dirname, 'test-results/.auth/organiser.json');

/**
 * Playwright-config voor de formulier-walkthrough-tests.
 *
 * Deze tests dienen niet als bewijs voor OF-equivalentie (dat doen de
 * Pest-equivalentietests + de json-logic-js-verificatie), maar laten wel
 * visueel zien dat het formulier end-to-end werkt: inloggen, door de
 * wizard klikken, validatie op lege velden, submit op het einde.
 *
 * Login-sessie: het `setup`-project (auth.setup.mjs) logt één keer in en
 * slaat de sessie op in `ORGANISER_STORAGE`. Alle specs hergebruiken die
 * via `storageState`, zodat ze niet elk opnieuw inloggen — dat tripte de
 * login-rate-limiter zodra je de hele suite achter elkaar draaide.
 *
 * Run: `npx playwright test`
 * Report: `npx playwright show-report` → opent HTML-rapport met
 *          screenshots en video's per stap.
 */
export default defineConfig({
    testDir: './tests/Playwright',
    testMatch: '**/*.spec.mjs',
    fullyParallel: false,
    forbidOnly: !! process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: 1,
    outputDir: 'test-results/playwright-artifacts',
    reporter: [
        ['html', { outputFolder: 'test-results/playwright-report', open: 'never' }],
        ['list'],
    ],
    use: {
        baseURL: process.env.EF_BASE_URL || 'http://localhost',
        trace: 'on',
        screenshot: 'on',
        video: 'retain-on-failure',
        // Slow-mo bij zichtbare browser zodat je het kunt volgen. In headless
        // CI-runs blijft dit 0 (snelheid = maximaal).
        launchOptions: {
            slowMo: process.env.EF_SLOW_MO ? parseInt(process.env.EF_SLOW_MO, 10) : 0,
        },
    },
    projects: [
        {
            name: 'setup',
            testMatch: /auth\.setup\.mjs/,
        },
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'], storageState: ORGANISER_STORAGE },
            dependencies: ['setup'],
        },
    ],
});
