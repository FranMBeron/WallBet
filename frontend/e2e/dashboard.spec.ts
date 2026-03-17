import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth';

const BASE = 'http://localhost:3001';

test.describe('Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'demo@wallbet.com', 'password123');
  });

  test('loads-authenticated', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    await expect(page.getByRole('heading', { name: /my leagues/i })).toBeVisible({ timeout: 15_000 });
  });

  test('shows-new-league-button', async ({ page }) => {
    await page.goto(`${BASE}/dashboard`);
    // Accept either a link or a button labelled "New League"
    const newLeague = page
      .getByRole('link', { name: /new league/i })
      .or(page.getByRole('button', { name: /new league/i }))
      .first();
    await expect(newLeague).toBeVisible({ timeout: 15_000 });
  });
});
