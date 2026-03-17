'use client';

import { useState, useMemo } from 'react';
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer,
} from 'recharts';
import { formatDateShort } from '@/lib/utils';
import type { LeaderboardHistory } from '@/types/api';

const LINE_COLORS = [
  '#1B6FEB', '#22C55E', '#F59E0B', '#EF4444', '#A855F7',
  '#06B6D4', '#F97316', '#EC4899', '#14B8A6', '#64748B',
];

type Mode = 'rank' | 'return';

interface EvolutionLineProps {
  history: LeaderboardHistory;
  /** If provided, only show lines for these user IDs (used in compare view) */
  userIds?: string[];
}

export function EvolutionLine({ history, userIds }: EvolutionLineProps) {
  const [mode, setMode] = useState<Mode>('rank');

  // Derive unique users and build chart data
  const { participants, chartData } = useMemo(() => {
    if (!history.length) return { participants: [], chartData: [] };

    // Collect all unique users across history
    const userMap = new Map<string, string>();
    history.forEach((snapshot) => {
      snapshot.entries.forEach((e) => {
        if (!userMap.has(e.user.id)) {
          userMap.set(e.user.id, e.user.display_name);
        }
      });
    });

    const allParticipants = Array.from(userMap.entries()).map(([id, name]) => ({ id, name }));
    const filteredParticipants = userIds
      ? allParticipants.filter((p) => userIds.includes(p.id))
      : allParticipants;

    // Build flat chart data keyed by date
    const rows = history.map((snapshot) => {
      const row: Record<string, unknown> = { date: formatDateShort(snapshot.date) };
      snapshot.entries.forEach((e) => {
        if (!userIds || userIds.includes(e.user.id)) {
          row[e.user.id] = mode === 'rank' ? e.rank : e.return_pct;
        }
      });
      return row;
    });

    return { participants: filteredParticipants, chartData: rows };
  }, [history, userIds, mode]);

  if (!history.length) return null;

  return (
    <div className="rounded-xl border border-[#222222] bg-[#111111] p-4">
      {/* Header + mode toggle */}
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-sm font-medium text-gray-400">Ranking Evolution</h3>
        <div className="flex rounded-md overflow-hidden border border-[#333333]">
          <button
            onClick={() => setMode('rank')}
            className={`px-3 py-1 text-xs font-medium transition-colors ${
              mode === 'rank' ? 'bg-[#1B6FEB] text-white' : 'bg-transparent text-gray-400 hover:text-white'
            }`}
          >
            Rank
          </button>
          <button
            onClick={() => setMode('return')}
            className={`px-3 py-1 text-xs font-medium transition-colors ${
              mode === 'return' ? 'bg-[#1B6FEB] text-white' : 'bg-transparent text-gray-400 hover:text-white'
            }`}
          >
            Return %
          </button>
        </div>
      </div>

      <div className="h-64 w-full">
        <ResponsiveContainer width="100%" height="100%">
          <LineChart data={chartData} margin={{ top: 4, right: 8, left: -12, bottom: 4 }}>
            <CartesianGrid strokeDasharray="3 3" stroke="#222222" />
            <XAxis
              dataKey="date"
              tick={{ fill: '#9CA3AF', fontSize: 11 }}
              axisLine={false}
              tickLine={false}
            />
            <YAxis
              reversed={mode === 'rank'}   // Rank 1 should be at the top
              tick={{ fill: '#9CA3AF', fontSize: 11 }}
              axisLine={false}
              tickLine={false}
              tickFormatter={(v) => mode === 'rank' ? `#${v}` : `${v}%`}
            />
            <Tooltip
              contentStyle={{ background: '#111111', border: '1px solid #333333', borderRadius: 6 }}
              labelStyle={{ color: '#9CA3AF', fontSize: 11 }}
              itemStyle={{ color: '#FFFFFF', fontSize: 12 }}
              formatter={(value) => {
                const v = value as number;
                return mode === 'rank' ? [`#${v}`, ''] : [`${v.toFixed(2)}%`, ''];
              }}
            />
            <Legend
              formatter={(value) => {
                const p = participants.find((p) => p.id === value);
                return <span className="text-xs text-gray-300">{p?.name ?? value}</span>;
              }}
            />
            {participants.map((p, i) => (
              <Line
                key={p.id}
                type="monotone"
                dataKey={p.id}
                name={p.id}
                stroke={LINE_COLORS[i % LINE_COLORS.length]}
                strokeWidth={2}
                dot={false}
                connectNulls
              />
            ))}
          </LineChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
