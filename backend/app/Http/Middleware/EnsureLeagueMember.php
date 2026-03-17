<?php

namespace App\Http\Middleware;

use App\Models\League;
use App\Models\LeagueMember;
use Closure;
use Illuminate\Http\Request;

class EnsureLeagueMember
{
    /**
     * Abort with 403 if the authenticated user is not a member of the league in the route.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        /** @var League $league */
        $league = $request->route('league');

        $isMember = LeagueMember::where('league_id', $league->id)
            ->where('user_id', $request->user()->id)
            ->exists();

        if (!$isMember) {
            abort(403, 'You are not a member of this league.');
        }

        return $next($request);
    }
}
