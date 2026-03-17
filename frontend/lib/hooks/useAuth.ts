'use client';

import useSWR from 'swr';
import { apiFetcher } from '@/lib/api';
import type { User } from '@/types/api';

export function useAuth() {
  const { data, error, isLoading, mutate } = useSWR<User>(
    '/auth/me',
    apiFetcher,
    {
      // Revalidate on window focus to catch logout from other tabs
      revalidateOnFocus: true,
      // Don't retry on 401 — the fetcher handles redirect
      shouldRetryOnError: false,
    },
  );

  return {
    user: data,
    isLoading,
    error,
    mutate,
    isAuthenticated: !!data && !error,
  };
}
