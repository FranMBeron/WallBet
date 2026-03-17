import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth';

const BASE = 'http://localhost:3001';

test.describe('Analytics', () => {
  test('dashboard-renders', async ({ page }) => {
    const jsErrors: string[] = [];
    page.on('pageerror', (err) => jsErrors.push(err.message));

    await loginAs(page, 'demo@wallbet.com', 'password123');
    await page.goto(`${BASE}/dashboard`);
    await page.waitForLoadState('networkidle');

    // No unhandled JS errors
    expect(jsErrors).toHaveLength(0);

    // Page title
    const title = await page.title();
    expect(title).toMatch(/WallBet/i);
  });

  test('navigation-works', async ({ page }) => {
    await loginAs(page, 'demo@wallbet.com', 'password123');
    await page.goto(`${BASE}/dashboard`);

    // Click the "Leagues" nav link in the sidebar
    const leaguesNav = page
      .getByRole('link', { name: /^leagues$/i })
      .or(page.getByRole('navigation').getByText(/leagues/i))
      .first();
    await leaguesNav.click();

    await page.waitForURL(/\/leagues/, { timeout: 10_000 });
    expect(page.url()).toContain('/leagues');
  });
});
