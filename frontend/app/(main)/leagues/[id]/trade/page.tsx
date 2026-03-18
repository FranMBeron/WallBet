'use client';

import { useLeague } from '@/lib/hooks/useLeague';
import { LeagueHeader } from '@/components/layout/LeagueHeader';
import { TabBar } from '@/components/layout/TabBar';
import { TradeForm } from '@/components/trade/TradeForm';
import { TradeHistory } from '@/components/trade/TradeHistory';
import { SkeletonCard } from '@/components/ui/SkeletonCard';
import { EmptyState } from '@/components/ui/EmptyState';

interface Props {
  params: { id: string };
}

export default function TradePage({ params }: Props) {
  const { id } = params;
  const { data: league, isLoading: leagueLoading } = useLeague(id);

  return (
    <div>
      {/* Header */}
      {leagueLoading ? (
        <div className="mb-4"><SkeletonCard /></div>
      ) : league ? (
        <>
          <LeagueHeader league={league} />
          <TabBar leagueId={id} leagueStatus={league.status} />
        </>
      ) : null}

      <div className="mt-4">
        <h2 className="text-lg font-semibold text-white mb-4">Trade</h2>

        {leagueLoading ? (
          <div className="space-y-4">
            <SkeletonCard />
            <SkeletonCard />
          </div>
        ) : !league ? null : league.status !== 'active' ? (
          <EmptyState
            title="Trading no disponible"
            description="Solo podés operar cuando la liga está activa."
          />
        ) : (
          <div className="grid gap-6 lg:grid-cols-5">
            <div className="lg:col-span-2">
              <TradeForm leagueId={id} />
            </div>
            <div className="lg:col-span-3">
              <TradeHistory leagueId={id} />
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
