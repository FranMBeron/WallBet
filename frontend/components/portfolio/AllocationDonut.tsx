'use client';

import { PieChart, Pie, Cell, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import type { Position } from '@/types/api';

// Distinct palette for up to 12 slices; repeats for more
const COLORS = [
  '#1B6FEB', '#22C55E', '#F59E0B', '#EF4444', '#A855F7',
  '#06B6D4', '#F97316', '#84CC16', '#EC4899', '#14B8A6',
  '#8B5CF6', '#64748B',
];

interface AllocationDonutProps {
  positions: Position[];
}

interface TooltipPayloadItem {
  name: string;
  value: number;
}

function CustomTooltip({ active, payload }: { active?: boolean; payload?: TooltipPayloadItem[] }) {
  if (!active || !payload?.length) return null;
  const { name, value } = payload[0];
  return (
    <div className="rounded-md border border-[#333333] bg-[#111111] px-3 py-2 text-sm">
      <p className="font-semibold text-white">{name}</p>
      <p className="text-gray-400">{value.toFixed(1)}% weight</p>
    </div>
  );
}

export function AllocationDonut({ positions }: AllocationDonutProps) {
  if (!positions.length) return null;

  const data = positions.map((p) => ({
    name: p.ticker,
    value: p.weight_pct,
  }));

  return (
    // Explicit height parent required to avoid Recharts SSR hydration mismatch
    <div className="h-64 w-full">
      <ResponsiveContainer width="100%" height="100%">
        <PieChart>
          <Pie
            data={data}
            dataKey="value"
            nameKey="name"
            cx="50%"
            cy="50%"
            innerRadius="55%"
            outerRadius="75%"
            paddingAngle={2}
          >
            {data.map((_, index) => (
              <Cell key={index} fill={COLORS[index % COLORS.length]} />
            ))}
          </Pie>
          <Tooltip content={<CustomTooltip />} />
          <Legend
            formatter={(value) => (
              <span className="text-xs text-gray-300">{value}</span>
            )}
          />
        </PieChart>
      </ResponsiveContainer>
    </div>
  );
}
