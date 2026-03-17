'use client';

import { useState } from 'react';
import { Users, DollarSign, Calendar, Link2, Check } from 'lucide-react';
import { LeagueStatusBadge } from '@/components/leagues/LeagueStatusBadge';
import { formatCurrency, formatDate } from '@/lib/utils';
import { useAuth } from '@/lib/hooks/useAuth';
import type { League } from '@/types/api';

interface LeagueHeaderProps {
  league: League;
}

export function LeagueHeader({ league }: LeagueHeaderProps) {
  const { user } = useAuth();
  const [copied, setCopied] = useState(false);

  const isCreator = !!user && !!league.created_by && user.id === league.created_by;
  const showInviteButton = isCreator && !!league.invite_code;

  function handleCopyInvite() {
    const url = `${window.location.origin}/join/${league.invite_code}`;
    navigator.clipboard.writeText(url).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    });
  }

  return (
    <div className="mb-4">
      <div className="flex flex-wrap items-center justify-between gap-3 mb-1">
        <div className="flex flex-wrap items-center gap-3">
          <h1 className="text-xl font-bold text-white">{league.name}</h1>
          <LeagueStatusBadge status={league.status} />
        </div>

        {showInviteButton && (
          <button
            onClick={handleCopyInvite}
            className={`flex items-center gap-1.5 rounded px-2.5 py-1 text-xs border transition-colors ${
              copied
                ? 'border-green-700 text-green-400'
                : 'border-[#333] text-gray-400 hover:text-white hover:border-[#555]'
            }`}
          >
            {copied ? (
              <>
                <Check className="h-3.5 w-3.5" />
                Copied!
              </>
            ) : (
              <>
                <Link2 className="h-3.5 w-3.5" />
                Copy invite link
              </>
            )}
          </button>
        )}
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
          {league.member_count} / {league.max_participants} participants
        </span>
      </div>
    </div>
  );
}
