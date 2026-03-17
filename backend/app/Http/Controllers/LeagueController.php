<?php

namespace App\Http\Controllers;

use App\Enums\LeagueStatus;
use App\Http\Requests\CreateLeagueRequest;
use App\Http\Requests\JoinLeagueRequest;
use App\Http\Resources\LeagueResource;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\WallbitKey;
use App\Services\WallbitClient;
use App\Services\WallbitVault;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LeagueController extends Controller
{
    public function __construct(
        private readonly WallbitClient $client,
        private readonly WallbitVault  $vault,
    ) {}

    /**
     * GET /leagues
     * Return a paginated list of public leagues ordered by creation date.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $leagues = League::where('is_public', true)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return LeagueResource::collection($leagues);
    }

    /**
     * POST /leagues
     * Create a new league with a unique invite code.
     */
    public function store(CreateLeagueRequest $request): JsonResponse|\Illuminate\Http\Response
    {
        $user = $request->user();

        // Generate a unique invite code (up to 5 retries)
        $inviteCode = null;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = strtoupper(Str::random(8));
            if (!League::where('invite_code', $candidate)->exists()) {
                $inviteCode = $candidate;
                break;
            }
        }

        if ($inviteCode === null) {
            return response()->json(['message' => 'Could not generate a unique invite code. Please try again.'], 500);
        }

        $rawPassword = null;
        $password    = null;
        if ($request->filled('password')) {
            $rawPassword = $request->string('password')->value();
            $password    = Hash::make($rawPassword);
        }

        // Guard: never store the raw password as the description
        $description = $request->input('description');
        if ($rawPassword && $description === $rawPassword) {
            $description = null;
        }

        $league = League::create([
            'name'             => $request->name,
            'description'      => $description,
            'type'             => $request->type,
            'buy_in'           => $request->buy_in,
            'max_participants' => $request->max_participants,
            'starts_at'        => $request->starts_at,
            'ends_at'          => $request->ends_at,
            'is_public'        => $request->boolean('is_public'),
            'invite_code'      => $inviteCode,
            'password'         => $password,
            'status'           => LeagueStatus::Upcoming,
            'created_by'       => $user->id,
        ]);

        // Automatically enrol the creator as a member so the league
        // appears on their Dashboard (which queries league_members).
        LeagueMember::create([
            'league_id'       => $league->id,
            'user_id'         => $user->id,
            'initial_capital' => $league->buy_in,
            'joined_at'       => now(),
        ]);

        return (new LeagueResource($league))->response()->setStatusCode(201);
    }

    /**
     * GET /leagues/my
     * Return all leagues where the authenticated user is a member.
     */
    public function my(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $leagues = League::whereHas('leagueMembers', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->get();

        return LeagueResource::collection($leagues);
    }

    /**
     * GET /leagues/invite/{code}
     * Resolve an invite code to a league (case-insensitive).
     */
    public function findByCode(Request $request, string $code): LeagueResource|JsonResponse
    {
        $league = League::whereRaw('UPPER(invite_code) = ?', [strtoupper($code)])->first();

        if (!$league) {
            return response()->json(['message' => 'League not found.'], 404);
        }

        return new LeagueResource($league);
    }

    /**
     * GET /leagues/{league}
     * Return league details with is_member flag.
     * Private leagues are only visible to their members.
     */
    public function show(Request $request, League $league): LeagueResource|JsonResponse
    {
        if (!$league->is_public) {
            $isMember = $league->leagueMembers()
                ->where('user_id', $request->user()->id)
                ->exists();

            if (!$isMember) {
                return response()->json(['message' => 'You are not a member of this league.'], 403);
            }
        }

        return new LeagueResource($league);
    }

    /**
     * POST /leagues/{league}/join
     * Join a league — requires wallbit.connected middleware.
     */
    public function join(JoinLeagueRequest $request, League $league): JsonResponse
    {
        $user = $request->user();

        // Guard 1: already a member
        if ($league->leagueMembers()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You are already a member of this league.'], 422);
        }

        // Guard 2: league is full
        if ($league->isFull()) {
            return response()->json(['message' => 'This league is full.'], 422);
        }

        // Guard 3: league must be upcoming
        if ($league->status !== LeagueStatus::Upcoming) {
            return response()->json(['message' => 'This league is not accepting new members.'], 422);
        }

        // Guard 4: check WallBit balance (skip in demo mode)
        if (config('app.demo_mode')) {
            $balance = 100_000.0;
        } else {
            $wallbitKey = WallbitKey::where('user_id', $user->id)
                ->where('is_valid', true)
                ->first();

            $decryptedKey = $this->vault->decrypt($wallbitKey);
            $balance      = $this->client->getBalance($decryptedKey);
        }

        if ($balance < $league->buy_in) {
            return response()->json(['message' => 'Insufficient WallBit balance to join this league.'], 422);
        }

        // Guard 5: private league password check
        if (!$league->is_public) {
            if (!Hash::check($request->string('password')->value(), $league->password)) {
                return response()->json(['message' => 'Invalid league password.'], 422);
            }
        }

        LeagueMember::create([
            'league_id'       => $league->id,
            'user_id'         => $user->id,
            'initial_capital' => $league->buy_in,
            'joined_at'       => now(),
        ]);

        return response()->json(['message' => 'Joined successfully.']);
    }

    /**
     * DELETE /leagues/{league}/leave
     * Leave a league — creators cannot leave.
     */
    public function leave(Request $request, League $league): JsonResponse
    {
        $user = $request->user();

        // Guard: must be a member
        $membership = $league->leagueMembers()->where('user_id', $user->id)->first();

        if (!$membership) {
            return response()->json(['message' => 'You are not a member of this league.'], 403);
        }

        // Guard: creator cannot leave
        if ($user->id === $league->created_by) {
            return response()->json(['message' => 'The league creator cannot leave the league.'], 403);
        }

        $membership->delete();

        return response()->json(['message' => 'You have left the league.']);
    }
}
