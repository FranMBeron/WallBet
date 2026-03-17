import { formatCurrency, formatPct, gainLossClass } from '@/lib/utils';
import type { PortfolioSummary as PortfolioSummaryType } from '@/types/api';

interface PortfolioSummaryProps {
  summary: PortfolioSummaryType;
}

interface StatProps {
  label: string;
  value: string;
  valueClass?: string;
}

function Stat({ label, value, valueClass = 'text-white' }: StatProps) {
  return (
    <div className="rounded-lg border border-[#222222] bg-[#111111] px-4 py-3">
      <p className="text-xs text-gray-400 mb-1">{label}</p>
      <p className={`text-lg font-semibold tabular-nums ${valueClass}`}>{value}</p>
    </div>
  );
}

export function PortfolioSummary({ summary }: PortfolioSummaryProps) {
  return (
    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 mb-5">
      <Stat
        label="Initial Capital"
        value={formatCurrency(summary.initial_capital)}
      />
      <Stat
        label="Portfolio Value"
        value={formatCurrency(summary.total_value)}
      />
      <Stat
        label="Cash Available"
        value={formatCurrency(summary.cash_available)}
      />
      <Stat
        label="Return"
        value={formatPct(summary.return_pct)}
        valueClass={gainLossClass(summary.return_pct) + ' font-bold'}
      />
    </div>
  );
}
