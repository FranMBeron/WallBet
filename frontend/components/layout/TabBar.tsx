'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { cn } from '@/lib/utils';

const TABS = [
  { label: 'Leaderboard', segment: 'leaderboard' },
  { label: 'Portfolio',   segment: 'portfolio' },
  { label: 'Analytics',  segment: 'analytics' },
  { label: 'Compare',    segment: 'compare' },
];

interface TabBarProps {
  leagueId: string;
}

export function TabBar({ leagueId }: TabBarProps) {
  const pathname = usePathname();

  return (
    <nav
      className="flex border-b border-[#222222] overflow-x-auto"
      aria-label="League tabs"
    >
      {TABS.map(({ label, segment }) => {
        const href = `/leagues/${leagueId}/${segment}`;
        const isActive = pathname.includes(`/${segment}`);

        return (
          <Link
            key={segment}
            href={href}
            className={cn(
              'flex-shrink-0 px-4 py-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap',
              isActive
                ? 'border-[#1B6FEB] text-white'
                : 'border-transparent text-gray-400 hover:text-white hover:border-gray-500',
            )}
          >
            {label}
          </Link>
        );
      })}
    </nav>
  );
}
