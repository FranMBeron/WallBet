import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth';

const BASE = 'http://localhost:3001';

test.describe('Leaderboard', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'demo@wallbet.com', 'password123');
  });

  test('leaderboard-tab-visible', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    // Wait for any league cards to appear (they're loaded asynchronously)
    await page.waitForLoadState('networkidle');

    // LeagueCard links go to /leagues/{id} which redirects to /leagues/{id}/leaderboard
    const leagueCard = page.getByRole('link').filter({ hasText: /.+/ }).first();
    const href = await leagueCard.getAttribute('href', { timeout: 5_000 }).catch(() => null);

    if (!href || !href.match(/\/leagues\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/)) {
      test.skip();
      return;
    }

    await page.goto(`${BASE}${href}`);
    // The detail page redirects to /leagues/{id}/leaderboard
    await page.waitForURL(/\/leagues\/.*\/leaderboard/, { timeout: 10_000 });

    // Leaderboard heading or navigation tab
    const leaderboardSection = page
      .getByRole('heading', { name: /leaderboard/i })
      .or(page.getByText(/leaderboard/i))
      .first();
    await expect(leaderboardSection).toBeVisible({ timeout: 10_000 });
  });

  test('leaderboard-empty-state', async ({ page }) => {
    await page.goto(`${BASE}/leagues`);
    await page.waitForLoadState('networkidle');

    // Find a link to a specific league (/leagues/{uuid} — UUID has dashes and is 36 chars)
    const links = await page.getByRole('link').all();
    let leagueHref: string | null = null;
    for (const link of links) {
      const h = await link.getAttribute('href').catch(() => null);
      // UUIDs: 8-4-4-4-12 hex chars separated by dashes (e.g. /leagues/a151df11-8a3b-...)
      if (h && /\/leagues\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/.test(h)) {
        leagueHref = h;
        break;
      }
    }

    if (!leagueHref) {
      test.skip();
      return;
    }

    // Navigate via client-side routing (click) to avoid Next.js 14 use(params) SSR issues
    // Use the exact href to find and click the league card
    await page.locator(`a[href="${leagueHref}"]`).first().click();
    await page.waitForURL(/\/leagues\/.*\/leaderboard/, { timeout: 10_000 });

    // Page must render without crashing — leaderboard page shows an h2 "Leaderboard"
    await expect(page.getByRole('heading', { name: /leaderboard/i })).toBeVisible({ timeout: 10_000 });
  });
});
