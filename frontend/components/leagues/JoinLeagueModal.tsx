'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from '@/components/ui/dialog';
import { apiMutate } from '@/lib/api';
import { useWallbitStatus } from '@/lib/hooks/useWallbitStatus';
import { formatCurrency } from '@/lib/utils';
import type { League, ApiValidationError } from '@/types/api';

interface JoinLeagueModalProps {
  league: League;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSuccess?: () => void;
}

export function JoinLeagueModal({
  league,
  open,
  onOpenChange,
  onSuccess,
}: JoinLeagueModalProps) {
  const router = useRouter();
  const { data: wallbitStatus } = useWallbitStatus();
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  // If Wallbit not connected, prompt redirect instead of showing join form
  if (wallbitStatus && !wallbitStatus.connected) {
    return (
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="bg-[#111111] border border-[#222222] text-white">
          <DialogHeader>
            <DialogTitle>Connect Wallbit first</DialogTitle>
            <DialogDescription className="text-gray-400">
              You need a connected Wallbit account to join a league. Your
              portfolio data comes from Wallbit.
            </DialogDescription>
          </DialogHeader>
          <div className="flex gap-3 mt-2">
            <button
              onClick={() => router.push('/connect-wallbit')}
              className="flex-1 rounded-md bg-[#1B6FEB] px-4 py-2 text-sm font-medium text-white hover:bg-[#1559c9] transition-colors"
            >
              Connect Wallbit
            </button>
            <button
              onClick={() => onOpenChange(false)}
              className="flex-1 rounded-md border border-[#333333] px-4 py-2 text-sm text-gray-300 hover:bg-[#222222] transition-colors"
            >
              Cancel
            </button>
          </div>
        </DialogContent>
      </Dialog>
    );
  }

  async function handleJoin(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setLoading(true);

    try {
      await apiMutate(`/leagues/${league.id}/join`, 'POST', {
        password: !league.is_public ? password : undefined,
      });
      onOpenChange(false);
      onSuccess?.();
      router.push(`/leagues/${league.id}`);
    } catch (err: unknown) {
      const apiErr = err as ApiValidationError;
      const firstErr = apiErr?.errors
        ? Object.values(apiErr.errors).flat()[0]
        : null;
      setError(firstErr ?? apiErr?.message ?? 'Failed to join league.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="bg-[#111111] border border-[#222222] text-white">
        <DialogHeader>
          <DialogTitle>Join {league.name}</DialogTitle>
          <DialogDescription className="text-gray-400">
            {league.buy_in > 0
              ? `Entry fee: ${formatCurrency(league.buy_in)}`
              : 'Free to join'}
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleJoin} className="space-y-4 mt-2">
          {!league.is_public && (
            <div>
              <label htmlFor="modal-password" className="block text-sm font-medium text-gray-300 mb-1">
                League password
              </label>
              <input
                id="modal-password"
                type="text"
                required
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="w-full rounded-md border border-[#333333] bg-black px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-[#1B6FEB] focus:outline-none focus:ring-1 focus:ring-[#1B6FEB]"
                placeholder="Enter league password"
              />
            </div>
          )}

          {error && (
            <p className="rounded-md bg-red-950/40 border border-red-800/40 px-3 py-2 text-sm text-red-400">
              {error}
            </p>
          )}

          <div className="flex gap-3">
            <button
              type="submit"
              disabled={loading}
              className="flex-1 rounded-md bg-[#1B6FEB] px-4 py-2 text-sm font-medium text-white hover:bg-[#1559c9] disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {loading ? 'Joining…' : 'Confirm Join'}
            </button>
            <button
              type="button"
              onClick={() => onOpenChange(false)}
              className="flex-1 rounded-md border border-[#333333] px-4 py-2 text-sm text-gray-300 hover:bg-[#222222] transition-colors"
            >
              Cancel
            </button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
}
