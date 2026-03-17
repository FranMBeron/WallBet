// (main) layout — authenticated app shell.
// Desktop: fixed 256px sidebar + scrollable content area.
// Mobile: sticky topbar with hamburger drawer trigger.

import 'driver.js/dist/driver.css';
import { Sidebar } from '@/components/layout/Sidebar';
import { MobileDrawer } from '@/components/layout/MobileDrawer';
import { DemoTourButton } from '@/components/demo/DemoTourButton';

export default function MainLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div className="flex h-screen bg-black overflow-hidden">
      {/* Desktop sidebar — hidden on mobile */}
      <div className="hidden md:flex md:w-64 md:flex-shrink-0 md:flex-col">
        <Sidebar />
      </div>

      {/* Main content area */}
      <div className="flex flex-1 flex-col overflow-hidden">
        {/* Mobile topbar */}
        <header className="flex h-14 items-center gap-3 border-b border-[#222222] bg-[#111111] px-4 md:hidden">
          <MobileDrawer />
          <span className="text-lg font-bold tracking-tight">
            Wall<span className="text-[#1B6FEB]">Bet</span>
          </span>
        </header>

        {/* Scrollable page content */}
        <main className="flex-1 overflow-y-auto p-4 md:p-6">
          {children}
        </main>
      </div>

      {/* Demo tour button — only renders when localStorage.is_demo === 'true' */}
      <DemoTourButton />
    </div>
  );
}
