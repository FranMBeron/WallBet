# WallBet

**Stock trading leagues where friends compete with real market data.**

![Next.js](https://img.shields.io/badge/Next.js_14-black?style=flat-square&logo=next.js)
![Laravel](https://img.shields.io/badge/Laravel_11-FF2D20?style=flat-square&logo=laravel&logoColor=white)
![TypeScript](https://img.shields.io/badge/TypeScript-3178C6?style=flat-square&logo=typescript&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white)
![PHP](https://img.shields.io/badge/PHP_8.3-777BB4?style=flat-square&logo=php&logoColor=white)
![Playwright](https://img.shields.io/badge/Playwright-2EAD33?style=flat-square&logo=playwright&logoColor=white)

---

## What is WallBet?

WallBet brings the thrill of fantasy sports to the real stock market. Users create or join leagues, invite friends with a simple code, and compete by building the best-performing portfolio using real market data. Think fantasy football, but with real stocks.

Each league runs for a defined period. During that time, members buy and sell assets, track their portfolio performance, and climb the leaderboard. The app provides analytics, portfolio evolution charts, and head-to-head comparisons so players can see exactly how they stack up against the competition.

WallBet also ships with a fully functional demo mode, complete with mock market data, pre-seeded leagues, and a guided tour. This makes it easy to explore every feature without needing API keys or a live trading account.

---

## Features

- **League Management** - Create leagues, join via invite code, support for public and private (password-protected) leagues
- **Real-Time Trading** - Buy and sell assets during active leagues with live price previews powered by the Wallbit API
- **Portfolio Tracking** - View positions, current value, and gain/loss for each league member
- **Leaderboard** - Rank all league members by portfolio performance with historical snapshots
- **Analytics** - Portfolio evolution charts and return metrics (daily, total, percentage-based)
- **Head-to-Head Comparison** - Compare portfolio performance between any two league members side by side
- **Trade History** - Full trade log per user within each league
- **Demo Mode with Guided Tour** - Explore the entire app with mock data and a step-by-step Driver.js tour (11 steps across multiple pages)

---

## Platform Rules

WallBet enforces a set of rules to keep leagues fair, competitive, and secure.

### :trophy: League Lifecycle
- Leagues start as **Upcoming**, transition to **Active** when the start date arrives, and become **Finished** when the end date passes (automated every 5 minutes)
- Users can only join leagues while they are in **Upcoming** status
- The league creator is automatically enrolled as a member upon creation
- The league creator cannot leave their own league

### :ticket: Joining a League
- Users must have a connected Wallbit account with sufficient balance to cover the buy-in (always equal or over $30 USD)
- A league cannot exceed its maximum participant limit
- Users cannot join a league they are already a member of
- Private leagues require the correct password to join

### :chart_with_upwards_trend: Trading
- Trades can only be executed in **Active** leagues (returns 403 otherwise)
- Only league members can trade
- Trades are executed on the Wallbit API first; only on success is the trade logged locally
- If the external API rejects the trade, no local record is created
- Each user can only see their own trade history

### :eye: Portfolio Visibility
- Users can always view their own portfolio
- Other members' portfolios are **hidden** while the league is active (no peeking at competitors!)
- Portfolios become visible to all members once the league finishes

### :bar_chart: Analytics & Comparison
- The head-to-head comparison feature is **blocked** during active leagues (returns 403)
- Comparison is only available after the league ends
- Top traded tickers are hidden during active leagues and revealed after the league finishes
- Analytics never call the external Wallbit API; they read only from stored snapshots

### :lock: Privacy & Security
- League passwords are bcrypt-hashed before storage (never stored in plaintext)
- The password field is never exposed in API responses
- Private leagues do not appear in the public directory
- Invite codes are only visible to league members

---

## Tech Stack

| Layer | Technologies |
|-------|-------------|
| **Frontend** | Next.js 14 (App Router), TypeScript, Tailwind CSS, SWR, Recharts, shadcn/ui, Driver.js |
| **Backend** | Laravel 11, PHP 8.3, Sanctum authentication |
| **Database** | MySQL / SQLite |
| **Testing** | Playwright E2E (7 spec files across auth, dashboard, leagues, leaderboard, portfolio, analytics, trading) |
| **External API** | Wallbit (real market data, mocked in demo mode) |

---

## Architecture

```
frontend/                         backend/
├── app/                          ├── app/
│   ├── (auth)/                   │   ├── Http/Controllers/
│   │   ├── login/                │   │   ├── AuthController
│   │   └── connect-wallbit/      │   │   ├── LeagueController
│   ├── (main)/                   │   │   ├── TradeController
│   │   ├── dashboard/            │   │   ├── PortfolioController
│   │   ├── leagues/              │   │   ├── LeaderboardController
│   │   │   ├── [id]/             │   │   ├── AnalyticsController
│   │   │   │   ├── portfolio/    │   │   └── CompareController
│   │   │   │   ├── leaderboard/  │   ├── Services/
│   │   │   │   ├── analytics/    │   │   ├── PortfolioService
│   │   │   │   └── compare/      │   │   └── WallbitClient
│   │   │   └── new/              │   └── ...
│   │   └── join/[code]/          ├── routes/
│   └── globals.css               │   └── api.php
├── components/                   └── database/
├── lib/                              ├── migrations/
│   ├── api.ts                        └── seeders/
│   ├── auth.ts
│   ├── demo-tour.ts
│   └── utils.ts
└── types/
```

**Key architectural decisions:**

- **Route Groups** - Next.js App Router with `(auth)` and `(main)` route groups for layout separation
- **Sanctum Auth** - Token-based authentication with CSRF protection for SPA sessions
- **Service Layer** - Business logic encapsulated in `PortfolioService` and `WallbitClient`, keeping controllers thin
- **SWR** - Client-side data fetching with automatic revalidation and caching
- **Middleware Guards** - Laravel middleware (`league.member`, `wallbit.connected`) to enforce access control at the route level

---

## Demo Mode

Demo mode lets you explore WallBet without any external API keys or live market connections. When enabled, the app:

- **Mocks asset prices** with realistic jitter so charts and values look authentic
- **Pre-seeds the database** with leagues (active and finished), users, portfolios, and trade history
- **Provides one-click login** so you can jump straight in without registration
- **Runs a guided tour** using Driver.js that walks through 11 steps across the dashboard, leagues, portfolio, leaderboard, analytics, and comparison pages
- **Resets nightly** via the `php artisan demo:reset` command to keep data fresh

To activate demo mode, set `APP_DEMO_MODE=true` in the backend `.env` file and configure the demo league IDs in the frontend `.env`.

---

## Getting Started

### Prerequisites

- Node.js 18+
- PHP 8.3+
- Composer
- MySQL or SQLite

### Backend Setup

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

### Frontend Setup

```bash
cd frontend
cp .env.example .env.local
# Edit .env.local to set NEXT_PUBLIC_API_URL (default: http://localhost:8000)
npm install
npm run dev
```

### Activating Demo Mode

1. In `backend/.env`, set `APP_DEMO_MODE=true`
2. Run `php artisan migrate:fresh --seed` to load demo data
3. In `frontend/.env.local`, set the demo league environment variables (see `.env.example`)
4. Visit the app and use the one-click demo login on the login page

---

## API Endpoints

All endpoints are prefixed with `/api`. Protected routes require a Sanctum bearer token.

### Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/register` | Register a new user |
| POST | `/api/auth/login` | Log in and receive a token |
| POST | `/api/auth/demo-login` | One-click demo login |
| POST | `/api/auth/logout` | Log out (protected) |
| GET | `/api/auth/me` | Get current user (protected) |

### Wallbit Connection

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/wallbit/connect` | Connect Wallbit vault (rate-limited) |
| GET | `/api/wallbit/status` | Check connection status |
| DELETE | `/api/wallbit/disconnect` | Disconnect vault |

### Leagues

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/leagues` | List all public leagues |
| POST | `/api/leagues` | Create a new league |
| GET | `/api/leagues/my` | List leagues the user belongs to |
| GET | `/api/leagues/invite/{code}` | Find league by invite code |
| GET | `/api/leagues/{id}` | Get league details |
| POST | `/api/leagues/{id}/join` | Join a league |
| DELETE | `/api/leagues/{id}/leave` | Leave a league |

### Trading

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/leagues/{id}/assets/{symbol}` | Preview asset with live price |
| POST | `/api/leagues/{id}/trades` | Execute a trade (buy/sell) |
| GET | `/api/leagues/{id}/trades` | List trade history |

### Portfolio and Competition

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/leagues/{id}/portfolio` | Get portfolio for current user |
| GET | `/api/leagues/{id}/leaderboard` | Get league leaderboard |
| GET | `/api/leagues/{id}/leaderboard/history` | Leaderboard snapshots over time |
| GET | `/api/leagues/{id}/analytics` | Portfolio analytics and metrics |
| GET | `/api/leagues/{id}/compare` | Head-to-head member comparison |

---

## Testing

WallBet includes a Playwright end-to-end test suite covering the main user flows.

**Test files:**

| File | Coverage |
|------|----------|
| `auth.spec.ts` | Registration, login, session handling |
| `dashboard.spec.ts` | Dashboard rendering, league list |
| `leagues.spec.ts` | League creation, joining, navigation |
| `leaderboard.spec.ts` | Leaderboard display and ranking |
| `portfolio.spec.ts` | Portfolio positions and values |
| `analytics.spec.ts` | Charts, return metrics |
| `trading.spec.ts` | Buy/sell flows, trade history |

### Running Tests

```bash
cd frontend
npx playwright install   # first time only
npx playwright test
```

To run a specific test file:

```bash
npx playwright test e2e/auth.spec.ts
```

To run tests with the browser visible:

```bash
npx playwright test --headed
```
