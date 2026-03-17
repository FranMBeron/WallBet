'use client';

import { useState } from 'react';
import { useCompare } from '@/lib/hooks/useCompare';
import { EvolutionLine } from '@/components/analytics/EvolutionLine';
import { Skeleton } from '@/components/ui/SkeletonCard';
import { ErrorState } from '@/components/ui/ErrorState';
import { formatPct, gainLossClass } from '@/lib/utils';

interface CompareParticipantOption {
  id: string;
  display_name: string;
}

interface CompareLayoutProps {
  leagueId: string;
  participants: CompareParticipantOption[];
}

function MetricRow({ label, value1, value2 }: { label: string; value1: React.ReactNode; value2: React.ReactNode }) {
  return (
    <div className="grid grid-cols-3 items-center gap-2 py-2 border-b border-[#1a1a1a] last:border-0">
      <div className="text-xs text-gray-400 text-center">{value1}</div>
      <div className="text-xs text-gray-500 text-center">{label}</div>
      <div className="text-xs text-gray-400 text-center">{value2}</div>
    </div>
  );
}

function PositionsList({ positions }: { positions: { ticker: string; shares: number; value: number; weight_pct: number }[] }) {
  if (!positions.length) return <p className="text-xs text-gray-500 text-center py-4">No positions</p>;
  return (
    <div className="space-y-1">
      {positions.map((p) => (
        <div key={p.ticker} className="flex items-center justify-between text-xs py-1">
          <span className="font-semibold text-white">{p.ticker}</span>
          <span className="text-gray-400">{p.weight_pct.toFixed(1)}%</span>
        </div>
      ))}
    </div>
  );
}

// Build a minimal LeaderboardHistory shape from CompareEvolution data for EvolutionLine
function buildHistoryFromEvolution(
  dates: string[],
  user1Returns: (number | null)[],
  user2Returns: (number | null)[],
  user1Id: string,
  user1Name: string,
  user2Id: string,
  user2Name: string,
) {
  return dates.map((date, i) => ({
    date,
    entries: [
      ...(user1Returns[i] != null
        ? [{ user: { id: user1Id, display_name: user1Name, username: '', email: '', avatar_url: null, has_wallbit_key: true }, rank: 0, return_pct: user1Returns[i] as number }]
        : []),
      ...(user2Returns[i] != null
        ? [{ user: { id: user2Id, display_name: user2Name, username: '', email: '', avatar_url: null, has_wallbit_key: true }, rank: 0, return_pct: user2Returns[i] as number }]
        : []),
    ],
  }));
}

