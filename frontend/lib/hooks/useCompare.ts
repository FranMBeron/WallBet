'use client';

import useSWR from 'swr';
import { apiFetcher } from '@/lib/api';
import type { CompareResponse } from '@/types/api';

// NOTE: The backend CompareController uses user1/user2 query params (not users[]).
// Only fetches when both user IDs are defined.
export function useCompare(
  leagueId: string | null,
  user1Id?: string | null,
  user2Id?: string | null,
) {
  const key =
    leagueId && user1Id && user2Id
      ? `/leagues/${leagueId}/compare?user1=${user1Id}&user2=${user2Id}`
      : null;

  return useSWR<CompareResponse>(key, apiFetcher);
}
