'use client';

import useSWR from 'swr';
import { apiFetcher } from '@/lib/api';
import type { PortfolioResponse } from '@/types/api';

export function usePortfolio(leagueId: string | null, userId?: string) {
  const key = leagueId
    ? userId
      ? `/leagues/${leagueId}/portfolio?userId=${userId}`
      : `/leagues/${leagueId}/portfolio`
    : null;

  return useSWR<PortfolioResponse>(key, apiFetcher, {
    refreshInterval: 30_000,   // re-fetch every 30s so current_price reflects live jitter
    dedupingInterval: 5_000,   // allow re-fetch after trade-triggered mutate()
  });
}
