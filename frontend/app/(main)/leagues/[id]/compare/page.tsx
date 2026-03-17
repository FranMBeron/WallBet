'use client';

import { use } from 'react';
import { useLeague } from '@/lib/hooks/useLeague';
import { useLeaderboard } from '@/lib/hooks/useLeaderboard';
import { LeagueHeader } from '@/components/layout/LeagueHeader';
import { TabBar } from '@/components/layout/TabBar';
import { LockedOverlay } from '@/components/compare/LockedOverlay';
import { CompareLayout } from '@/components/compare/CompareLayout';
import { SkeletonCard } from '@/components/ui/SkeletonCard';
import { ErrorState } from '@/components/ui/ErrorState';

interface Props {
  params: Promise<{ id: string }>;
}

export default function ComparePage({ params }: Props) {
  const { id } = use(params);
  const { data: league, isLoading: leagueLoading, error: leagueError, mutate: mutateLeague } = useLeague(id);

  // Fetch leaderboard to get participants list for finished leagues
  const { data: leaderboardData } = useLeaderboard(
    league?.status === 'finished' ? id : null,
  );

  const participants = leaderboardData?.leaderboard.map((e) => ({
    id: e.user.id,
    display_name: e.user.display_name,
  })) ?? [];

  return (
    <div>
      {/* Header */}
      {leagueLoading ? (
        <div className="mb-4"><SkeletonCard /></div>
      ) : leagueError ? (
        <ErrorState message="Couldn't load league." onRetry={() => mutateLeague()} />
      ) : league ? (
        <>
          <LeagueHeader league={league} />
          <TabBar leagueId={id} />
        </>
      ) : null}

      <div className="mt-4">
        <h2 className="text-lg font-semibold text-white mb-4">Compare</h2>

        {leagueLoading ? (
          <SkeletonCard />
        ) : !league ? null : league.status === 'active' || league.status === 'upcoming' ? (
          /* Design override: active league → LockedOverlay; no comparison UI */
          <LockedOverlay endsAt={league.ends_at} />
        ) : (
          /* Finished league → CompareLayout with participant selector */
          <CompareLayout leagueId={id} participants={participants} />
        )}
      </div>
    </div>
  );
}
