import { cn } from '@/lib/utils';
import type { LeagueStatus } from '@/types/api';

const STATUS_CONFIG: Record<LeagueStatus, { label: string; className: string }> = {
  upcoming: {
    label: 'Upcoming',
    className: 'bg-gray-700 text-gray-300',
  },
  active: {
    label: 'Active',
    className: 'bg-[#1B6FEB]/20 text-[#1B6FEB] border border-[#1B6FEB]/30',
  },
  finished: {
    label: 'Finished',
    className: 'bg-[#222222] text-gray-400',
  },
};

interface LeagueStatusBadgeProps {
  status: LeagueStatus;
  className?: string;
}

export function LeagueStatusBadge({ status, className }: LeagueStatusBadgeProps) {
  const config = STATUS_CONFIG[status];

  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
        config.className,
        className,
      )}
    >
      {status === 'active' && (
        <span className="mr-1.5 h-1.5 w-1.5 rounded-full bg-[#1B6FEB] animate-pulse" />
      )}
      {config.label}
    </span>
  );
}
