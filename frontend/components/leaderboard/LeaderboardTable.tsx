'use client';

import { useState } from 'react';
import { ChevronUp, ChevronDown, ChevronsUpDown } from 'lucide-react';
import { RankBadge } from './RankBadge';
import { formatCurrency, formatPct, gainLossClass, cn } from '@/lib/utils';
import type { LeaderboardEntry, User } from '@/types/api';

type SortKey =
  | 'rank'
  | 'return_pct'
  | 'total_value'
  | 'pnl'
  | 'total_trades'
  | 'unique_tickers'
  | 'best_trade'
  | 'win_rate'
  | 'risk_score';

interface Column {
  key: SortKey;
  label: string;
  align?: 'left' | 'right';
}

const COLUMNS: Column[] = [
  { key: 'rank',           label: 'Rank',           align: 'left' },
  { key: 'return_pct',     label: 'Return %',        align: 'right' },
  { key: 'total_value',    label: 'Portfolio Value', align: 'right' },
  { key: 'pnl',            label: 'P&L',             align: 'right' },
  { key: 'total_trades',   label: '# Trades',        align: 'right' },
  { key: 'unique_tickers', label: '# Tickers',       align: 'right' },
  { key: 'best_trade',     label: 'Best Trade',      align: 'right' },
  { key: 'win_rate',       label: 'Win Rate',        align: 'right' },
  { key: 'risk_score',     label: 'Risk Score',      align: 'left' },
];

interface LeaderboardTableProps {
  entries: LeaderboardEntry[];
  currentUser?: User | null;
  onSortChange?: (key: SortKey) => void;
  activeSort?: string;
}

function SortIcon({ col, activeSort }: { col: SortKey; activeSort?: string }) {
  if (activeSort === col) return <ChevronDown className="h-3.5 w-3.5 inline ml-1" />;
  if (activeSort === `-${col}`) return <ChevronUp className="h-3.5 w-3.5 inline ml-1" />;
  return <ChevronsUpDown className="h-3.5 w-3.5 inline ml-1 opacity-40" />;
}

