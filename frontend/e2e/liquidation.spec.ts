import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth';

const BASE = 'http://localhost:3001';
const API  = 'http://localhost:8000';

/**
 * Helper: find the league ID for a league by name.
 * Fetches /api/leagues/my and returns the UUID whose name matches.
 */
async function findLeagueIdByName(page: import('@playwright/test').Page, name: string): Promise<string | null> {
  const id = await page.evaluate(
    async ({ apiUrl, leagueName }: { apiUrl: string; leagueName: string }) => {
      const token = localStorage.getItem('wallbet_token');
      const res = await fetch(`${apiUrl}/api/leagues/my`, {
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        credentials: 'include',
      });
      const text = await res.text();
      const jsonStart = text.indexOf('{');
      if (jsonStart === -1 && text.indexOf('[') === -1) return null;
      const body = JSON.parse(text.slice(Math.min(
        jsonStart === -1 ? Infinity : jsonStart,
        text.indexOf('[') === -1 ? Infinity : text.indexOf('['),
      )));
      const leagues = Array.isArray(body) ? body : body?.data ?? [];
      const match = leagues.find((l: { name: string }) => l.name === leagueName);
      return match?.id ?? null;
    },
    { apiUrl: API, leagueName: name },
  );
  return id;
}

/**
 * Helper: check if user has active positions in a league via API.
 */
async function hasPositions(page: import('@playwright/test').Page, leagueId: string): Promise<boolean> {
  return page.evaluate(
    async ({ apiUrl, leagueId }: { apiUrl: string; leagueId: string }) => {
      const token = localStorage.getItem('wallbet_token');
      const res = await fetch(`${apiUrl}/api/leagues/${leagueId}/portfolio`, {
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        credentials: 'include',
      });
      const text = await res.text();
      const jsonStart = text.indexOf('{');
      if (jsonStart === -1) return false;
      const body = JSON.parse(text.slice(jsonStart));
      const portfolio = body?.data ?? body;
      const positions = portfolio?.positions ?? [];
      return positions.some((p: { quantity: number }) => p.quantity > 0);
    },
    { apiUrl: API, leagueId },
  );
}

// ---------------------------------------------------------------------------
// Liquidation E2E Tests
// ---------------------------------------------------------------------------

