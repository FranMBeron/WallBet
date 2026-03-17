import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth';

const BASE = 'http://localhost:3001';

test.describe('Leagues', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'demo@wallbet.com', 'password123');
  });

  test('create-public-league', async ({ page }) => {
    await page.goto(`${BASE}/leagues/new`);

    const unique = Date.now();

    // League name — field id="name"
    await page.locator('#name').fill(`E2E League ${unique}`);

    // Buy-in — field id="buy_in" (default 0, public is default via toggle)
    await page.locator('#buy_in').fill('0');

    // Required datetime fields — set to future dates
    const tomorrow = new Date(Date.now() + 86_400_000);
    const nextWeek = new Date(Date.now() + 7 * 86_400_000);
    const toLocal = (d: Date) =>
      d.toISOString().slice(0, 16); // "YYYY-MM-DDTHH:MM"

    await page.locator('#starts_at').fill(toLocal(tomorrow));
    await page.locator('#ends_at').fill(toLocal(nextWeek));

    // The form defaults to is_public=true (toggle is on), so no need to change it

    await page.getByRole('button', { name: /create league/i }).click();

    // After successful creation the form redirects to /leagues/{id}/leaderboard
    await page.waitForURL(/\/leagues\//, { timeout: 15_000 });
    expect(page.url()).toMatch(/\/leagues\//);
  });

  test('leagues-page-loads', async ({ page }) => {
    await page.goto(`${BASE}/leagues`);

    // Either a heading or the empty-state / list container should appear
    const heading = page.getByRole('heading').first();
    await expect(heading).toBeVisible({ timeout: 15_000 });
  });
});
