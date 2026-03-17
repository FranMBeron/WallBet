// leagues/new — renders the CreateLeagueForm inside a content card.

import { CreateLeagueForm } from '@/components/leagues/CreateLeagueForm';

export default function NewLeaguePage() {
  return (
    <div className="max-w-xl mx-auto">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-white">Create a League</h1>
        <p className="text-sm text-gray-400 mt-0.5">
          Set up a new fantasy trading competition.
        </p>
      </div>

      <div className="rounded-xl border border-[#222222] bg-[#111111] p-6">
        <CreateLeagueForm />
      </div>
    </div>
  );
}
