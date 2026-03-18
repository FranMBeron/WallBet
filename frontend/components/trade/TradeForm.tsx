'use client';

import { useState, useCallback } from 'react';
import { mutate } from 'swr';
import { fetchAssetPreview, executeTrade } from '@/lib/api';
import { TickerInput } from './TickerInput';
import { AssetPreview } from './AssetPreview';
import { ConfirmDialog } from './ConfirmDialog';
import type { AssetInfo } from '@/types/api';

interface TradeFormProps {
  leagueId: string;
}

export function TradeForm({ leagueId }: TradeFormProps) {
  const [symbol, setSymbol] = useState('');
  const [direction, setDirection] = useState<'BUY' | 'SELL'>('BUY');
  const [amount, setAmount] = useState('');
  const [showConfirm, setShowConfirm] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  const [asset, setAsset] = useState<AssetInfo | null>(null);
  const [assetLoading, setAssetLoading] = useState(false);
  const [assetError, setAssetError] = useState<string | null>(null);

  const [formError, setFormError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  const handleTickerBlur = useCallback(async () => {
    if (!symbol.trim()) {
      setAsset(null);
      setAssetError(null);
      return;
    }

    setAssetLoading(true);
    setAssetError(null);
    try {
      const data = await fetchAssetPreview(leagueId, symbol.trim());
      setAsset(data);
    } catch {
      setAsset(null);
      setAssetError('Asset not found or unavailable.');
    } finally {
      setAssetLoading(false);
    }
  }, [leagueId, symbol]);

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setFormError(null);
    setSuccessMessage(null);

    if (!symbol.trim()) {
      setFormError('Please enter a ticker symbol.');
      return;
    }
    if (!amount || parseFloat(amount) <= 0) {
      setFormError('Please enter a valid amount.');
      return;
    }
    if (!asset) {
      setFormError('Please wait for asset preview to load.');
      return;
    }

    setShowConfirm(true);
  }

  async function handleConfirm() {
    setSubmitting(true);
    setFormError(null);

    try {
      await executeTrade(leagueId, {
        symbol: symbol.trim(),
        direction,
        order_type: 'MARKET',
        amount: parseFloat(amount),
      });

      // Success: clear form and show toast
      setSuccessMessage(`${direction} order executed for ${symbol}`);
      setSymbol('');
      setDirection('BUY');
      setAmount('');
      setAsset(null);
      setShowConfirm(false);

      // Invalidate SWR caches for trades and portfolio
      mutate(
        (key: unknown) => typeof key === 'string' && key.startsWith(`/leagues/${leagueId}/`),
        undefined,
        { revalidate: true },
      );

      setTimeout(() => setSuccessMessage(null), 4000);
    } catch (err: unknown) {
      const error = err as { message?: string };
      setFormError(error?.message ?? 'Trade failed. Please try again.');
      setShowConfirm(false);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div data-tour="trade-form" className="rounded-xl border border-[#222222] bg-[#111111] p-5">
      <h3 className="text-base font-semibold text-white mb-4">Place Trade</h3>

      {successMessage && (
        <div className="mb-4 rounded-lg bg-green-900/30 border border-green-800 px-3 py-2 text-sm text-green-400">
          {successMessage}
        </div>
      )}

      {formError && (
        <div className="mb-4 rounded-lg bg-red-900/30 border border-red-800 px-3 py-2 text-sm text-red-400">
          {formError}
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-4">
        {/* Ticker */}
        <TickerInput
          value={symbol}
          onChange={setSymbol}
          onBlur={handleTickerBlur}
        />

        {/* Asset Preview */}
        <AssetPreview asset={asset} isLoading={assetLoading} error={assetError} />

        {/* Direction Toggle */}
        <div>
          <label className="block text-sm font-medium text-gray-400 mb-1">
            Direction
          </label>
          <div className="flex gap-2">
            <button
              type="button"
              onClick={() => setDirection('BUY')}
              className={`flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition-colors ${
                direction === 'BUY'
                  ? 'bg-green-600 text-white'
                  : 'bg-[#0a0a0a] border border-[#333] text-gray-400 hover:text-white'
              }`}
            >
              BUY
            </button>
            <button
              type="button"
              onClick={() => setDirection('SELL')}
              className={`flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition-colors ${
                direction === 'SELL'
                  ? 'bg-red-600 text-white'
                  : 'bg-[#0a0a0a] border border-[#333] text-gray-400 hover:text-white'
              }`}
            >
              SELL
            </button>
          </div>
        </div>

        {/* Amount */}
        <div>
          <label className="block text-sm font-medium text-gray-400 mb-1">
            Amount
          </label>
          <div className="relative">
            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">
              $
            </span>
            <input
              type="number"
              min="0"
              step="0.01"
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              placeholder="0.00"
              className="w-full bg-[#0a0a0a] border border-[#333] rounded-lg pl-7 pr-3 py-2 text-white text-sm placeholder-gray-600 focus:outline-none focus:border-[#1B6FEB] transition-colors"
            />
          </div>
        </div>

        {/* Submit */}
        <button
          type="submit"
          disabled={!symbol.trim() || !amount || parseFloat(amount) < 0.01 || assetLoading || !!assetError}
          className={`w-full rounded-lg px-4 py-2.5 text-sm font-semibold text-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed ${
            direction === 'BUY'
              ? 'bg-green-600 hover:bg-green-700'
              : 'bg-red-600 hover:bg-red-700'
          }`}
        >
          Review {direction} Order
        </button>
      </form>

      {/* Confirm Dialog */}
      <ConfirmDialog
        open={showConfirm}
        symbol={symbol}
        direction={direction}
        amount={parseFloat(amount) || 0}
        price={asset?.price ?? 0}
        onConfirm={handleConfirm}
        onCancel={() => setShowConfirm(false)}
        submitting={submitting}
      />
    </div>
  );
}
