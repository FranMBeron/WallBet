'use client';

import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Cell,
} from 'recharts';
import type { ReturnBucket } from '@/types/api';

interface DistributionBarProps {
  data: ReturnBucket[];
}

interface TooltipPayloadItem {
  name: string;
  value: number;
}

function CustomTooltip({ active, payload, label }: { active?: boolean; payload?: TooltipPayloadItem[]; label?: string }) {
  if (!active || !payload?.length) return null;
  return (
    <div className="rounded-md border border-[#333333] bg-[#111111] px-3 py-2 text-sm">
      <p className="text-gray-400">{label}</p>
      <p className="font-semibold text-white">{payload[0].value} trader{payload[0].value !== 1 ? 's' : ''}</p>
    </div>
  );
}

export function DistributionBar({ data }: DistributionBarProps) {
  if (!data.length) return null;

  // Color bars: green for positive ranges (containing "+"), red for negative
  function barColor(range: string) {
    if (range.startsWith('-') || range.startsWith('< ')) return '#EF4444';
    if (range === '0%') return '#6B7280';
    return '#22C55E';
  }

  return (
    <div className="rounded-xl border border-[#222222] bg-[#111111] p-4">
      <h3 className="text-sm font-medium text-gray-400 mb-4">Return Distribution</h3>
      {/* Explicit height parent required for Recharts responsive container */}
      <div className="h-56 w-full">
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={data} margin={{ top: 4, right: 8, left: -12, bottom: 4 }}>
            <CartesianGrid strokeDasharray="3 3" stroke="#222222" vertical={false} />
            <XAxis
              dataKey="range"
              tick={{ fill: '#9CA3AF', fontSize: 11 }}
              axisLine={false}
              tickLine={false}
            />
            <YAxis
              tick={{ fill: '#9CA3AF', fontSize: 11 }}
              axisLine={false}
              tickLine={false}
              allowDecimals={false}
            />
            <Tooltip content={<CustomTooltip />} cursor={{ fill: '#1a1a1a' }} />
            <Bar dataKey="count" radius={[4, 4, 0, 0]}>
              {data.map((entry, index) => (
                <Cell key={index} fill={barColor(entry.range)} />
              ))}
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
