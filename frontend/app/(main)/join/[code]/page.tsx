'use client';

import { useState, use } from 'react';
import { useRouter } from 'next/navigation';
import useSWR from 'swr';
import { apiFetcher, apiMutate } from '@/lib/api';
import { LeagueStatusBadge } from '@/components/leagues/LeagueStatusBadge';
import { SkeletonCard } from '@/components/ui/SkeletonCard';
import { ErrorState } from '@/components/ui/ErrorState';
import { formatCurrency, formatDate } from '@/lib/utils';
import type { League, ApiValidationError } from '@/types/api';

interface Props {
  params: Promise<{ code: string }>;
}

export default function JoinByCodePage({ params }: Props) {
  const { code } = use(params);
  const router = useRouter();

  const { data: league, isLoading, error, mutate } = useSWR<League>(
    `/leagues/invite/${code}`,
    apiFetcher,
  );

  const [password, setPassword] = useState('');
  const [joinError, setJoinError] = useState<string | null>(null);
  const [joining, setJoining] = useState(false);

  async function handleJoin(e: React.FormEvent) {
    e.preventDefault();
    if (!league) return;
    setJoinError(null);
    setJoining(true);

    try {
      await apiMutate(`/leagues/${league.id}/join`, 'POST', {
        password: !league.is_public ? password : undefined,
      });
      router.push(`/leagues/${league.id}`);
    } catch (err: unknown) {
      const apiErr = err as ApiValidationError;
      const firstErr = apiErr?.errors
        ? Object.values(apiErr.errors).flat()[0]
        : null;
      setJoinError(firstErr ?? apiErr?.message ?? 'Failed to join league.');
    } finally {
      setJoining(false);
    }
  }

  if (isLoading) return <div className="max-w-md mx-auto"><SkeletonCard /></div>;
  if (error || !league) {
    return (
      <div className="max-w-md mx-auto">
        <ErrorState
          message="Invalid or expired invite link."
          onRetry={() => mutate()}
        />
      </div>
    );
  }

  return (
    <div className="max-w-md mx-auto">
      <div className="rounded-xl border border-[#222222] bg-[#111111] p-6">
        <h1 className="text-xl font-bold text-white mb-4">Join League</h1>

        {/* League details */}
        <div className="rounded-lg border border-[#222222] bg-black p-4 mb-5 space-y-2">
          <div className="flex items-center justify-between">
            <span className="font-semibold text-white">{league.name}</span>
            <LeagueStatusBadge status={league.status} />
          </div>
          <div className="text-sm text-gray-400 space-y-1">
            <p>Buy-in: <span className="text-white">{league.buy_in > 0 ? formatCurrency(league.buy_in) : 'Free'}</span></p>
            <p>Starts: <span className="text-white">{formatDate(league.starts_at)}</span></p>
            <p>Ends: <span className="text-white">{formatDate(league.ends_at)}</span></p>
            <p>Participants: <span className="text-white">{league.member_count} / {league.max_participants}</span></p>
          </div>
        </div>

        <form onSubmit={handleJoin} className="space-y-4">
          {/* Password for private leagues */}
          {!league.is_public && (
            <div>
              <label htmlFor="password" className="block text-sm font-medium text-gray-300 mb-1">
                League password
              </label>
              <input
                id="password"
                type="password"
                required
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="w-full rounded-md border border-[#333333] bg-black px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-[#1B6FEB] focus:outline-none focus:ring-1 focus:ring-[#1B6FEB]"
                placeholder="Enter league password"
              />
            </div>
          )}

          {joinError && (
            <p className="rounded-md bg-red-950/40 border border-red-800/40 px-3 py-2 text-sm text-red-400">
              {joinError}
            </p>
          )}

          <button
            type="submit"
            disabled={joining}
            className="w-full rounded-md bg-[#1B6FEB] px-4 py-2 text-sm font-semibold text-white hover:bg-[#1559c9] disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            {joining ? 'Joining…' : 'Join League'}
          </button>
        </form>
      </div>
    </div>
  );
}
