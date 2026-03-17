import { TrendingUp, TrendingDown } from 'lucide-react';
import { formatPct, gainLossClass } from '@/lib/utils';
import type { AnalyticsResponse } from '@/types/api';

interface AnalyticsCardsProps {
  data: Pick<AnalyticsResponse, 'avg_return_pct' | 'median_return_pct' | 'positive_count' | 'negative_count'>;
}

export function AnalyticsCards({ data }: AnalyticsCardsProps) {
  const cards = [
    {
      label: 'Avg Return',
      value: formatPct(data.avg_return_pct),
      valueClass: gainLossClass(data.avg_return_pct),
      icon: data.avg_return_pct >= 0 ? TrendingUp : TrendingDown,
    },
    {
      label: 'Median Return',
      value: formatPct(data.median_return_pct),
      valueClass: gainLossClass(data.median_return_pct),
      icon: data.median_return_pct >= 0 ? TrendingUp : TrendingDown,
    },
    {
      label: 'Positive Traders',
      value: String(data.positive_count),
      valueClass: 'text-gain',
      icon: TrendingUp,
    },
    {
      label: 'Negative Traders',
      value: String(data.negative_count),
      valueClass: 'text-loss',
      icon: TrendingDown,
    },
  ];

  return (
    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 mb-6">
      {cards.map(({ label, value, valueClass, icon: Icon }) => (
        <div
          key={label}
          className="rounded-xl border border-[#222222] bg-[#111111] px-4 py-3"
        >
          <div className="flex items-center justify-between mb-2">
            <p className="text-xs text-gray-400">{label}</p>
            <Icon className={`h-4 w-4 ${valueClass}`} />
          </div>
          <p className={`text-2xl font-bold tabular-nums ${valueClass}`}>{value}</p>
        </div>
      ))}
    </div>
  );
}
