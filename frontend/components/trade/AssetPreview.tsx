'use client';

import type { AssetInfo } from '@/types/api';

interface AssetPreviewProps {
  asset: AssetInfo | null;
  isLoading: boolean;
  error?: string | null;
}

export function AssetPreview({ asset, isLoading, error }: AssetPreviewProps) {
  if (error) {
    return (
      <div className="rounded-xl border border-[#222222] bg-[#111111] p-4">
        <p className="text-sm text-red-400">{error}</p>
      </div>
    );
  }

  if (isLoading) {
    return (
      <div className="rounded-xl border border-[#222222] bg-[#111111] p-4 animate-pulse">
        <div className="h-4 bg-[#222222] rounded w-1/3 mb-3" />
        <div className="h-8 bg-[#222222] rounded w-1/2 mb-2" />
        <div className="h-3 bg-[#222222] rounded w-1/4" />
      </div>
    );
  }

  if (!asset) return null;

  return (
    <div className="rounded-xl border border-[#222222] bg-[#111111] p-4">
      <div className="flex items-center gap-2 mb-1">
        <span className="text-sm font-semibold text-white">{asset.symbol}</span>
        <span className="text-sm text-gray-400">{asset.name}</span>
      </div>
      <p className="text-2xl font-bold text-white mb-2">
        ${asset.price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
      </p>
      <span className="inline-block text-xs px-2 py-0.5 rounded-full bg-[#1a1a1a] text-gray-400 border border-[#333]">
        {asset.sector}
      </span>
    </div>
  );
}
