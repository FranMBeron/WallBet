import { formatCurrency, formatPct, gainLossClass } from '@/lib/utils';
import type { Position } from '@/types/api';

interface PositionsTableProps {
  positions: Position[];
}

export function PositionsTable({ positions }: PositionsTableProps) {
  if (!positions.length) return null;

  return (
    <div className="overflow-x-auto rounded-xl border border-[#222222]">
      <table className="min-w-full text-sm">
        <thead>
          <tr className="border-b border-[#222222] bg-[#111111]">
            <th className="px-4 py-3 text-left font-medium text-gray-400 whitespace-nowrap">Ticker</th>
            <th className="px-4 py-3 text-left font-medium text-gray-400 whitespace-nowrap hidden sm:table-cell">Name</th>
            <th className="px-4 py-3 text-right font-medium text-gray-400 whitespace-nowrap">Qty</th>
            <th className="px-4 py-3 text-right font-medium text-gray-400 whitespace-nowrap">Avg Price</th>
            <th className="px-4 py-3 text-right font-medium text-gray-400 whitespace-nowrap">Current</th>
            <th className="px-4 py-3 text-right font-medium text-gray-400 whitespace-nowrap">P&amp;L</th>
            <th className="px-4 py-3 text-right font-medium text-gray-400 whitespace-nowrap">P&amp;L %</th>
            <th className="px-4 py-3 text-right font-medium text-gray-400 whitespace-nowrap">Weight</th>
          </tr>
        </thead>
        <tbody className="bg-black divide-y divide-[#1a1a1a]">
          {positions.map((pos) => (
            <tr key={pos.ticker} className="hover:bg-[#111111] transition-colors">
              <td className="px-4 py-3 font-semibold text-white whitespace-nowrap">
                {pos.ticker}
              </td>
              <td className="px-4 py-3 text-gray-300 whitespace-nowrap hidden sm:table-cell max-w-[160px] truncate">
                {pos.name}
              </td>
              <td className="px-4 py-3 text-right text-gray-300 tabular-nums">
                {pos.quantity.toLocaleString()}
              </td>
              <td className="px-4 py-3 text-right text-gray-300 tabular-nums">
                {formatCurrency(pos.avg_price)}
              </td>
              <td className="px-4 py-3 text-right text-white tabular-nums">
                {formatCurrency(pos.current_price)}
              </td>
              <td className={`px-4 py-3 text-right tabular-nums font-medium ${gainLossClass(pos.pnl)}`}>
                {formatCurrency(pos.pnl)}
              </td>
              <td className={`px-4 py-3 text-right tabular-nums font-medium ${gainLossClass(pos.pnl_pct)}`}>
                {formatPct(pos.pnl_pct)}
              </td>
              <td className="px-4 py-3 text-right text-gray-400 tabular-nums">
                {pos.weight_pct.toFixed(1)}%
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
