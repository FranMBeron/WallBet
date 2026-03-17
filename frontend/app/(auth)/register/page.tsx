'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { apiMutate } from '@/lib/api';
import { setToken } from '@/lib/auth';
import type { RegisterResponse, ApiValidationError } from '@/types/api';

interface FieldErrors {
  email?: string[];
  username?: string[];
  name?: string[];
  password?: string[];
}

export default function RegisterPage() {
  const router = useRouter();
  const [form, setForm] = useState({
    email: '',
    username: '',
    name: '',
    password: '',
    password_confirmation: '',
  });
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [globalError, setGlobalError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  function update(field: keyof typeof form) {
    return (e: React.ChangeEvent<HTMLInputElement>) => {
      setForm((prev) => ({ ...prev, [field]: e.target.value }));
      // Clear field error on change
      setFieldErrors((prev) => ({ ...prev, [field]: undefined }));
    };
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setGlobalError(null);
    setFieldErrors({});
    setLoading(true);

    try {
      const res = await apiMutate<RegisterResponse>('/auth/register', 'POST', form);
      setToken(res.token);
      document.cookie = 'wallbet_auth=1; path=/; max-age=86400';
      router.push('/connect-wallbit');
    } catch (err: unknown) {
      const apiErr = err as ApiValidationError;
      if (apiErr?.errors) {
        setFieldErrors(apiErr.errors as FieldErrors);
      } else {
        setGlobalError(apiErr?.message ?? 'Registration failed. Please try again.');
      }
    } finally {
      setLoading(false);
    }
  }

  function fieldError(field: keyof FieldErrors) {
    const errs = fieldErrors[field];
    if (!errs?.length) return null;
    return (
      <p className="mt-1 text-xs text-red-400">{errs[0]}</p>
    );
  }

  return (
    <div className="rounded-xl border border-[#222222] bg-[#111111] p-8">
      <h2 className="mb-6 text-2xl font-bold text-white">Create account</h2>

      <form onSubmit={handleSubmit} className="space-y-4">
        <div>
          <label htmlFor="email" className="block text-sm font-medium text-gray-300 mb-1">Email</label>
          <input
            id="email" type="email" autoComplete="email" required
            value={form.email} onChange={update('email')}
            className="w-full rounded-md border border-[#333333] bg-black px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-[#1B6FEB] focus:outline-none focus:ring-1 focus:ring-[#1B6FEB]"
            placeholder="you@example.com"
          />
          {fieldError('email')}
        </div>

        <div>
          <label htmlFor="username" className="block text-sm font-medium text-gray-300 mb-1">Username</label>
          <input
            id="username" type="text" autoComplete="username" required
            value={form.username} onChange={update('username')}
            className="w-full rounded-md border border-[#333333] bg-black px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-[#1B6FEB] focus:outline-none focus:ring-1 focus:ring-[#1B6FEB]"
            placeholder="trader123"
          />
          {fieldError('username')}
        </div>

        <div>
          <label htmlFor="name" className="block text-sm font-medium text-gray-300 mb-1">Display name</label>
          <input
            id="name" type="text" required
            value={form.name} onChange={update('name')}
            className="w-full rounded-md border border-[#333333] bg-black px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-[#1B6FEB] focus:outline-none focus:ring-1 focus:ring-[#1B6FEB]"
            placeholder="Your Name"
          />
          {fieldError('name')}
        </div>

        <div>
          <label htmlFor="password" className="block text-sm font-medium text-gray-300 mb-1">Password</label>
          <input
            id="password" type="password" autoComplete="new-password" required
            value={form.password} onChange={update('password')}
            className="w-full rounded-md border border-[#333333] bg-black px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-[#1B6FEB] focus:outline-none focus:ring-1 focus:ring-[#1B6FEB]"
            placeholder="••••••••"
          />
          {fieldError('password')}
        </div>

        <div>
          <label htmlFor="password_confirmation" className="block text-sm font-medium text-gray-300 mb-1">Confirm password</label>
          <input
            id="password_confirmation" type="password" autoComplete="new-password" required
            value={form.password_confirmation} onChange={update('password_confirmation')}
            className="w-full rounded-md border border-[#333333] bg-black px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-[#1B6FEB] focus:outline-none focus:ring-1 focus:ring-[#1B6FEB]"
            placeholder="••••••••"
          />
        </div>

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
          {loading ? 'Creating account…' : 'Create account'}
        </button>
      </form>

      <p className="mt-6 text-center text-sm text-gray-400">
        Already have an account?{' '}
        <Link href="/login" className="text-[#1B6FEB] hover:underline">Sign in</Link>
      </p>
    </div>
  );
}
