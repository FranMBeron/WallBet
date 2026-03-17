import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth';

const BASE = 'http://localhost:3001';

test.describe('Portfolio', () => {
  test('connect-wallbit-page', async ({ page }) => {
    await loginAs(page, 'demo@wallbet.com', 'password123');
    await page.goto(`${BASE}/connect-wallbit`);

    // Heading must be visible
    const heading = page.getByRole('heading').first();
    await expect(heading).toBeVisible({ timeout: 15_000 });

    // API key input (text / password input)
    const apiInput = page
      .getByLabel(/api key/i)
      .or(page.getByPlaceholder(/api key/i))
      .first();
    await expect(apiInput).toBeVisible({ timeout: 10_000 });
  });

  test('portfolio-requires-auth', async ({ page }) => {
    // Navigate to dashboard WITHOUT setting auth — should redirect to /login
    await page.goto(`${BASE}/dashboard`);
    await page.waitForURL(/\/login/, { timeout: 15_000 });
    expect(page.url()).toContain('/login');
  });
});
