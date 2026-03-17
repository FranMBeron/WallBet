import { NextRequest, NextResponse } from 'next/server';

// Matches all routes inside the (main) route group
// i.e. /dashboard, /leagues/*, /join/*
const PROTECTED_PATHS = ['/dashboard', '/leagues', '/join'];

export function middleware(request: NextRequest): NextResponse {
  const { pathname } = request.nextUrl;

  // Redirect bare root to /dashboard
  if (pathname === '/') {
    return NextResponse.redirect(new URL('/dashboard', request.url));
  }

  // Check if this path is protected
  const isProtected = PROTECTED_PATHS.some((p) => pathname.startsWith(p));

  if (isProtected) {
    // Read token from cookie (set by the client after login) or
    // fall back to checking the Authorization header.
    // Since we use localStorage on the client, we cannot read it in middleware
    // (middleware runs on the edge, before React hydrates).
    // We use a lightweight cookie mirror: after setToken(), the client also
    // sets document.cookie = "wallbet_auth=1; path=/" (httpOnly NOT set so JS can write it).
    const authCookie = request.cookies.get('wallbet_auth');

    if (!authCookie?.value) {
      const loginUrl = new URL('/login', request.url);
      loginUrl.searchParams.set('from', pathname);
      return NextResponse.redirect(loginUrl);
    }
  }

  return NextResponse.next();
}

export const config = {
  matcher: [
    /*
     * Match all request paths EXCEPT:
     * - _next/static (static files)
     * - _next/image (image optimization)
     * - favicon.ico
     * - /login, /register, /connect-wallbit (public auth pages)
     */
    '/((?!_next/static|_next/image|favicon.ico|login|register|connect-wallbit).*)',
  ],
};
