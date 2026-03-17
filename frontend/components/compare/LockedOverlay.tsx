import { Lock } from 'lucide-react';
import { CountdownTimer } from './CountdownTimer';
import { formatDate } from '@/lib/utils';

interface LockedOverlayProps {
  endsAt: string; // ISO 8601
}

export function LockedOverlay({ endsAt }: LockedOverlayProps) {
  return (
    <div className="flex flex-col items-center justify-center rounded-xl border border-[#222222] bg-[#111111] py-16 px-6 text-center">
      <div className="mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-[#222222]">
        <Lock className="h-7 w-7 text-gray-500" />
      </div>

      <h3 className="text-lg font-semibold text-white mb-2">
        Compare unlocks after the league ends
      </h3>
      <p className="text-sm text-gray-400 mb-6 max-w-xs">
        Side-by-side comparison is only available once all trading is complete.
        League ends on <span className="text-white">{formatDate(endsAt)}</span>.
      </p>

      <div className="mb-2">
        <p className="text-xs text-gray-500 mb-2 uppercase tracking-widest">Time remaining</p>
        <CountdownTimer targetDate={endsAt} />
      </div>

      <div className="mt-3 flex gap-1 text-xs text-gray-600">
        <span>DD</span><span>:</span>
        <span>HH</span><span>:</span>
        <span>MM</span><span>:</span>
        <span>SS</span>
      </div>
    </div>
  );
}
