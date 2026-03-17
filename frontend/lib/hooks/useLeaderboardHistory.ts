'use client';

import useSWR from 'swr';
import { apiFetcher } from '@/lib/api';
import type { LeaderboardHistory } from '@/types/api';

export function useLeaderboardHistory(leagueId: string | null) {
  return useSWR<LeaderboardHistory>(
    leagueId ? `/leagues/${leagueId}/leaderboard/history` : null,
    apiFetcher,
  );
}
