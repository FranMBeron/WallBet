'use client';

import { useState, useEffect } from 'react';

interface CountdownTimerProps {
  targetDate: string; // ISO 8601
}

interface TimeLeft {
  days: number;
  hours: number;
  minutes: number;
  seconds: number;
  expired: boolean;
}

function calcTimeLeft(target: string): TimeLeft {
  const diff = new Date(target).getTime() - Date.now();
  if (diff <= 0) return { days: 0, hours: 0, minutes: 0, seconds: 0, expired: true };

  const totalSeconds = Math.floor(diff / 1000);
  const days = Math.floor(totalSeconds / 86400);
  const hours = Math.floor((totalSeconds % 86400) / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const seconds = totalSeconds % 60;

  return { days, hours, minutes, seconds, expired: false };
}

function pad(n: number) {
  return String(n).padStart(2, '0');
}

export function CountdownTimer({ targetDate }: CountdownTimerProps) {
  const [timeLeft, setTimeLeft] = useState<TimeLeft>(() => calcTimeLeft(targetDate));

  useEffect(() => {
    const interval = setInterval(() => {
      setTimeLeft(calcTimeLeft(targetDate));
    }, 1000);
    return () => clearInterval(interval);
  }, [targetDate]);

  if (timeLeft.expired) {
    return <span className="text-gray-400 text-sm">League has ended</span>;
  }

  return (
    <div className="flex items-center gap-2 font-mono text-2xl font-bold text-white">
      <span>{pad(timeLeft.days)}</span>
      <span className="text-gray-600">:</span>
      <span>{pad(timeLeft.hours)}</span>
      <span className="text-gray-600">:</span>
      <span>{pad(timeLeft.minutes)}</span>
      <span className="text-gray-600">:</span>
      <span>{pad(timeLeft.seconds)}</span>
    </div>
  );
}
