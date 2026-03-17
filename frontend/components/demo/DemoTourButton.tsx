'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { startTour } from '@/lib/demo-tour';

export function DemoTourButton() {
  const [isDemo, setIsDemo] = useState(false);
  const router = useRouter();

  useEffect(() => {
    setIsDemo(localStorage.getItem('is_demo') === 'true');
  }, []);

  // Auto-start the tour once when the demo user first lands on the page.
  useEffect(() => {
    if (!isDemo) return;
    if (sessionStorage.getItem('wallbet_demo_tour') !== '1') return;

    const timer = setTimeout(() => {
      startTour(router);
    }, 800);

    return () => clearTimeout(timer);
  }, [isDemo, router]);

  if (!isDemo) return null;

  async function handleStartTour() {
    await startTour(router);
  }

  return (
    <div className="fixed bottom-6 right-6 z-50 flex items-center gap-2">
      <button
        onClick={handleStartTour}
        className="flex items-center gap-2 rounded-full bg-[#1B6FEB] px-4 py-2 text-sm font-medium text-white shadow-lg transition-colors hover:bg-[#1558c7]"
      >
        <span>▶</span>
        <span>Guía del Producto</span>
      </button>
    </div>
  );
}
