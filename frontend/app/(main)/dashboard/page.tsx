'use client';

import { Plus } from 'lucide-react';
import Link from 'next/link';
import { useMyLeagues } from '@/lib/hooks/useMyLeagues';
import { LeagueCard } from '@/components/leagues/LeagueCard';
import { SkeletonCard } from '@/components/ui/SkeletonCard';
import { EmptyState } from '@/components/ui/EmptyState';
import { ErrorState } from '@/components/ui/ErrorState';

export default function DashboardPage() {
  const { data: leagues, isLoading, error, mutate } = useMyLeagues();

  return (
    <div>
      {/* Page header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-white">My Leagues</h1>
          <p className="text-sm text-gray-400 mt-0.5">
            Leagues you&apos;ve joined or created
          </p>
        </div>
        <Link
          href="/leagues/new"
          className="flex items-center gap-1.5 rounded-md bg-[#1B6FEB] px-3 py-2 text-sm font-medium text-white hover:bg-[#1559c9] transition-colors"
        >
          <Plus className="h-4 w-4" />
          New League
        </Link>
      </div>

      {/* Content */}
      {isLoading ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <SkeletonCard key={i} />
          ))}
        </div>
      ) : error ? (
        <ErrorState
          message="Couldn't load your leagues."
          onRetry={() => mutate()}
        />
      ) : !leagues?.length ? (
        <EmptyState
          title="No leagues yet"
          description="Join a public league or create your own to start trading."
          ctaLabel="Browse leagues"
          ctaHref="/leagues"
        />
      ) : (
        <div data-tour="dashboard-leagues" className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {leagues.map((league) => (
            <LeagueCard key={league.id} league={league} />
          ))}
        </div>
      )}
    </div>
  );
}
