'use client';

import useSWR from 'swr';
import { apiFetcher } from '@/lib/api';
import type { WallbitStatus } from '@/types/api';

export function useWallbitStatus() {
  return useSWR<WallbitStatus>('/wallbit/status', apiFetcher);
}
