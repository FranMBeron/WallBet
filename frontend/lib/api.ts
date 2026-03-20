import { clearToken, getToken } from './auth';
import type { AssetInfo, ExecuteTradePayload, LiquidateResponse, TradeLog } from '@/types/api';

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000';

// ----------------------------------------------------------------
// CSRF helper for Laravel Sanctum stateful API.
// Fetches /sanctum/csrf-cookie (sets the XSRF-TOKEN cookie), then
// reads and URL-decodes that cookie value so it can be sent as the
// X-XSRF-TOKEN request header on subsequent state-changing calls.
// fetch does NOT forward cookies as headers automatically (unlike
// axios), so we must do it manually.
// ----------------------------------------------------------------

async function getCsrfToken(): Promise<string | null> {
  if (typeof document === 'undefined') return null;

  await fetch(`${API_URL}/sanctum/csrf-cookie`, {
    method: 'GET',
    credentials: 'include',
  });

  const match = document.cookie
    .split('; ')
    .find((row) => row.startsWith('XSRF-TOKEN='));

  if (!match) return null;

  // Laravel URL-encodes the token value; decode before sending.
  return decodeURIComponent(match.split('=')[1]);
}

// ----------------------------------------------------------------
// Central typed fetcher — used as SWR `fetcher` argument.
// All hooks pass just the path (e.g. "/leagues/my").
// ----------------------------------------------------------------

export async function apiFetcher<T>(path: string): Promise<T> {
  const token = getToken();

  const res = await fetch(`${API_URL}/api${path}`, {
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    credentials: 'include',
  });

  if (res.status === 401) {
    clearToken();
    if (typeof window !== 'undefined') {
      window.location.href = '/login';
    }
    throw new Error('Unauthorized');
  }

  if (!res.ok) {
    // Attempt to parse JSON error body; fall back to status text
    let errorBody: Record<string, unknown>;
    try {
      errorBody = await res.json() as Record<string, unknown>;
    } catch {
      errorBody = { message: res.statusText };
    }
    // Attach HTTP status so callers can distinguish error types (e.g. 404 vs 500)
    throw { ...errorBody, status: res.status };
  }

  const json = await res.json() as Record<string, unknown>;
  // Laravel API Resources wrap responses in {"data": ...} — unwrap automatically.
  return (json?.data !== undefined ? json.data : json) as T;
}

/**
 * Fetcher that returns the full JSON response without unwrapping "data".
 * Use for paginated endpoints where you need current_page, last_page, etc.
 */
export async function apiRawFetcher<T>(path: string): Promise<T> {
  const token = getToken();

  const res = await fetch(`${API_URL}/api${path}`, {
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    credentials: 'include',
  });

  if (res.status === 401) {
    clearToken();
    if (typeof window !== 'undefined') {
      window.location.href = '/login';
    }
    throw new Error('Unauthorized');
  }

  if (!res.ok) {
    let errorBody: Record<string, unknown>;
    try {
      errorBody = await res.json() as Record<string, unknown>;
    } catch {
      errorBody = { message: res.statusText };
    }
    throw { ...errorBody, status: res.status };
  }

  return await res.json() as T;
}

// ----------------------------------------------------------------
// Mutation helper for POST / PUT / PATCH / DELETE
// Returns the parsed JSON response body.
// Throws the parsed error body on non-2xx.
// ----------------------------------------------------------------

export async function apiMutate<T = unknown>(
  path: string,
  method: 'POST' | 'PUT' | 'PATCH' | 'DELETE' = 'POST',
  body?: unknown,
): Promise<T> {
  const token = getToken();
  const csrfToken = await getCsrfToken();

  const res = await fetch(`${API_URL}/api${path}`, {
    method,
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(csrfToken ? { 'X-XSRF-TOKEN': csrfToken } : {}),
    },
    credentials: 'include',
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });

  if (res.status === 401) {
    clearToken();
    if (typeof window !== 'undefined') {
      window.location.href = '/login';
    }
    throw new Error('Unauthorized');
  }

  // 204 No Content — nothing to parse
  if (res.status === 204) {
    return undefined as unknown as T;
  }

  let data: unknown;
  try {
    data = await res.json();
  } catch {
    data = { message: res.statusText };
  }

  if (!res.ok) {
    throw data;
  }

  // Laravel API Resources wrap responses in {"data": ...} — unwrap automatically.
  const json = data as Record<string, unknown>;
  return (json?.data !== undefined ? json.data : json) as T;
}

// ----------------------------------------------------------------
// Trading
// ----------------------------------------------------------------

export async function fetchAssetPreview(leagueId: string, symbol: string): Promise<AssetInfo> {
  return apiFetcher(`/leagues/${leagueId}/assets/${symbol}`);
}

export async function executeTrade(leagueId: string, payload: ExecuteTradePayload): Promise<TradeLog> {
  return apiMutate(`/leagues/${leagueId}/trades`, 'POST', payload);
}

// ----------------------------------------------------------------
// Liquidation
// ----------------------------------------------------------------

export async function liquidateAll(leagueId: string): Promise<LiquidateResponse> {
  return apiMutate<LiquidateResponse>(`/leagues/${leagueId}/liquidate`, 'POST');
}
