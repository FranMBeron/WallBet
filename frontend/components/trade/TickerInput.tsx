'use client';

import { useState, useRef } from 'react';

const SUGGESTIONS = ['AAPL', 'GOOGL', 'MSFT', 'TSLA', 'NVDA', 'AMZN', 'META'];

interface TickerInputProps {
  value: string;
  onChange: (value: string) => void;
  onBlur: () => void;
  error?: string | null;
}

export function TickerInput({ value, onChange, onBlur, error }: TickerInputProps) {
  const [showSuggestions, setShowSuggestions] = useState(false);
  const blurTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);

  const filtered = SUGGESTIONS.filter(
    (s) => s.includes(value.toUpperCase()) && value.length > 0,
  );

  function handleFocus() {
    setShowSuggestions(true);
  }

  function handleBlur() {
    blurTimeout.current = setTimeout(() => {
      setShowSuggestions(false);
      onBlur();
    }, 150);
  }

  function handleSelect(symbol: string) {
    if (blurTimeout.current) clearTimeout(blurTimeout.current);
    onChange(symbol);
    setShowSuggestions(false);
    onBlur();
  }

  return (
    <div className="relative">
      <label className="block text-sm font-medium text-gray-400 mb-1">
        Ticker
      </label>
      <input
        type="text"
        value={value}
        onChange={(e) => onChange(e.target.value.toUpperCase())}
        onFocus={handleFocus}
        onBlur={handleBlur}
        maxLength={10}
        placeholder="e.g. AAPL"
        className={`w-full bg-[#0a0a0a] border ${
          error ? 'border-red-500' : 'border-[#333]'
        } rounded-lg px-3 py-2 text-white text-sm placeholder-gray-600 focus:outline-none focus:border-[#1B6FEB] transition-colors`}
      />
      {error && <p className="text-xs text-red-400 mt-1">{error}</p>}

      {showSuggestions && filtered.length > 0 && (
        <ul className="absolute z-10 w-full mt-1 bg-[#0a0a0a] border border-[#333] rounded-lg overflow-hidden shadow-lg">
          {filtered.map((symbol) => (
            <li key={symbol}>
              <button
                type="button"
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => handleSelect(symbol)}
                className="w-full text-left px-3 py-2 text-sm text-gray-300 hover:bg-[#1a1a1a] hover:text-white transition-colors"
              >
                {symbol}
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
