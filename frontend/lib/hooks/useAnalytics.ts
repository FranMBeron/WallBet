'use client';

import useSWR from 'swr';
import { apiFetcher } from '@/lib/api';
import type { AnalyticsResponse } from '@/types/api';

export function useAnalytics(leagueId: string | null) {
  return useSWR<AnalyticsResponse>(
    leagueId ? `/leagues/${leagueId}/analytics` : null,
    apiFetcher,
  );
}
