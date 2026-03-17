'use client';

import { use, useState, useEffect } from 'react';
import { useLeaderboard } from '@/lib/hooks/useLeaderboard';
import { useAuth } from '@/lib/hooks/useAuth';
import { LeagueHeader } from '@/components/layout/LeagueHeader';
import { TabBar } from '@/components/layout/TabBar';
import { LeaderboardTable } from '@/components/leaderboard/LeaderboardTable';
import { SkeletonCard, SkeletonRow } from '@/components/ui/SkeletonCard';
import { ErrorState } from '@/components/ui/ErrorState';
import { EmptyState } from '@/components/ui/EmptyState';

interface Props {
  params: Promise<{ id: string }>;
}

export default function LeaderboardPage({ params }: Props) {
  const { id } = use(params);
  const { user } = useAuth();
  const [sortBy, setSortBy] = useState<string | undefined>(undefined);
  const { data, isLoading, error, mutate } = useLeaderboard(id, sortBy);
  const [lastUpdated, setLastUpdated] = useState<Date | null>(null);
  const [minsAgo, setMinsAgo] = useState(0);

  // Track when data last refreshed
  useEffect(() => {
    if (data) setLastUpdated(new Date());
  }, [data]);

  // Update "X mins ago" every 30s
  useEffect(() => {
    if (!lastUpdated) return;
    const tick = () => {
      const diff = Math.floor((Date.now() - lastUpdated.getTime()) / 60_000);
      setMinsAgo(diff);
    };
    tick();
    const interval = setInterval(tick, 30_000);
    return () => clearInterval(interval);
  }, [lastUpdated]);

  function handleSort(key: string) {
    setSortBy((prev) => (prev === key ? undefined : key));
  }

  return (
    <div>
      {/* League header */}
      {isLoading && !data ? (
        <div className="mb-4"><SkeletonCard /></div>
      ) : data?.league ? (
        <>
          <LeagueHeader league={data.league} />
          <TabBar leagueId={id} />
        </>
      ) : null}

      <div className="mt-4">
        {/* Last updated */}
        <div className="flex items-center justify-between mb-3">
          <h2 className="text-lg font-semibold text-white">Leaderboard</h2>
          {lastUpdated && (
            <span className="text-xs text-gray-500">
              Last updated {minsAgo === 0 ? 'just now' : `${minsAgo} min${minsAgo !== 1 ? 's' : ''} ago`}
            </span>
          )}
        </div>

        {isLoading && !data ? (
          <div className="rounded-xl border border-[#222222] overflow-hidden">
            <table className="min-w-full">
              <tbody>
                {Array.from({ length: 8 }).map((_, i) => (
                  <SkeletonRow key={i} cols={10} />
                ))}
              </tbody>
            </table>
          </div>
        ) : error ? (
          <ErrorState
            message="Couldn't load the leaderboard."
            onRetry={() => mutate()}
          />
        ) : !data?.leaderboard?.length ? (
          <EmptyState
            title="No entries yet"
            description="The leaderboard will populate once participants make trades."
          />
        ) : (
          <LeaderboardTable
            entries={data.leaderboard}
            currentUser={user}
            onSortChange={handleSort}
            activeSort={sortBy}
          />
        )}
      </div>
    </div>
  );
}
