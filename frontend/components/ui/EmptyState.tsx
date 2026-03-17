import Link from 'next/link';
import { cn } from '@/lib/utils';

interface EmptyStateProps {
  title: string;
  description?: string;
  ctaLabel?: string;
  ctaHref?: string;
  className?: string;
}

export function EmptyState({
  title,
  description,
  ctaLabel,
  ctaHref,
  className,
}: EmptyStateProps) {
  return (
    <div
      className={cn(
        'flex flex-col items-center justify-center rounded-xl border border-[#222222] bg-[#111111] px-6 py-16 text-center',
        className,
      )}
    >
      <div className="mb-3 text-4xl">📭</div>
      <h3 className="text-base font-semibold text-white mb-1">{title}</h3>
      {description && (
        <p className="text-sm text-gray-400 max-w-xs">{description}</p>
      )}
      {ctaLabel && ctaHref && (
        <Link
          href={ctaHref}
          className="mt-5 inline-flex items-center rounded-md bg-[#1B6FEB] px-4 py-2 text-sm font-medium text-white hover:bg-[#1559c9] transition-colors"
        >
          {ctaLabel}
        </Link>
      )}
    </div>
  );
}
