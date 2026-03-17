<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the plain array returned by LeaderboardService::getLeaderboard().
 * Instantiated as: new LeaderboardResource($data)
 */
class LeaderboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'my_rank'     => $this->resource['my_rank'],
            'leaderboard' => $this->resource['leaderboard'],
        ];
    }
}
