import { Page } from '@playwright/test';

/**
 * Programmatic login helper.
 *
 * Sanctum stateful API flow (called from the frontend origin):
 * 1. GET /sanctum/csrf-cookie  → sets XSRF-TOKEN cookie on the backend domain
 * 2. POST /api/auth/login with X-XSRF-TOKEN header → returns { token }
 * 3. Store token in localStorage + set presence cookie on frontend origin
 *
 * All fetch calls are made from inside the browser (page.evaluate) so that
 * credentials (cookies) are attached correctly by the browser.
 *
 * PHP 8.5 emits deprecated-constant HTML warnings before the JSON body, so
 * we strip everything before the first '{' when parsing login response.
 */
export async function loginAs(page: Page, email: string, password: string): Promise<void> {
  // Navigate to the frontend first so the page context is initialised.
  await page.goto('http://localhost:3001', { waitUntil: 'domcontentloaded' });

  const token: string = await page.evaluate(
    async ({ email, password }: { email: string; password: string }) => {
      // Step 1: get CSRF cookie
      await fetch('http://localhost:8000/sanctum/csrf-cookie', {
        method: 'GET',
        credentials: 'include',
      });

      // Step 2: read XSRF-TOKEN from cookies and URL-decode it
      const xsrfRaw = document.cookie
        .split(';')
        .map((c) => c.trim())
        .find((c) => c.startsWith('XSRF-TOKEN='));
      const xsrfToken = xsrfRaw ? decodeURIComponent(xsrfRaw.split('=').slice(1).join('=')) : '';

      // Step 3: login
      const res = await fetch('http://localhost:8000/api/auth/login', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-XSRF-TOKEN': xsrfToken,
        },
        body: JSON.stringify({ email, password }),
      });

      const text = await res.text();

      // Strip PHP deprecated-warning HTML that may prefix the JSON body
      const jsonStart = text.indexOf('{');
      if (jsonStart === -1) {
        throw new Error(`No JSON in login response (status ${res.status}): ${text.slice(0, 300)}`);
      }

      const body = JSON.parse(text.slice(jsonStart));
      const t: string = body?.token ?? body?.data?.token ?? body?.access_token ?? '';
      if (!t) throw new Error(`No token in login response: ${JSON.stringify(body)}`);
      return t;
    },
    { email, password },
  );

  // Store the token in localStorage on the frontend origin
  await page.evaluate((t: string) => {
    localStorage.setItem('wallbet_token', t);
  }, token);

  // Set the presence cookie on the frontend origin
  await page.context().addCookies([
    {
      name: 'wallbet_auth',
      value: '1',
      domain: 'localhost',
      path: '/',
      httpOnly: false,
      secure: false,
      sameSite: 'Lax',
    },
  ]);
}
