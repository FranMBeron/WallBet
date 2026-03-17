import { Users, DollarSign, Calendar } from 'lucide-react';
import { LeagueStatusBadge } from '@/components/leagues/LeagueStatusBadge';
import { formatCurrency, formatDate } from '@/lib/utils';
import type { League } from '@/types/api';

interface LeagueHeaderProps {
  league: League;
}

export function LeagueHeader({ league }: LeagueHeaderProps) {
  return (
    <div className="mb-4">
      <div className="flex flex-wrap items-center gap-3 mb-1">
        <h1 className="text-xl font-bold text-white">{league.name}</h1>
        <LeagueStatusBadge status={league.status} />
      </div>

      {league.description && (
        <p className="text-sm text-gray-400 mb-2">{league.description}</p>
      )}

      <div className="flex flex-wrap gap-4 text-sm text-gray-400">
        <span className="flex items-center gap-1.5">
          <DollarSign className="h-3.5 w-3.5" />
          {league.buy_in > 0 ? formatCurrency(league.buy_in) : 'Free'}
        </span>
        <span className="flex items-center gap-1.5">
          <Calendar className="h-3.5 w-3.5" />
          {formatDate(league.starts_at)} – {formatDate(league.ends_at)}
        </span>
        <span className="flex items-center gap-1.5">
          <Users className="h-3.5 w-3.5" />
          {league.participant_count} / {league.max_participants} participants
        </span>
      </div>
    </div>
  );
}
