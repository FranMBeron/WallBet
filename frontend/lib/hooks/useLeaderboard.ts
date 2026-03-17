'use client';

import useSWR from 'swr';
import { apiFetcher } from '@/lib/api';
import type { LeaderboardResponse } from '@/types/api';

export function useLeaderboard(leagueId: string | null, sortBy?: string) {
  const key = leagueId
    ? sortBy
      ? `/leagues/${leagueId}/leaderboard?sort_by=${sortBy}`
      : `/leagues/${leagueId}/leaderboard`
    : null;

  return useSWR<LeaderboardResponse>(key, apiFetcher, {
    refreshInterval: 60_000,
  });
}
