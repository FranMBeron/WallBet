'use client';

import { useEffect } from 'react';
import { Loader2 } from 'lucide-react';

interface ConfirmDialogProps {
  open: boolean;
  symbol: string;
  direction: 'BUY' | 'SELL';
  amount: number;
  price: number;
  onConfirm: () => void;
  onCancel: () => void;
  submitting: boolean;
}

export function ConfirmDialog({
  open,
  symbol,
  direction,
  amount,
  price,
  onConfirm,
  onCancel,
  submitting,
}: ConfirmDialogProps) {
  useEffect(() => {
    if (!open) return;
    function handleKeyDown(e: KeyboardEvent) {
      if (e.key === 'Escape') onCancel();
    }
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [open, onCancel]);

  if (!open) return null;

  const estimatedShares = price > 0 ? amount / price : 0;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
      <div role="dialog" aria-label="Confirm Trade" className="w-full max-w-sm rounded-xl border border-[#222222] bg-[#111111] p-6 shadow-xl">
        <h3 className="text-lg font-semibold text-white mb-4">Confirm Trade</h3>

        <div className="space-y-3 mb-6">
          <div className="flex justify-between text-sm">
            <span className="text-gray-400">Ticker</span>
            <span className="font-mono text-white">{symbol}</span>
          </div>
          <div className="flex justify-between text-sm">
            <span className="text-gray-400">Direction</span>
            <span
              className={
                direction === 'BUY' ? 'text-green-400 font-semibold' : 'text-red-400 font-semibold'
              }
            >
              {direction}
            </span>
          </div>
          <div className="flex justify-between text-sm">
            <span className="text-gray-400">Amount</span>
            <span className="text-white">
              ${amount.toLocaleString('en-US', { minimumFractionDigits: 2 })}
            </span>
          </div>
          <div className="flex justify-between text-sm">
            <span className="text-gray-400">Price</span>
            <span className="text-white">
              ${price.toLocaleString('en-US', { minimumFractionDigits: 2 })}
            </span>
          </div>
          <div className="flex justify-between text-sm border-t border-[#222222] pt-3">
            <span className="text-gray-400">Est. shares</span>
            <span className="text-white">{estimatedShares.toFixed(6)}</span>
          </div>
        </div>

        <div className="flex gap-3">
          <button
            type="button"
            onClick={onCancel}
            disabled={submitting}
            className="flex-1 rounded-lg border border-[#333] px-4 py-2 text-sm text-gray-400 hover:text-white hover:border-[#555] transition-colors disabled:opacity-50"
          >
            Cancel
          </button>
          <button
            type="button"
            onClick={onConfirm}
            disabled={submitting}
            className={`flex-1 rounded-lg px-4 py-2 text-sm font-semibold text-white transition-colors disabled:opacity-50 flex items-center justify-center gap-2 ${
              direction === 'BUY'
                ? 'bg-green-600 hover:bg-green-700'
                : 'bg-red-600 hover:bg-red-700'
            }`}
          >
            {submitting && <Loader2 className="h-4 w-4 animate-spin" />}
            {submitting ? 'Executing...' : `Confirm ${direction}`}
          </button>
        </div>
      </div>
    </div>
  );
}
