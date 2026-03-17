// leagues/[id] — redirects to /leagues/[id]/leaderboard by default.
// This page also acts as the shell (layout) shared by all league sub-pages.

import { redirect } from 'next/navigation';

interface Props {
  params: Promise<{ id: string }>;
}

export default async function LeagueDetailPage({ params }: Props) {
  const { id } = await params;
  redirect(`/leagues/${id}/leaderboard`);
}
