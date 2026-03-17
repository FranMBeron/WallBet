import { test, expect } from '@playwright/test';

const BASE = 'http://localhost:3001';

test.describe('Auth', () => {
  test('login-success', async ({ page }) => {
    await page.goto(`${BASE}/login`);

    await page.getByLabel(/email/i).fill('demo@wallbet.com');
    await page.getByLabel(/password/i).fill('password123');
    await page.getByRole('button', { name: /sign in/i }).click();

    await page.waitForURL(/\/(dashboard|connect-wallbit)/, { timeout: 15_000 });
    expect(page.url()).toMatch(/\/(dashboard|connect-wallbit)/);
  });

  test('register-flow', async ({ page }) => {
    const unique = Date.now();
    await page.goto(`${BASE}/register`);

    // Fields use explicit HTML ids: email, username, display_name, password, password_confirmation
    await page.locator('#email').fill(`testuser${unique}@example.com`);
    await page.locator('#username').fill(`testuser${unique}`);
    await page.locator('#name').fill(`Test User ${unique}`);
    await page.locator('#password').fill('password123');
    await page.locator('#password_confirmation').fill('password123');

    await page.getByRole('button', { name: /create account/i }).click();

    await page.waitForURL(/\/(dashboard|connect-wallbit|login)/, { timeout: 15_000 });
    expect(page.url()).toMatch(/\/(dashboard|connect-wallbit|login)/);
  });
});
