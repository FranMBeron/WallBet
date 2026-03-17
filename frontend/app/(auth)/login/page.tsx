'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { apiMutate } from '@/lib/api';
import { setToken, setDemoMode } from '@/lib/auth';
import type { LoginResponse, ApiError } from '@/types/api';

export default function LoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [demoLoading, setDemoLoading] = useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setLoading(true);

    try {
      const res = await apiMutate<LoginResponse>('/auth/login', 'POST', {
        email,
        password,
      });
      setToken(res.token);
      // Set a lightweight presence cookie so middleware can detect auth state
      document.cookie = 'wallbet_auth=1; path=/; max-age=86400';
      router.push('/dashboard');
    } catch (err: unknown) {
      const apiErr = err as ApiError;
      setError(apiErr?.message ?? 'Invalid credentials. Please try again.');
    } finally {
      setLoading(false);
    }
  }

  async function handleDemoLogin() {
    setDemoLoading(true);
    try {
      const data = await apiMutate<{ token: string; user: { id: string; name: string; email: string; display_name: string; avatar_url: string | null } }>(
        '/auth/demo-login',
        'POST',
        undefined,
      );
      setToken(data.token);
      setDemoMode();
      document.cookie = 'wallbet_auth=1; path=/; max-age=86400';
      sessionStorage.setItem('wallbet_demo_tour', '1');
      router.push('/dashboard');
    } catch {
      setError('Demo login no disponible en este momento.');
    } finally {
      setDemoLoading(false);
    }
  }

  return (
    <div className="rounded-xl border border-[#222222] bg-[#111111] p-8">
      <h2 className="mb-6 text-2xl font-bold text-white">Sign in</h2>

      <form onSubmit={handleSubmit} className="space-y-4">
        <div>
          <label htmlFor="email" className="block text-sm font-medium text-gray-300 mb-1">
            Email
          </label>
          <input
            id="email"
            type="email"
            autoComplete="email"
            required
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            className="w-full rounded-md border border-[#333333] bg-[#000000] px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-[#1B6FEB] focus:outline-none focus:ring-1 focus:ring-[#1B6FEB]"
            placeholder="you@example.com"
          />
        </div>

        <div>
          <label htmlFor="password" className="block text-sm font-medium text-gray-300 mb-1">
            Password
          </label>
          <input
            id="password"
            type="password"
            autoComplete="current-password"
            required
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            className="w-full rounded-md border border-[#333333] bg-[#000000] px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-[#1B6FEB] focus:outline-none focus:ring-1 focus:ring-[#1B6FEB]"
            placeholder="••••••••"
          />
        </div>

        {error && (
          <p className="rounded-md bg-red-950/40 border border-red-800/40 px-3 py-2 text-sm text-red-400">
            {error}
          </p>
        )}

        <button
          type="submit"
          disabled={loading}
          className="w-full rounded-md bg-[#1B6FEB] px-4 py-2 text-sm font-semibold text-white hover:bg-[#1559c9] disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          {loading ? 'Signing in…' : 'Sign in'}
        </button>
      </form>

      {/* Divider */}
      <div className="relative my-5">
        <div className="absolute inset-0 flex items-center">
          <div className="w-full border-t border-white/10" />
        </div>
        <div className="relative flex justify-center text-xs text-gray-500">
          <span className="bg-[#111111] px-2">o</span>
        </div>
      </div>

      {/* Demo access */}
      <button
        data-tour="demo-button"
        onClick={handleDemoLogin}
        disabled={demoLoading}
        className="w-full rounded-lg border border-[#1B6FEB]/40 bg-[#1B6FEB]/10 py-2.5 text-sm font-medium text-[#1B6FEB] transition-colors hover:bg-[#1B6FEB]/20 disabled:opacity-50"
      >
        {demoLoading ? 'Entrando…' : '▶ Probar Demo'}
      </button>

      <p className="mt-6 text-center text-sm text-gray-400">
        No account?{' '}
        <Link href="/register" className="text-[#1B6FEB] hover:underline">
          Create one
        </Link>
      </p>
    </div>
  );
}