export function CompareLayout({ leagueId, participants }: CompareLayoutProps) {
  const [user1Id, setUser1Id] = useState<string>('');
  const [user2Id, setUser2Id] = useState<string>('');

  const { data, isLoading, error, mutate } = useCompare(
    leagueId,
    user1Id || null,
    user2Id || null,
  );

  const selectCls =
    'w-full rounded-md border border-[#333333] bg-black px-3 py-2 text-sm text-white focus:border-[#1B6FEB] focus:outline-none focus:ring-1 focus:ring-[#1B6FEB]';

  return (
    <div className="space-y-5">
      {/* User selectors */}
      <div className="grid grid-cols-2 gap-4">
        <div>
          <label className="block text-xs font-medium text-gray-400 mb-1">Participant 1</label>
          <select value={user1Id} onChange={(e) => setUser1Id(e.target.value)} className={selectCls}>
            <option value="">Select participant…</option>
            {participants
              .filter((p) => p.id !== user2Id)
              .map((p) => (
                <option key={p.id} value={p.id}>{p.display_name}</option>
              ))}
          </select>
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-400 mb-1">Participant 2</label>
          <select value={user2Id} onChange={(e) => setUser2Id(e.target.value)} className={selectCls}>
            <option value="">Select participant…</option>
            {participants
              .filter((p) => p.id !== user1Id)
              .map((p) => (
                <option key={p.id} value={p.id}>{p.display_name}</option>
              ))}
          </select>
        </div>
      </div>

      {/* Prompt if not both selected */}
      {(!user1Id || !user2Id) && (
        <p className="text-sm text-gray-500 text-center py-6">
          Select two participants to compare their performance.
        </p>
      )}

      {/* Loading */}
      {isLoading && (
        <div className="space-y-3">
          <Skeleton className="h-40 w-full" />
          <Skeleton className="h-64 w-full" />
        </div>
      )}

      {/* Error */}
      {error && <ErrorState message="Couldn't load comparison data." onRetry={() => mutate()} />}

      {/* Comparison data */}
      {data && (
        <div className="space-y-5">
          {/* Side-by-side metrics */}
          <div className="rounded-xl border border-[#222222] bg-[#111111] p-4">
            <div className="grid grid-cols-3 mb-3">
              <span className="text-sm font-semibold text-[#1B6FEB] text-center">{data.user1.display_name}</span>
              <span className="text-xs text-gray-500 text-center self-center">vs</span>
              <span className="text-sm font-semibold text-[#22C55E] text-center">{data.user2.display_name}</span>
            </div>
            <MetricRow
              label="Return"
              value1={<span className={gainLossClass(data.user1.return_pct)}>{formatPct(data.user1.return_pct)}</span>}
              value2={<span className={gainLossClass(data.user2.return_pct)}>{formatPct(data.user2.return_pct)}</span>}
            />
            <MetricRow
              label="Trades"
              value1={<span className="text-white">{data.user1.total_trades}</span>}
              value2={<span className="text-white">{data.user2.total_trades}</span>}
            />
            <MetricRow
              label="Tickers"
              value1={<span className="text-white">{data.user1.unique_tickers}</span>}
              value2={<span className="text-white">{data.user2.unique_tickers}</span>}
            />
            <MetricRow
              label="Win Rate"
              value1={<span className="text-white">{data.user1.win_rate != null ? formatPct(data.user1.win_rate, 1, false) : '—'}</span>}
              value2={<span className="text-white">{data.user2.win_rate != null ? formatPct(data.user2.win_rate, 1, false) : '—'}</span>}
            />
          </div>

          {/* Side-by-side positions */}
          <div className="grid grid-cols-2 gap-4">
            <div className="rounded-xl border border-[#222222] bg-[#111111] p-4">
              <h4 className="text-xs font-medium text-[#1B6FEB] mb-3">{data.user1.display_name}&apos;s Positions</h4>
              <PositionsList positions={data.user1.positions} />
            </div>
            <div className="rounded-xl border border-[#222222] bg-[#111111] p-4">
              <h4 className="text-xs font-medium text-[#22C55E] mb-3">{data.user2.display_name}&apos;s Positions</h4>
              <PositionsList positions={data.user2.positions} />
            </div>
          </div>

          {/* Shared tickers */}
          {data.shared_tickers.length > 0 && (
            <div className="rounded-xl border border-[#222222] bg-[#111111] p-4">
              <h4 className="text-sm font-medium text-gray-400 mb-3">
                Shared Tickers <span className="text-white">({data.shared_tickers.length})</span>
              </h4>
              <div className="flex flex-wrap gap-2">
                {data.shared_tickers.map((ticker) => (
                  <span
                    key={ticker}
                    className="rounded-full bg-[#1B6FEB]/10 border border-[#1B6FEB]/30 px-3 py-1 text-xs font-semibold text-[#1B6FEB]"
                  >
                    {ticker}
                  </span>
                ))}
              </div>
            </div>
          )}

          {/* Mini evolution chart for both users */}
          {data.evolution.dates.length > 0 && (
            <EvolutionLine
              history={buildHistoryFromEvolution(
                data.evolution.dates,
                data.evolution.user1_returns,
                data.evolution.user2_returns,
                data.user1.id,
                data.user1.display_name,
                data.user2.id,
                data.user2.display_name,
              )}
              userIds={[data.user1.id, data.user2.id]}
            />
          )}
        </div>
      )}
    </div>
  );
}
