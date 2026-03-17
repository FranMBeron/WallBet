'use client';

import useSWR from 'swr';
import { apiFetcher } from '@/lib/api';
import type { League } from '@/types/api';

export function useLeague(leagueId: string | null) {
  return useSWR<League>(
    leagueId ? `/leagues/${leagueId}` : null,
    apiFetcher,
  );
}
