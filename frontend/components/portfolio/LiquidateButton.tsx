'use client';

import { useState, useEffect, useCallback } from 'react';
import { Loader2, AlertTriangle } from 'lucide-react';
import { mutate } from 'swr';
import { liquidateAll } from '@/lib/api';
import type { Position } from '@/types/api';
import type { LiquidateResponse } from '@/types/api';

interface LiquidateButtonProps {
  leagueId: string;
  positions: Position[];
}

type LiquidateState = 'idle' | 'confirming' | 'loading' | 'success' | 'error';

export function LiquidateButton({ leagueId, positions }: LiquidateButtonProps) {
  const [state, setState] = useState<LiquidateState>('idle');
  const [result, setResult] = useState<LiquidateResponse | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const activePositions = positions.filter((p) => p.quantity > 0);

  const handleCancel = useCallback(() => {
    setState('idle');
  }, []);

  // Escape key to close confirmation
  useEffect(() => {
    if (state !== 'confirming') return;
    function handleKeyDown(e: KeyboardEvent) {
      if (e.key === 'Escape') handleCancel();
    }
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [state, handleCancel]);

  // Auto-dismiss success message
  useEffect(() => {
    if (state !== 'success') return;
    const timer = setTimeout(() => {
      setState('idle');
      setResult(null);
    }, 6000);
    return () => clearTimeout(timer);
  }, [state]);

  if (activePositions.length === 0) return null;

  async function handleConfirm() {
    setState('loading');
    setErrorMessage(null);

    try {
      const response = await liquidateAll(leagueId);
      setResult(response);
      setState('success');

      // Invalidate SWR caches for this league
      mutate(
        (key: unknown) => typeof key === 'string' && key.startsWith(`/leagues/${leagueId}/`),
        undefined,
        { revalidate: true },
      );
    } catch (err: unknown) {
      const error = err as { message?: string };
      setErrorMessage(error?.message ?? 'La liquidacion fallo. Intenta de nuevo.');
      setState('error');
    }
  }

  return (
    <>
      {/* Trigger button */}
      {state === 'idle' && (
        <button
          type="button"
          onClick={() => setState('confirming')}
          className="rounded-lg border border-red-500/40 bg-red-600/20 hover:bg-red-600/30 px-4 py-1.5 text-sm font-semibold text-red-400 hover:text-red-300 transition-colors whitespace-nowrap"
        >
          Liquidar posiciones
        </button>
      )}

      {/* Loading state (inline) */}
      {state === 'loading' && (
        <button
          type="button"
          disabled
          className="rounded-lg border border-red-500/40 bg-red-600/20 px-4 py-1.5 text-sm font-semibold text-red-400 transition-colors disabled:opacity-50 flex items-center gap-1.5 whitespace-nowrap"
        >
          <Loader2 className="h-3 w-3 animate-spin" />
          Liquidando...
        </button>
      )}

      {/* Success message */}
      {state === 'success' && result && (
        <div className="rounded-lg bg-green-900/30 border border-green-800 px-3 py-2 text-sm text-green-400">
          Liquidacion completada: {result.total_sold} posicion{result.total_sold !== 1 ? 'es' : ''} vendida{result.total_sold !== 1 ? 's' : ''}.
          {result.total_failed > 0 && (
            <span className="text-amber-400">
              {' '}{result.total_failed} posicion{result.total_failed !== 1 ? 'es' : ''} fallaron.
            </span>
          )}
        </div>
      )}

      {/* Error message */}
      {state === 'error' && (
        <div className="space-y-2">
          <div className="rounded-lg bg-red-900/30 border border-red-800 px-3 py-2 text-sm text-red-400">
            {errorMessage}
          </div>
          <button
            type="button"
            onClick={() => setState('idle')}
            className="text-sm text-gray-400 hover:text-white transition-colors"
          >
            Intentar de nuevo
          </button>
        </div>
      )}

      {/* Confirmation dialog */}
      {state === 'confirming' && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
          <div
            role="dialog"
            aria-label="Confirmar liquidacion"
            className="w-full max-w-sm rounded-xl border border-[#222222] bg-[#111111] p-6 shadow-xl"
          >
            <h3 className="text-lg font-semibold text-white mb-4">
              Liquidar todas las posiciones
            </h3>

            {/* Warning banner */}
            <div className="flex items-start gap-2 rounded-lg bg-amber-900/30 border border-amber-800 px-3 py-2 mb-4">
              <AlertTriangle className="h-4 w-4 text-amber-400 mt-0.5 shrink-0" />
              <p className="text-sm text-amber-400">
                Las ventas se ejecutan a precio de mercado actual, que puede diferir del precio del snapshot de la liga. Este proceso puede demorar hasta 10 minutos.
              </p>
            </div>

            {/* Positions list */}
            <div className="space-y-2 mb-6 max-h-48 overflow-y-auto">
              <p className="text-sm text-gray-400 mb-2">
                Se venderan {activePositions.length} posicion{activePositions.length !== 1 ? 'es' : ''}:
              </p>
              {activePositions.map((pos) => (
                <div key={pos.ticker} className="flex justify-between text-sm">
                  <span className="font-mono text-white">{pos.ticker}</span>
                  <span className="text-gray-400">
                    {pos.quantity.toLocaleString()} acciones
                  </span>
                </div>
              ))}
            </div>

            {/* Action buttons */}
            <div className="flex gap-3">
              <button
                type="button"
                onClick={handleCancel}
                className="flex-1 rounded-lg border border-[#333] px-4 py-2 text-sm text-gray-400 hover:text-white hover:border-[#555] transition-colors"
              >
                Cancelar
              </button>
              <button
                type="button"
                onClick={handleConfirm}
                className="flex-1 rounded-lg bg-amber-600 hover:bg-amber-700 px-4 py-2 text-sm font-semibold text-white transition-colors"
              >
                Confirmar liquidacion
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
