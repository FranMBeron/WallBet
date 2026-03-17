// Demo tour — single global 10-step journey using driver.js.
// Navigates across pages using Next.js router.

import type { AppRouterInstance } from 'next/dist/shared/lib/app-router-context.shared-runtime';

interface TourStep {
  route: string | null;
  element?: string;
  popover: {
    title: string;
    description: string;
    side?: 'top' | 'bottom' | 'left' | 'right';
    align?: 'start' | 'center' | 'end';
  };
}

function buildSteps(): TourStep[] {
  const activeId = process.env.NEXT_PUBLIC_DEMO_ACTIVE_LEAGUE_ID ?? '';
  const finishedId = process.env.NEXT_PUBLIC_DEMO_FINISHED_LEAGUE_ID ?? '';

  return [
    {
      route: '/dashboard',
      element: '[data-tour="dashboard-leagues"]',
      popover: {
        title: 'Tus Ligas',
        description: 'Acá ves todas las ligas en las que participás.',
        side: 'bottom',
      },
    },
    {
      route: `/leagues/${activeId}/leaderboard`,
      element: '[data-tour="leaderboard-table"]',
      popover: {
        title: 'Tabla de Posiciones',
        description: 'Rankings en tiempo real de todos los participantes.',
        side: 'top',
      },
    },
    {
      route: `/leagues/${activeId}/portfolio`,
      element: '[data-tour="portfolio-hidden"]',
      popover: {
        title: 'Portfolio Oculto',
        description: 'Durante la liga, los portfolios de otros jugadores están ocultos.',
        side: 'bottom',
      },
    },
    {
      route: `/leagues/${activeId}/analytics`,
      element: '[data-tour="analytics-tickers"]',
      popover: {
        title: 'Análisis por Ticker',
        description: 'Métricas detalladas de cada ticker operado.',
        side: 'bottom',
      },
    },
    {
      route: `/leagues/${finishedId}/leaderboard`,
      element: '[data-tour="leaderboard-table"]',
      popover: {
        title: 'Liga Finalizada',
        description: 'Rankings finales con métricas completas.',
        side: 'top',
      },
    },
    {
      route: `/leagues/${finishedId}/portfolio`,
      element: '[data-tour="portfolio-positions"]',
      popover: {
        title: 'Posiciones Finales',
        description: 'Una vez finalizada la liga, todos los portfolios son visibles.',
        side: 'top',
      },
    },
    {
      route: `/leagues/${finishedId}/compare`,
      element: '[data-tour="compare-chart"]',
      popover: {
        title: 'Comparar Jugadores',
        description: 'Compará la evolución de distintos jugadores.',
        side: 'left',
        align: 'start',
      },
    },
    {
      route: '/leagues/new',
      element: '[data-tour="create-league-form"]',
      popover: {
        title: 'Crear Liga',
        description: 'Creá tu propia liga e invitá a tus amigos.',
        side: 'top',
      },
    },
    {
      route: '/connect-wallbit',
      element: '[data-tour="wallbit-connect"]',
      popover: {
        title: 'Conectar Wallbit',
        description: 'Conectá tu cuenta de Wallbit para participar.',
        side: 'bottom',
      },
    },
    {
      route: null,
      popover: {
        title: '¡Listo!',
        description: 'Ya conocés WallBet. ¡Creá tu liga y empezá a competir!',
      },
    },
  ];
}

export async function startTour(router: AppRouterInstance) {
  const tourSteps = buildSteps();

  const { driver } = await import('driver.js');

  const driverSteps = tourSteps.map((step) => {
    const driverStep: Record<string, unknown> = {
      popover: {
        ...step.popover,
      },
    };
    if (step.element) {
      driverStep.element = step.element;
    }
    return driverStep;
  });

  /**
   * Wait for an element to appear in the DOM (polls every 100ms, up to 3s).
   * Falls back to calling `then()` even if the element never appears so the
   * tour doesn't get stuck — driver.js will simply show the popover un-anchored.
   */
  function waitForElement(selector: string | undefined, then: () => void) {
    if (!selector) {
      then();
      return;
    }
    let elapsed = 0;
    const interval = 100;
    const maxWait = 5000;
    const poll = setInterval(() => {
      elapsed += interval;
      const el = document.querySelector(selector);
      if (el || elapsed >= maxWait) {
        clearInterval(poll);
        if (el) {
          el.scrollIntoView({ behavior: 'smooth', block: 'center' });
          // Allow scroll animation to settle before highlighting
          setTimeout(then, 400);
        } else {
          then();
        }
      }
    }, interval);
  }

  function navigateIfNeeded(
    targetIndex: number,
    then: () => void,
  ) {
    const targetStep = tourSteps[targetIndex];
    if (!targetStep) return;

    const targetRoute = targetStep.route;
    if (!targetRoute || window.location.pathname === targetRoute) {
      waitForElement(targetStep.element, then);
      return;
    }

    router.push(targetRoute);
    // Wait for route change + element to appear in DOM
    setTimeout(() => {
      waitForElement(targetStep.element, then);
    }, 300);
  }

  const driverObj = driver({
    showProgress: false,
    nextBtnText: 'Siguiente →',
    prevBtnText: '← Anterior',
    doneBtnText: '¡Listo!',
    popoverClass: 'wallbet-tour',
    allowClose: true,
    overlayColor: 'black',
    overlayOpacity: 0.65,
    stagePadding: 10,
    stageRadius: 8,
    animate: true,
    steps: driverSteps as import('driver.js').DriveStep[],
    onNextClick: () => {
      const currentIndex = driverObj.getActiveIndex() ?? 0;
      const nextIndex = currentIndex + 1;
      if (nextIndex >= tourSteps.length) {
        driverObj.destroy();
        return;
      }
      navigateIfNeeded(nextIndex, () => driverObj.moveNext());
    },
    onPrevClick: () => {
      const currentIndex = driverObj.getActiveIndex() ?? 0;
      const prevIndex = currentIndex - 1;
      if (prevIndex < 0) return;
      navigateIfNeeded(prevIndex, () => driverObj.movePrevious());
    },
    onDestroyed: () => {
      sessionStorage.removeItem('wallbet_demo_tour');
    },
  });

  // Navigate to the first step's route if needed
  const firstRoute = tourSteps[0]?.route;
  if (firstRoute && window.location.pathname !== firstRoute) {
    router.push(firstRoute);
    setTimeout(() => {
      waitForElement(tourSteps[0]?.element, () => driverObj.drive());
    }, 300);
  } else {
    waitForElement(tourSteps[0]?.element, () => driverObj.drive());
  }
}
