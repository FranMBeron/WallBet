import Link from 'next/link';
import { Users, DollarSign, Calendar, TrendingUp } from 'lucide-react';
import { LeagueStatusBadge } from './LeagueStatusBadge';
import { formatCurrency, formatPct, formatDate, gainLossClass } from '@/lib/utils';
import type { League } from '@/types/api';

interface LeagueCardProps {
  league: League;
}

export function LeagueCard({ league }: LeagueCardProps) {
  return (
    <Link
      href={`/leagues/${league.id}`}
      className="block rounded-xl border border-[#222222] bg-[#111111] p-5 hover:border-[#333333] hover:bg-[#161616] transition-colors"
    >
      {/* Header row */}
      <div className="flex items-start justify-between gap-3 mb-2">
        <h3 className="font-semibold text-white leading-tight">{league.name}</h3>
        <LeagueStatusBadge status={league.status} />
      </div>

      {/* Meta row */}
      <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-400 mb-3">
        <span className="flex items-center gap-1">
          <DollarSign className="h-3.5 w-3.5" />
          {league.buy_in > 0 ? formatCurrency(league.buy_in) : 'Free'}
        </span>
        <span className="flex items-center gap-1">
          <Calendar className="h-3.5 w-3.5" />
          {formatDate(league.starts_at)}
        </span>
        <span className="flex items-center gap-1">
          <Users className="h-3.5 w-3.5" />
          {league.member_count} / {league.max_participants}
        </span>
      </div>

      {/* Personal stats (only shown when user is a member) */}
      {league.my_rank != null && (
        <div className="flex items-center gap-4 pt-3 border-t border-[#222222] text-sm">
          <span className="text-gray-400">
            Rank <span className="font-semibold text-white">#{league.my_rank}</span>
          </span>
          {league.my_return_pct != null && (
            <span className={`flex items-center gap-1 font-semibold ${gainLossClass(league.my_return_pct)}`}>
              <TrendingUp className="h-3.5 w-3.5" />
              {formatPct(league.my_return_pct)}
            </span>
          )}
        </div>
      )}
    </Link>
  );
}