test.describe('Liquidation in finished leagues', () => {

  test('liquidate-button-visible-in-finished-league-with-positions', async ({ page }) => {
    await loginAs(page, 'demo@wallbet.io', 'password');
    await page.waitForTimeout(500);

    const finishedId = await findLeagueIdByName(page, 'Campeonato WallBet');
    if (!finishedId) {
      test.skip();
      return;
    }

    // Check if user has positions — skip if not
    const userHasPositions = await hasPositions(page, finishedId);
    if (!userHasPositions) {
      test.skip();
      return;
    }

    // Navigate to portfolio page of finished league
    await page.goto(`${BASE}/leagues/${finishedId}/portfolio`);
    await page.waitForLoadState('networkidle');

    // The "Liquidar todas las posiciones" button should be visible
    const liquidateButton = page.getByRole('button', { name: /Liquidar todas las posiciones/i });
    await expect(liquidateButton).toBeVisible({ timeout: 15_000 });
  });

  test('liquidate-button-hidden-in-active-league', async ({ page }) => {
    await loginAs(page, 'demo@wallbet.io', 'password');
    await page.waitForTimeout(500);

    const activeId = await findLeagueIdByName(page, 'Copa de Primavera');
    if (!activeId) {
      test.skip();
      return;
    }

    // Navigate to portfolio page of active league
    await page.goto(`${BASE}/leagues/${activeId}/portfolio`);
    await page.waitForLoadState('networkidle');

    // Wait for portfolio content to load
    await page.waitForTimeout(3000);

    // The liquidate button should NOT be visible in active leagues
    const liquidateButton = page.getByRole('button', { name: /Liquidar todas las posiciones/i });
    await expect(liquidateButton).not.toBeVisible();
  });

  test('liquidate-confirmation-dialog-shows-warning', async ({ page }) => {
    await loginAs(page, 'demo@wallbet.io', 'password');
    await page.waitForTimeout(500);

    const finishedId = await findLeagueIdByName(page, 'Campeonato WallBet');
    if (!finishedId) {
      test.skip();
      return;
    }

    const userHasPositions = await hasPositions(page, finishedId);
    if (!userHasPositions) {
      test.skip();
      return;
    }

    await page.goto(`${BASE}/leagues/${finishedId}/portfolio`);
    await page.waitForLoadState('networkidle');

    // Click liquidate button
    const liquidateButton = page.getByRole('button', { name: /Liquidar todas las posiciones/i });
    await expect(liquidateButton).toBeVisible({ timeout: 15_000 });
    await liquidateButton.click();

    // Confirmation dialog should appear
    const dialog = page.locator('[role="dialog"]');
    await expect(dialog).toBeVisible({ timeout: 5_000 });

    // Dialog title
    await expect(dialog.getByText(/Liquidar todas las posiciones/i)).toBeVisible();

    // Price warning should be visible
    await expect(dialog.getByText(/precio de mercado actual/i)).toBeVisible();

    // Position count should be displayed
    await expect(dialog.getByText(/Se venderan/i)).toBeVisible();

    // Cancel and Confirm buttons should be visible
    await expect(dialog.getByRole('button', { name: /Cancelar/i })).toBeVisible();
    await expect(dialog.getByRole('button', { name: /Confirmar liquidacion/i })).toBeVisible();
  });

  test('liquidate-dialog-cancel-returns-to-idle', async ({ page }) => {
    await loginAs(page, 'demo@wallbet.io', 'password');
    await page.waitForTimeout(500);

    const finishedId = await findLeagueIdByName(page, 'Campeonato WallBet');
    if (!finishedId) {
      test.skip();
      return;
    }

    const userHasPositions = await hasPositions(page, finishedId);
    if (!userHasPositions) {
      test.skip();
      return;
    }

    await page.goto(`${BASE}/leagues/${finishedId}/portfolio`);
    await page.waitForLoadState('networkidle');

    // Click liquidate button
    const liquidateButton = page.getByRole('button', { name: /Liquidar todas las posiciones/i });
    await expect(liquidateButton).toBeVisible({ timeout: 15_000 });
    await liquidateButton.click();

    // Dialog appears
    const dialog = page.locator('[role="dialog"]');
    await expect(dialog).toBeVisible({ timeout: 5_000 });

    // Click Cancel
    await dialog.getByRole('button', { name: /Cancelar/i }).click();

    // Dialog should disappear
    await expect(dialog).not.toBeVisible({ timeout: 3_000 });

    // Original button should reappear
    await expect(liquidateButton).toBeVisible({ timeout: 3_000 });
  });

  test('liquidate-dialog-dismiss-with-escape-key', async ({ page }) => {
    await loginAs(page, 'demo@wallbet.io', 'password');
    await page.waitForTimeout(500);

    const finishedId = await findLeagueIdByName(page, 'Campeonato WallBet');
    if (!finishedId) {
      test.skip();
      return;
    }

    const userHasPositions = await hasPositions(page, finishedId);
    if (!userHasPositions) {
      test.skip();
      return;
    }

    await page.goto(`${BASE}/leagues/${finishedId}/portfolio`);
    await page.waitForLoadState('networkidle');

    // Click liquidate button
    const liquidateButton = page.getByRole('button', { name: /Liquidar todas las posiciones/i });
    await expect(liquidateButton).toBeVisible({ timeout: 15_000 });
    await liquidateButton.click();

    // Dialog appears
    const dialog = page.locator('[role="dialog"]');
    await expect(dialog).toBeVisible({ timeout: 5_000 });

    // Press Escape
    await page.keyboard.press('Escape');

    // Dialog should disappear
    await expect(dialog).not.toBeVisible({ timeout: 3_000 });

    // Original button should reappear
    await expect(liquidateButton).toBeVisible({ timeout: 3_000 });
  });

  test('liquidate-confirm-calls-api-and-shows-result', async ({ page }) => {
    await loginAs(page, 'demo@wallbet.io', 'password');
    await page.waitForTimeout(500);

    const finishedId = await findLeagueIdByName(page, 'Campeonato WallBet');
    if (!finishedId) {
      test.skip();
      return;
    }

    const userHasPositions = await hasPositions(page, finishedId);
    if (!userHasPositions) {
      test.skip();
      return;
    }

    await page.goto(`${BASE}/leagues/${finishedId}/portfolio`);
    await page.waitForLoadState('networkidle');

    // Click liquidate button
    const liquidateButton = page.getByRole('button', { name: /Liquidar todas las posiciones/i });
    await expect(liquidateButton).toBeVisible({ timeout: 15_000 });
    await liquidateButton.click();

    // Dialog appears
    const dialog = page.locator('[role="dialog"]');
    await expect(dialog).toBeVisible({ timeout: 5_000 });

    // Click Confirm
    await dialog.getByRole('button', { name: /Confirmar liquidacion/i }).click();

    // Dialog should disappear (we're now in loading or success state)
    await expect(dialog).not.toBeVisible({ timeout: 5_000 });

    // Should show either loading ("Liquidando...") or success/error result
    // Wait for loading to finish — look for success or error message
    const successMessage = page.getByText(/Liquidacion completada/i);
    const errorMessage = page.getByText(/fallo|Intenta de nuevo/i);
    const loadingMessage = page.getByText(/Liquidando/i);

    // Either loading appears first, then success/error
    // Wait up to 60s for the liquidation to complete (API may take time)
    await expect(successMessage.or(errorMessage)).toBeVisible({ timeout: 60_000 });
  });

  test('liquidate-api-returns-403-for-active-league', async ({ page }) => {
    await loginAs(page, 'demo@wallbet.io', 'password');
    await page.waitForTimeout(500);

    const activeId = await findLeagueIdByName(page, 'Copa de Primavera');
    if (!activeId) {
      test.skip();
      return;
    }

    // POST to liquidate endpoint for active league — should get 403
    const status = await page.evaluate(
      async ({ apiUrl, leagueId }: { apiUrl: string; leagueId: string }) => {
        const token = localStorage.getItem('wallbet_token');

        // Get CSRF cookie first
        await fetch(`${apiUrl}/sanctum/csrf-cookie`, {
          method: 'GET',
          credentials: 'include',
        });
        const xsrfRaw = document.cookie
          .split(';')
          .map((c) => c.trim())
          .find((c) => c.startsWith('XSRF-TOKEN='));
        const xsrfToken = xsrfRaw ? decodeURIComponent(xsrfRaw.split('=').slice(1).join('=')) : '';

        const res = await fetch(`${apiUrl}/api/leagues/${leagueId}/liquidate`, {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrfToken,
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
          },
        });

        return res.status;
      },
      { apiUrl: API, leagueId: activeId },
    );

    expect(status).toBe(403);
  });
});