export function LeaderboardTable({
  entries,
  currentUser,
  onSortChange,
  activeSort,
}: LeaderboardTableProps) {
  const [localSort, setLocalSort] = useState<string>('rank');
  const effectiveSort = onSortChange ? (activeSort ?? 'rank') : localSort;

  function handleSort(key: SortKey) {
    if (onSortChange) {
      onSortChange(key);
      return;
    }
    setLocalSort((prev) => (prev === key ? `-${key}` : key));
  }

  // Local sort when no external handler provided
  const sorted = onSortChange ? entries : [...entries].sort((a, b) => {
    const desc = effectiveSort.startsWith('-');
    const key = desc ? effectiveSort.slice(1) as SortKey : effectiveSort as SortKey;
    const mul = desc ? -1 : 1;

    switch (key) {
      case 'rank':           return mul * (a.rank - b.rank);
      case 'return_pct':     return mul * (a.return_pct - b.return_pct);
      case 'total_value':    return mul * (a.total_value - b.total_value);
      case 'pnl':            return mul * (a.pnl - b.pnl);
      case 'total_trades':   return mul * (a.total_trades - b.total_trades);
      case 'unique_tickers': return mul * (a.unique_tickers - b.unique_tickers);
      case 'best_trade':     return mul * ((a.best_trade?.return_pct ?? 0) - (b.best_trade?.return_pct ?? 0));
      case 'win_rate':       return mul * ((a.win_rate ?? 0) - (b.win_rate ?? 0));
      case 'risk_score':     return mul * a.risk_score.localeCompare(b.risk_score);
      default:               return 0;
    }
  });

  return (
    <div className="overflow-x-auto rounded-xl border border-[#222222]">
      <table className="min-w-full text-sm">
        <thead>
          <tr className="border-b border-[#222222] bg-[#111111]">
            {/* Rank + User columns — sticky */}
            <th
              className="sticky left-0 z-10 bg-[#111111] px-4 py-3 text-left font-medium text-gray-400 cursor-pointer hover:text-white whitespace-nowrap"
              onClick={() => handleSort('rank')}
            >
              Rank <SortIcon col="rank" activeSort={effectiveSort} />
            </th>
            <th className="sticky left-[96px] z-10 bg-[#111111] px-4 py-3 text-left font-medium text-gray-400 whitespace-nowrap min-w-[140px]">
              User
            </th>

            {/* Scrollable columns */}
            {COLUMNS.filter((c) => c.key !== 'rank').map((col) => (
              <th
                key={col.key}
                className={cn(
                  'px-4 py-3 font-medium text-gray-400 cursor-pointer hover:text-white whitespace-nowrap',
                  col.align === 'right' ? 'text-right' : 'text-left',
                )}
                onClick={() => handleSort(col.key)}
              >
                {col.label}
                <SortIcon col={col.key} activeSort={effectiveSort} />
              </th>
            ))}
          </tr>
        </thead>

        <tbody className="bg-black divide-y divide-[#1a1a1a]">
          {sorted.map((entry) => {
            const isMe = currentUser && entry.user.id === currentUser.id;

            return (
              <tr
                key={entry.user.id}
                className={cn(
                  'transition-colors',
                  isMe
                    ? 'bg-[#1B6FEB]/5 border-l-2 border-l-[#1B6FEB]'
                    : 'hover:bg-[#111111]',
                )}
              >
                {/* Sticky: Rank */}
                <td className={cn(
                  'sticky left-0 z-10 px-4 py-3 whitespace-nowrap',
                  isMe ? 'bg-[#1B6FEB]/5' : 'bg-black',
                )}>
                  <RankBadge rank={entry.rank} rankChange={entry.rank_change} />
                </td>

                {/* Sticky: User */}
                <td className={cn(
                  'sticky left-[96px] z-10 px-4 py-3 whitespace-nowrap min-w-[140px]',
                  isMe ? 'bg-[#1B6FEB]/5' : 'bg-black',
                )}>
                  <div className="flex items-center gap-2">
                    {entry.user.avatar_url ? (
                      // eslint-disable-next-line @next/next/no-img-element
                      <img
                        src={entry.user.avatar_url}
                        alt={entry.user.display_name}
                        className="h-6 w-6 rounded-full flex-shrink-0 object-cover"
                      />
                    ) : (
                      <div className="h-6 w-6 rounded-full bg-[#1B6FEB]/20 flex items-center justify-center flex-shrink-0">
                        <span className="text-[10px] font-bold text-[#1B6FEB]">
                          {entry.user.display_name.charAt(0).toUpperCase()}
                        </span>
                      </div>
                    )}
                    <span className={cn('font-medium truncate max-w-[120px]', isMe ? 'text-[#1B6FEB]' : 'text-white')}>
                      {entry.user.display_name}
                    </span>
                  </div>
                </td>

                {/* Return % */}
                <td className={cn('px-4 py-3 text-right font-semibold tabular-nums', gainLossClass(entry.return_pct))}>
                  {formatPct(entry.return_pct)}
                </td>

                {/* Portfolio Value */}
                <td className="px-4 py-3 text-right text-white tabular-nums">
                  {formatCurrency(entry.total_value)}
                </td>

                {/* P&L */}
                <td className={cn('px-4 py-3 text-right tabular-nums', gainLossClass(entry.pnl))}>
                  {formatCurrency(entry.pnl)}
                </td>

                {/* # Trades */}
                <td className="px-4 py-3 text-right text-gray-300 tabular-nums">
                  {entry.total_trades}
                </td>

                {/* # Tickers */}
                <td className="px-4 py-3 text-right text-gray-300 tabular-nums">
                  {entry.unique_tickers}
                </td>

                {/* Best Trade */}
                <td className="px-4 py-3 text-right whitespace-nowrap">
                  {entry.best_trade ? (
                    <span className={gainLossClass(entry.best_trade.return_pct)}>
                      {entry.best_trade.ticker} {formatPct(entry.best_trade.return_pct)}
                    </span>
                  ) : (
                    <span className="text-gray-600">—</span>
                  )}
                </td>

                {/* Win Rate */}
                <td className="px-4 py-3 text-right text-gray-300 tabular-nums">
                  {entry.win_rate != null ? formatPct(entry.win_rate, 1, false) : '—'}
                </td>

                {/* Risk Score */}
                <td className="px-4 py-3 text-left">
                  <span className="rounded-full bg-[#222222] px-2 py-0.5 text-xs text-gray-300">
                    {entry.risk_score}
                  </span>
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
