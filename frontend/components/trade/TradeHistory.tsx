'use client';

import { useState } from 'react';
import useSWR from 'swr';
import { apiRawFetcher } from '@/lib/api';
import type { TradeLog } from '@/types/api';

interface TradeHistoryProps {
  leagueId: string;
}

interface TradesResponse {
  data: TradeLog[];
  current_page: number;
  last_page: number;
}

export function TradeHistory({ leagueId }: TradeHistoryProps) {
  const [page, setPage] = useState(1);

  const { data, isLoading } = useSWR<TradesResponse>(
    `/leagues/${leagueId}/trades?page=${page}`,
    (url: string) => apiRawFetcher<TradesResponse>(url),
    { keepPreviousData: true },
  );

  const trades = data?.data ?? [];
  const hasMore = data ? data.current_page < data.last_page : false;

  return (
    <div className="rounded-xl border border-[#222222] bg-[#111111] p-5">
      <h3 className="text-base font-semibold text-white mb-4">Trade History</h3>

      {isLoading && trades.length === 0 ? (
        <div className="space-y-3">
          {Array.from({ length: 5 }).map((_, i) => (
            <div key={i} className="h-8 bg-[#222222] rounded animate-pulse" />
          ))}
        </div>
      ) : trades.length === 0 ? (
        <p className="text-sm text-gray-500 text-center py-8">No trades yet. Execute your first trade above.</p>
      ) : (
        <>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-[#222222] text-gray-500">
                  <th className="text-left py-2 pr-3 font-medium">Ticker</th>
                  <th className="text-left py-2 pr-3 font-medium">Action</th>
                  <th className="text-right py-2 pr-3 font-medium">Qty</th>
                  <th className="text-right py-2 pr-3 font-medium">Price</th>
                  <th className="text-right py-2 pr-3 font-medium">Total</th>
                  <th className="text-right py-2 font-medium">Date</th>
                </tr>
              </thead>
              <tbody>
                {trades.map((trade) => (
                  <tr
                    key={trade.id}
                    className="border-b border-[#1a1a1a] last:border-0"
                  >
                    <td className="py-2 pr-3 font-mono text-white">
                      {trade.ticker}
                    </td>
                    <td className="py-2 pr-3">
                      <span
                        className={`inline-block text-xs font-semibold px-2 py-0.5 rounded ${
                          trade.action === 'BUY'
                            ? 'bg-green-900/40 text-green-400'
                            : 'bg-red-900/40 text-red-400'
                        }`}
                      >
                        {trade.action}
                      </span>
                    </td>
                    <td className="py-2 pr-3 text-right text-gray-300">
                      {trade.quantity.toFixed(4)}
                    </td>
                    <td className="py-2 pr-3 text-right text-gray-300">
                      ${trade.price.toLocaleString('en-US', { minimumFractionDigits: 2 })}
                    </td>
                    <td className="py-2 pr-3 text-right text-white font-medium">
                      ${trade.total_amount.toLocaleString('en-US', { minimumFractionDigits: 2 })}
                    </td>
                    <td className="py-2 text-right text-gray-500 text-xs">
                      {new Date(trade.executed_at).toLocaleDateString('en-US', {
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                      })}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {hasMore && (
            <button
              onClick={() => setPage((p) => p + 1)}
              className="w-full mt-4 rounded-lg border border-[#333] px-4 py-2 text-sm text-gray-400 hover:text-white hover:border-[#555] transition-colors"
            >
              Load more
            </button>
          )}
        </>
      )}
    </div>
  );
}
