'use client';


import { Lock } from 'lucide-react';
import { useAnalytics } from '@/lib/hooks/useAnalytics';
import { useLeaderboardHistory } from '@/lib/hooks/useLeaderboardHistory';
import { useLeague } from '@/lib/hooks/useLeague';
import { LeagueHeader } from '@/components/layout/LeagueHeader';
import { TabBar } from '@/components/layout/TabBar';
import { AnalyticsCards } from '@/components/analytics/AnalyticsCards';
import { DistributionBar } from '@/components/analytics/DistributionBar';
import { EvolutionLine } from '@/components/analytics/EvolutionLine';
import { SkeletonCard } from '@/components/ui/SkeletonCard';
import { ErrorState } from '@/components/ui/ErrorState';
import { EmptyState } from '@/components/ui/EmptyState';

// Top tickers section — design override: parent guards on `status === 'finished'`,
// child uses optional chaining on top_tickers?.map(...)
function TopTickersSection({ analytics }: { analytics: { top_tickers: Array<{ ticker: string; holders: number; avg_weight: number }> | null } }) {
  return (
    <div className="rounded-xl border border-[#222222] bg-[#111111] p-4">
      <h3 className="text-sm font-medium text-gray-400 mb-3">Top Tickers</h3>
      <div className="space-y-2">
        {analytics.top_tickers?.map((t) => (
          <div key={t.ticker} className="flex items-center justify-between py-1 border-b border-[#1a1a1a] last:border-0">
            <span className="font-semibold text-white">{t.ticker}</span>
            <div className="flex gap-4 text-sm text-gray-400">
              <span>{t.holders} holder{t.holders !== 1 ? 's' : ''}</span>
              <span>{t.avg_weight.toFixed(1)}% avg</span>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

interface Props {
  params: { id: string };
}

export default function AnalyticsPage({ params }: Props) {
  const { id } = params;
  const { data: league, isLoading: leagueLoading } = useLeague(id);
  const { data: analytics, isLoading, error, mutate } = useAnalytics(id);
  const { data: history } = useLeaderboardHistory(id);

  return (
    <div>
      {/* Header */}
      {leagueLoading ? (
        <div className="mb-4"><SkeletonCard /></div>
      ) : league ? (
        <>
          <LeagueHeader league={league} />
          <TabBar leagueId={id} />
        </>
      ) : null}

      <div className="mt-4 space-y-5">
        {isLoading ? (
          <div className="space-y-4">
            <SkeletonCard />
            <SkeletonCard />
          </div>
        ) : error && (error as { status?: number }).status !== 403 ? (
          <ErrorState
            message="Couldn't load analytics data."
            onRetry={() => mutate()}
          />
        ) : !analytics || (error as { status?: number } | undefined)?.status === 403 || (analytics.avg_return_pct === null && analytics.total_trades === 0) ? (
          <EmptyState
            title="Sin datos todavía"
            description="Las analíticas estarán disponibles una vez que comiencen las operaciones."
          />
        ) : (
          <>
            {/* Stat cards */}
            <div data-tour="analytics-stats">
              <AnalyticsCards data={analytics} />
            </div>

            {/* Distribution chart */}
            {analytics.returns_distribution.length > 0 && (
              <DistributionBar data={analytics.returns_distribution} />
            )}

            {/* Evolution chart */}
            {history && history.length > 0 && (
              <EvolutionLine history={history} />
            )}

            {/* Top tickers — design override:
                Parent: only render <TopTickersSection> when league.status === 'finished'
                Child: uses analytics.top_tickers?.map(...) with optional chaining */}
            {league?.status === 'finished' ? (
              <div data-tour="analytics-tickers">
                <TopTickersSection analytics={analytics} />
              </div>
            ) : (
              <div data-tour="analytics-tickers" className="flex items-center gap-3 rounded-xl border border-[#222222] bg-[#111111] px-4 py-5">
                <Lock className="h-5 w-5 text-gray-600 flex-shrink-0" />
                <div>
                  <p className="text-sm font-medium text-white">Top tickers locked</p>
                  <p className="text-xs text-gray-400 mt-0.5">Revealed after the league ends.</p>
                </div>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
}
