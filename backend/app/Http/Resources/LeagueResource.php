<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeagueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isMember = $this->leagueMembers()
            ->where('user_id', $request->user()?->id)
            ->exists();

        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'description'      => $this->description,
            'type'             => $this->type,
            'status'           => $this->status,
            'buy_in'           => $this->buy_in,
            'max_participants' => $this->max_participants,
            'is_public'        => $this->is_public,
            'invite_code'      => $isMember ? $this->invite_code : null,
            'starts_at'        => $this->starts_at,
            'ends_at'          => $this->ends_at,
            'created_by'       => $this->created_by,
            'member_count'     => $this->leagueMembers()->count(),
            'is_member'        => $isMember,
            'created_at'       => $this->created_at,
        ];
        // password is NEVER exposed
    }
}
