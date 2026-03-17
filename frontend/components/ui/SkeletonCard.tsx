import { cn } from '@/lib/utils';

interface SkeletonProps {
  className?: string;
}

// Single shimmer bar
export function Skeleton({ className }: SkeletonProps) {
  return (
    <div
      className={cn(
        'animate-pulse rounded-md bg-[#222222]',
        className,
      )}
    />
  );
}

// League card skeleton — matches the shape of LeagueCard
export function SkeletonCard() {
  return (
    <div className="rounded-xl border border-[#222222] bg-[#111111] p-5 space-y-3">
      <div className="flex items-center justify-between">
        <Skeleton className="h-5 w-40" />
        <Skeleton className="h-5 w-16 rounded-full" />
      </div>
      <Skeleton className="h-4 w-56" />
      <div className="flex gap-4 pt-1">
        <Skeleton className="h-4 w-20" />
        <Skeleton className="h-4 w-20" />
        <Skeleton className="h-4 w-20" />
      </div>
    </div>
  );
}

// Generic row skeleton (for tables)
export function SkeletonRow({ cols = 5 }: { cols?: number }) {
  return (
    <tr>
      {Array.from({ length: cols }).map((_, i) => (
        <td key={i} className="px-3 py-3">
          <Skeleton className="h-4 w-full" />
        </td>
      ))}
    </tr>
  );
}
