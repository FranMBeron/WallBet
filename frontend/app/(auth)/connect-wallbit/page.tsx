'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { apiMutate } from '@/lib/api';
import type { ApiValidationError } from '@/types/api';

export default function ConnectWallbitPage() {
  const router = useRouter();
  const [apiKey, setApiKey] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setLoading(true);

    try {
      await apiMutate('/wallbit/connect', 'POST', { api_key: apiKey });
      router.push('/dashboard');
    } catch (err: unknown) {
      const apiErr = err as ApiValidationError;
      const firstFieldError = apiErr?.errors
        ? Object.values(apiErr.errors).flat()[0]
        : null;
      setError(firstFieldError ?? apiErr?.message ?? 'Failed to connect Wallbit key.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="rounded-xl border border-[#222222] bg-[#111111] p-8">
      <div className="mb-6">
        <h2 className="text-2xl font-bold text-white">Connect Wallbit</h2>
        <p className="mt-2 text-sm text-gray-400">
          Link your Wallbit API key to participate in leagues. Your key is encrypted at rest.
        </p>
      </div>

      <form onSubmit={handleSubmit} className="space-y-4">
        <div>
          <label htmlFor="api_key" className="block text-sm font-medium text-gray-300 mb-1">
            Wallbit API Key
          </label>
          <input
            id="api_key"
            type="text"
            required
            value={apiKey}
            onChange={(e) => setApiKey(e.target.value)}
            className="w-full rounded-md border border-[#333333] bg-black px-3 py-2 text-sm text-white placeholder-gray-500 font-mono focus:border-[#1B6FEB] focus:outline-none focus:ring-1 focus:ring-[#1B6FEB]"
            placeholder="wb_live_••••••••••••••••"
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
          {loading ? 'Connecting…' : 'Connect Wallbit'}
        </button>
      </form>

      <p className="mt-4 text-center">
        <Link
          href="/dashboard"
          className="text-sm text-gray-400 hover:text-white transition-colors"
        >
          Skip for now →
        </Link>
      </p>
    </div>
  );
}
