'use client';

import { useState } from 'react';
import Link from 'next/link';
import { Search, Plus } from 'lucide-react';
import { useLeagues } from '@/lib/hooks/useLeagues';
import { LeagueCard } from '@/components/leagues/LeagueCard';
import { SkeletonCard } from '@/components/ui/SkeletonCard';
import { EmptyState } from '@/components/ui/EmptyState';
import { ErrorState } from '@/components/ui/ErrorState';

export default function LeaguesPage() {
  const { data: leagues, isLoading, error, mutate } = useLeagues();
  const [query, setQuery] = useState('');

  const filtered = leagues?.filter((l) =>
    l.name.toLowerCase().includes(query.toLowerCase()),
  );

  return (
    <div>
      {/* Page header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-white">Leagues</h1>
          <p className="text-sm text-gray-400 mt-0.5">Browse all public leagues</p>
        </div>
        <Link
          href="/leagues/new"
          className="flex items-center gap-1.5 rounded-md bg-[#1B6FEB] px-3 py-2 text-sm font-medium text-white hover:bg-[#1559c9] transition-colors"
        >
          <Plus className="h-4 w-4" />
          New League
        </Link>
      </div>

      {/* Search filter */}
      {!isLoading && !error && (
        <div className="relative mb-5 max-w-xs">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-500" />
          <input
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Search leagues…"
            className="w-full rounded-md border border-[#333333] bg-black pl-9 pr-3 py-2 text-sm text-white placeholder-gray-500 focus:border-[#1B6FEB] focus:outline-none focus:ring-1 focus:ring-[#1B6FEB]"
          />
        </div>
      )}

      {/* Content */}
      {isLoading ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {Array.from({ length: 6 }).map((_, i) => (
            <SkeletonCard key={i} />
          ))}
        </div>
      ) : error ? (
        <ErrorState
          message="Couldn't load leagues."
          onRetry={() => mutate()}
        />
      ) : !filtered?.length ? (
        <EmptyState
          title={query ? 'No matching leagues' : 'No leagues yet'}
          description={
            query
              ? `No leagues found for "${query}".`
              : 'Be the first to create a league!'
          }
          ctaLabel={query ? undefined : 'Create a league'}
          ctaHref={query ? undefined : '/leagues/new'}
        />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {filtered.map((league) => (
            <LeagueCard key={league.id} league={league} />
          ))}
        </div>
      )}
    </div>
  );
}
