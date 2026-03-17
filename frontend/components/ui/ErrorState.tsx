'use client';

import { cn } from '@/lib/utils';

interface ErrorStateProps {
  message?: string;
  onRetry?: () => void;
  className?: string;
}

export function ErrorState({
  message = 'Something went wrong. Please try again.',
  onRetry,
  className,
}: ErrorStateProps) {
  return (
    <div
      className={cn(
        'flex flex-col items-center justify-center rounded-xl border border-red-900/30 bg-red-950/20 px-6 py-12 text-center',
        className,
      )}
    >
      <div className="mb-3 text-4xl">⚠️</div>
      <p className="text-sm text-red-400 max-w-xs">{message}</p>
      {onRetry && (
        <button
          onClick={onRetry}
          className="mt-4 rounded-md border border-[#333333] px-4 py-2 text-sm text-gray-300 hover:bg-[#222222] transition-colors"
        >
          Try again
        </button>
      )}
    </div>
  );
}
