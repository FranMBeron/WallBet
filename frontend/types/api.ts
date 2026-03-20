// ============================================================
// WallBet API Types
// Mirrors all Laravel API Resource shapes exactly.
// ============================================================

// ----------------------------------------------------------------
// Auth
// ----------------------------------------------------------------

export interface User {
  id: string;
  username: string;
  display_name: string;
  email: string;
  avatar_url: string | null;
  has_wallbit_key: boolean;
}

export interface LoginResponse {
  token: string;
  user: User;
}

export interface RegisterResponse {
  token: string;
  user: User;
}

// ----------------------------------------------------------------
// Leagues
// ----------------------------------------------------------------

export type LeagueStatus = 'upcoming' | 'active' | 'finished';
export type LeagueType = 'sponsored' | 'private';

export interface League {
  id: string;
  name: string;
  description: string | null;
  type: LeagueType;
  buy_in: number;
  max_participants: number;
  member_count: number;
  status: LeagueStatus;
  invite_code: string;
  is_public: boolean;
  is_member: boolean;
  starts_at: string;   // ISO 8601
  ends_at: string;     // ISO 8601
  created_by: string;  // User ID
  my_rank?: number | null;
  my_return_pct?: number | null;
}

// ----------------------------------------------------------------
// Leaderboard
// ----------------------------------------------------------------

export interface BestTrade {
  ticker: string;
  return_pct: number;
}

export interface LeaderboardEntry {
  rank: number;
  rank_change: number;
  user: User;
  return_pct: number;
  total_value: number;
  pnl: number;
  total_trades: number;
  unique_tickers: number;
  best_trade: BestTrade | null;
  win_rate: number | null;
  risk_score: string;
}

export interface LeaderboardResponse {
  league: League;
  leaderboard: LeaderboardEntry[];
  my_rank: number | null;
}

export interface LeaderboardHistoryEntry {
  date: string;
  entries: Array<{
    user: User;
    rank: number;
    return_pct: number;
  }>;
}

export type LeaderboardHistory = LeaderboardHistoryEntry[];

// ----------------------------------------------------------------
// Portfolio
// ----------------------------------------------------------------

export interface Position {
  ticker: string;
  name: string;
  quantity: number;
  avg_price: number;
  current_price: number;
  value: number;
  pnl: number;
  pnl_pct: number;
  weight_pct: number;
}

export interface PortfolioSummary {
  initial_capital: number;
  total_value: number;
  cash_available: number;
  invested: number;
  return_pct: number;
  pnl: number;
}

export interface PortfolioResponse {
  user: User;
  summary: PortfolioSummary;
  positions: Position[];
  is_visible: boolean;
}

// ----------------------------------------------------------------
// Analytics
// ----------------------------------------------------------------

export interface ReturnBucket {
  range: string;
  count: number;
}

export interface TopTicker {
  ticker: string;
  holders: number;
  avg_weight: number;
}

export interface TradesPerDay {
  date: string;
  count: number;
}

export interface AnalyticsResponse {
  avg_return_pct: number | null;
  median_return_pct: number | null;
  positive_count: number;
  negative_count: number;
  returns_distribution: ReturnBucket[];
  top_tickers: TopTicker[] | null;
  avg_diversification: number;
  total_trades: number;
  trades_per_day: TradesPerDay[];
}

// ----------------------------------------------------------------
// Compare
// ----------------------------------------------------------------

export interface ComparePosition {
  ticker: string;
  shares: number;
  value: number;
  weight_pct: number;
}

export interface CompareParticipant {
  id: string;
  display_name: string;
  return_pct: number;
  total_trades: number;
  unique_tickers: number;
  win_rate: number | null;
  positions: ComparePosition[];
}

export interface CompareEvolution {
  dates: string[];
  user1_returns: (number | null)[];
  user2_returns: (number | null)[];
}

export interface CompareResponse {
  user1: CompareParticipant;
  user2: CompareParticipant;
  shared_tickers: string[];
  evolution: CompareEvolution;
}

// ----------------------------------------------------------------
// Wallbit
// ----------------------------------------------------------------

export interface WallbitStatus {
  connected: boolean;
  balance?: number | null;
  currency?: string | null;
}

// ----------------------------------------------------------------
// API Error shapes
// ----------------------------------------------------------------

export interface ApiValidationError {
  message: string;
  errors: Record<string, string[]>;
}

export interface ApiError {
  message: string;
}

// ----------------------------------------------------------------
// Trading
// ----------------------------------------------------------------

export interface AssetInfo {
  symbol: string;
  name: string;
  price: number;
  sector: string;
}

export interface TradeLog {
  id: string;
  ticker: string;
  action: 'BUY' | 'SELL';
  quantity: number;
  price: number;
  total_amount: number;
  executed_at: string;
}

export interface ExecuteTradePayload {
  symbol: string;
  direction: 'BUY' | 'SELL';
  order_type: 'MARKET';
  amount: number;
}

// ----------------------------------------------------------------
// Liquidation
// ----------------------------------------------------------------

export interface LiquidationResult {
  ticker: string;
  status: 'ok' | 'failed';
  shares?: number;
  amount?: number;
  error?: string;
}

export interface LiquidateResponse {
  results: LiquidationResult[];
  total_sold: number;
  total_failed: number;
}

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}
