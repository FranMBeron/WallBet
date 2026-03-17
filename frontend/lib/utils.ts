import { clsx, type ClassValue } from "clsx"
import { twMerge } from "tailwind-merge"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

// ----------------------------------------------------------------
// Formatting helpers
// ----------------------------------------------------------------

/**
 * Format a number as USD currency.
 * e.g. 1234.5 → "$1,234.50"
 */
export function formatCurrency(value: number | null | undefined, decimals = 2): string {
  if (value == null) return '—';
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(value);
}

/**
 * Format a decimal number as a percentage string.
 * e.g. 12.345 → "+12.35%"  |  -5.1 → "-5.10%"
 */
export function formatPct(value: number | null | undefined, decimals = 2, showSign = true): string {
  if (value == null) return '—';
  const sign = showSign && value > 0 ? '+' : '';
  return `${sign}${value.toFixed(decimals)}%`;
}

/**
 * Format an ISO date string as a human-readable date.
 * e.g. "2025-06-01T00:00:00Z" → "Jun 1, 2025"
 */
export function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

/**
 * Format an ISO date string as a short date (no year).
 * e.g. "2025-06-01T00:00:00Z" → "Jun 1"
 */
export function formatDateShort(iso: string): string {
  return new Date(iso).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
  });
}

/**
 * Return the CSS class for a gain/loss value.
 */
export function gainLossClass(value: number | null | undefined): string {
  if (value == null) return 'text-muted-foreground';
  if (value > 0) return 'text-gain';
  if (value < 0) return 'text-loss';
  return 'text-muted-foreground';
}

