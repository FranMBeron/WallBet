// Token helpers — all localStorage access is isolated here.
// Never import localStorage directly outside this module.

const TOKEN_KEY = 'wallbet_token';

export function getToken(): string | null {
  if (typeof window === 'undefined') return null;
  return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token: string): void {
  if (typeof window === 'undefined') return;
  localStorage.setItem(TOKEN_KEY, token);
}

export function clearToken(): void {
  if (typeof window === 'undefined') return;
  localStorage.removeItem(TOKEN_KEY);
  // Also clear the presence cookie that middleware reads.
  // Setting max-age=0 (or expires in the past) immediately expires it.
  document.cookie = 'wallbet_auth=; path=/; max-age=0';
  clearDemoMode();
}

export function isAuthenticated(): boolean {
  return !!getToken();
}

export function setDemoMode(): void {
  if (typeof window === 'undefined') return;
  localStorage.setItem('is_demo', 'true');
}

export function isDemoMode(): boolean {
  if (typeof window === 'undefined') return false;
  return localStorage.getItem('is_demo') === 'true';
}

export function clearDemoMode(): void {
  if (typeof window === 'undefined') return;
  localStorage.removeItem('is_demo');
  localStorage.removeItem('tour_dismissed');
}
