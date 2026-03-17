import { cn } from '@/lib/utils';

interface RankBadgeProps {
  rank: number;
  rankChange: number;
}

export function RankBadge({ rank, rankChange }: RankBadgeProps) {
  const changeLabel =
    rankChange > 0 ? `↑${rankChange}` :
    rankChange < 0 ? `↓${Math.abs(rankChange)}` :
    '—';

  const changeClass =
    rankChange > 0 ? 'text-gain' :
    rankChange < 0 ? 'text-loss' :
    'text-gray-500';

  return (
    <div className="flex items-center gap-1.5 min-w-0">
      <span className="font-semibold text-white">#{rank}</span>
      <span className={cn('text-xs font-medium tabular-nums', changeClass)}>
        {changeLabel}
      </span>
    </div>
  );
}
