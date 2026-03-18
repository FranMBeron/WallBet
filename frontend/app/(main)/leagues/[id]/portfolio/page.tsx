'use client';

import { useSearchParams } from 'next/navigation';
import { Lock } from 'lucide-react';
import { usePortfolio } from '@/lib/hooks/usePortfolio';
import { useLeague } from '@/lib/hooks/useLeague';
import { useAuth } from '@/lib/hooks/useAuth';
import { LeagueHeader } from '@/components/layout/LeagueHeader';
import { TabBar } from '@/components/layout/TabBar';
import { PortfolioSummary } from '@/components/portfolio/PortfolioSummary';
import { PositionsTable } from '@/components/portfolio/PositionsTable';
import { AllocationDonut } from '@/components/portfolio/AllocationDonut';
import { SkeletonCard } from '@/components/ui/SkeletonCard';
import { ErrorState } from '@/components/ui/ErrorState';
import { EmptyState } from '@/components/ui/EmptyState';

interface Props {
  params: { id: string };
}

export default function PortfolioPage({ params }: Props) {
  const { id } = params;
  const searchParams = useSearchParams();
  const targetUserId = searchParams.get('userId') ?? undefined;

  const { user: currentUser } = useAuth();
  const { data: league, isLoading: leagueLoading } = useLeague(id);
  const { data: portfolio, isLoading, error, mutate } = usePortfolio(id, targetUserId);

  const isOwnPortfolio = !targetUserId || targetUserId === currentUser?.id;
  const isActiveLeague = league?.status === 'active';
  const showLocked = !isOwnPortfolio && isActiveLeague;

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

      <div className="mt-4" data-tour="portfolio-hidden">
        <h2 className="text-lg font-semibold text-white mb-4">
          {isOwnPortfolio ? 'My Portfolio' : `${portfolio?.user?.display_name ?? 'Member'}'s Portfolio`}
        </h2>

        {/* Locked state for other members during active league */}
        {showLocked ? (
          <div className="flex flex-col items-center justify-center rounded-xl border border-[#222222] bg-[#111111] py-16 px-6 text-center">
            <Lock className="h-10 w-10 text-gray-600 mb-4" />
            <h3 className="text-base font-semibold text-white mb-1">Positions are private</h3>
            <p className="text-sm text-gray-400 max-w-xs">
              Other members&apos; portfolios are hidden while the league is active. Check back after the league ends.
            </p>
          </div>
        ) : isLoading ? (
          <div className="space-y-4">
            <SkeletonCard />
            <SkeletonCard />
          </div>
        ) : error && ![403, 404].includes((error as { status?: number }).status ?? 0) ? (
          <ErrorState
            message="Couldn't load portfolio data."
            onRetry={() => mutate()}
          />
        ) : !portfolio || [403, 404].includes((error as { status?: number } | undefined)?.status ?? 0) ? (
          <EmptyState
            title="No tenés un portfolio en esta liga aún"
            description="Unite a la liga para empezar a operar."
          />
        ) : portfolio.positions.length === 0 ? (
          <EmptyState
            title="No tenés un portfolio en esta liga aún"
            description="Unite a la liga para empezar a operar."
          />
        ) : (
          <div data-tour="portfolio-positions" className="space-y-6">
            {/* Summary stats */}
            <PortfolioSummary summary={portfolio.summary} />

            {/* Donut chart + positions */}
            <div className="grid gap-6 lg:grid-cols-3">
              <div className="lg:col-span-1">
                <div className="rounded-xl border border-[#222222] bg-[#111111] p-4">
                  <h3 className="text-sm font-medium text-gray-400 mb-3">Allocation</h3>
                  <AllocationDonut positions={portfolio.positions} />
                </div>
              </div>

              <div className="lg:col-span-2">
                <h3 className="text-sm font-medium text-gray-400 mb-3">Positions</h3>
                <PositionsTable positions={portfolio.positions} />
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
