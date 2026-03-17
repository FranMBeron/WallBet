'use client';

import useSWR from 'swr';
import { apiFetcher } from '@/lib/api';
import type { League } from '@/types/api';

export function useMyLeagues() {
  return useSWR<League[]>('/leagues/my', apiFetcher);
}
