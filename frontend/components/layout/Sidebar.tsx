'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { LayoutDashboard, Trophy, LogOut } from 'lucide-react';
import { cn } from '@/lib/utils';
import { clearToken } from '@/lib/auth';

const NAV_LINKS = [
  { href: '/dashboard', label: 'Dashboard', icon: LayoutDashboard },
  { href: '/leagues',   label: 'Leagues',   icon: Trophy },
];

interface SidebarProps {
  onNavigate?: () => void;
}

export function Sidebar({ onNavigate }: SidebarProps) {
  const pathname = usePathname();
  const router = useRouter();

  function handleLogout() {
    clearToken();
    // Clear the middleware auth cookie
    document.cookie = 'wallbet_auth=; path=/; max-age=0';
    router.push('/login');
  }

  return (
    <aside className="flex h-full flex-col bg-[#111111] border-r border-[#222222]">
      {/* Logo */}
      <div className="flex h-16 items-center px-6 border-b border-[#222222]">
        <Link href="/dashboard" className="text-xl font-bold tracking-tight">
          Wall<span className="text-[#1B6FEB]">Bet</span>
        </Link>
      </div>

      {/* Nav links */}
      <nav className="flex-1 px-3 py-4 space-y-1">
        {NAV_LINKS.map(({ href, label, icon: Icon }) => {
          const isActive = pathname === href || pathname.startsWith(href + '/');
          return (
            <Link
              key={href}
              href={href}
              onClick={onNavigate}
              className={cn(
                'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                isActive
                  ? 'bg-[#1B6FEB]/10 text-[#1B6FEB]'
                  : 'text-gray-400 hover:bg-[#222222] hover:text-white',
              )}
            >
              <Icon className="h-4 w-4 flex-shrink-0" />
              {label}
            </Link>
          );
        })}
      </nav>

      {/* Logout */}
      <div className="px-3 py-4 border-t border-[#222222]">
        <button
          onClick={handleLogout}
          className="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-gray-400 hover:bg-[#222222] hover:text-white transition-colors"
        >
          <LogOut className="h-4 w-4 flex-shrink-0" />
          Log out
        </button>
      </div>
    </aside>
  );
}
