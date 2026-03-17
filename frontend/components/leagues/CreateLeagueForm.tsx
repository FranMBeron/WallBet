'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { apiMutate } from '@/lib/api';
import type { League, ApiValidationError } from '@/types/api';

interface FormState {
  name: string;
  description: string;
  type: 'sponsored' | 'private';
  buy_in: string;
  max_participants: string;
  starts_at: string;
  ends_at: string;
  is_public: boolean;
  password: string;
}

const INITIAL: FormState = {
  name: '',
  description: '',
  type: 'private',
  buy_in: '30',
  max_participants: '20',
  starts_at: '',
  ends_at: '',
  is_public: true,
  password: '',
};

interface FieldErrors {
  [key: string]: string[] | undefined;
}

// Input component to keep JSX clean
function Field({
  label,
  id,
  children,
  error,
}: {
  label: string;
  id: string;
  children: React.ReactNode;
  error?: string;
}) {
  return (
    <div>
      <label htmlFor={id} className="block text-sm font-medium text-gray-300 mb-1">
        {label}
      </label>
      {children}
      {error && <p className="mt-1 text-xs text-red-400">{error}</p>}
    </div>
  );
}

const inputCls =
  'w-full rounded-md border border-[#333333] bg-black px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-[#1B6FEB] focus:outline-none focus:ring-1 focus:ring-[#1B6FEB]';

export function CreateLeagueForm() {
  const router = useRouter();
  const [form, setForm] = useState<FormState>(INITIAL);
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [globalError, setGlobalError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  function set<K extends keyof FormState>(key: K, value: FormState[K]) {
    setForm((prev) => ({ ...prev, [key]: value }));
    setFieldErrors((prev) => ({ ...prev, [key]: undefined }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setGlobalError(null);
    setFieldErrors({});

    if (Number(form.buy_in) < 30) {
      setFieldErrors({ buy_in: ['Minimum buy-in is $30'] });
      return;
    }

    setLoading(true);

    const payload = {
      name: form.name,
      description: form.description || undefined,
      type: form.type,
      buy_in: Number(form.buy_in),
      max_participants: Number(form.max_participants),
      starts_at: form.starts_at,
      ends_at: form.ends_at,
      is_public: form.is_public,
      password: !form.is_public ? form.password : undefined,
    };

    try {
      const league = await apiMutate<League>('/leagues', 'POST', payload);
      router.push(`/leagues/${league.id}`);
    } catch (err: unknown) {
      const apiErr = err as ApiValidationError;
      if (apiErr?.errors) {
        setFieldErrors(apiErr.errors);
      } else {
        setGlobalError(apiErr?.message ?? 'Failed to create league.');
      }
    } finally {
      setLoading(false);
    }
  }

  function fe(field: string): string | undefined {
    return (fieldErrors[field] ?? [])[0];
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-5">
      <Field label="League name" id="name" error={fe('name')}>
        <input
          id="name" type="text" required value={form.name}
          onChange={(e) => set('name', e.target.value)}
          className={inputCls} placeholder="My Trading League"
        />
      </Field>

      <Field label="Description (optional)" id="description" error={fe('description')}>
        <textarea
          id="description" rows={2} value={form.description}
          onChange={(e) => set('description', e.target.value)}
          className={`${inputCls} resize-none`}
          placeholder="A short description of this league"
        />
      </Field>

      <div className="grid grid-cols-2 gap-4">
        <Field label="Buy-in (USD)" id="buy_in" error={fe('buy_in')}>
          <input
            id="buy_in" type="number" min="30" step="1" required
            value={form.buy_in}
            onChange={(e) => set('buy_in', e.target.value)}
            className={inputCls}
          />
        </Field>

        <Field label="Max participants" id="max_participants" error={fe('max_participants')}>
          <input
            id="max_participants" type="number" min="2" max="500" required
            value={form.max_participants}
            onChange={(e) => set('max_participants', e.target.value)}
            className={inputCls}
          />
        </Field>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <Field label="Starts at" id="starts_at" error={fe('starts_at')}>
          <input
            id="starts_at" type="datetime-local" required
            value={form.starts_at}
            onChange={(e) => set('starts_at', e.target.value)}
            className={inputCls}
          />
        </Field>

        <Field label="Ends at" id="ends_at" error={fe('ends_at')}>
          <input
            id="ends_at" type="datetime-local" required
            value={form.ends_at}
            onChange={(e) => set('ends_at', e.target.value)}
            className={inputCls}
          />
        </Field>
      </div>

      {/* Visibility toggle */}
      <div className="flex items-center gap-3">
        <button
          type="button"
          role="switch"
          aria-checked={form.is_public}
          onClick={() => {
            setForm((prev) => ({ ...prev, is_public: !prev.is_public }));
            setFieldErrors((prev) => ({ ...prev, is_public: undefined }));
          }}
          className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full overflow-hidden transition-colors duration-200 focus:outline-none ${
            form.is_public ? 'bg-[#1B6FEB]' : 'bg-gray-600'
          }`}
        >
          <span
            className={`absolute left-0 top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition-transform duration-200 ${
              form.is_public ? 'translate-x-[22px]' : 'translate-x-0.5'
            }`}
          />
        </button>
        <span className="text-sm text-gray-300">
          {form.is_public ? 'Public league' : 'Private league'}
        </span>
      </div>

      {/* Password — only when private */}
      {!form.is_public && (
        <Field label="League password" id="password" error={fe('password')}>
          <input
            id="password" type="text" required={!form.is_public}
            value={form.password}
            onChange={(e) => set('password', e.target.value)}
            className={inputCls} placeholder="Entry password for members"
          />
        </Field>
      )}

      {globalError && (
        <p className="rounded-md bg-red-950/40 border border-red-800/40 px-3 py-2 text-sm text-red-400">
          {globalError}
        </p>
      )}

      <button
        type="submit"
        disabled={loading}
        className="w-full rounded-md bg-[#1B6FEB] px-4 py-2 text-sm font-semibold text-white hover:bg-[#1559c9] disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
      >
        {loading ? 'Creating…' : 'Create League'}
      </button>
    </form>
  );
}
