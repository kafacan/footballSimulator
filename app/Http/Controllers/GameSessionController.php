<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMatchResultRequest;
use App\Models\GameSession;
use App\Models\MatchGame;
use App\Services\GameModes\ChampionsLeagueGameModeService;
use App\Services\GameModes\NationalLeagueGameModeService;
use App\Services\GameModes\SessionStateBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GameSessionController extends Controller
{
    public function __construct(
        private ChampionsLeagueGameModeService $championsLeague,
        private NationalLeagueGameModeService $nationalLeague,
        private SessionStateBuilder $stateBuilder,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'sessions' => GameSession::sessionList(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'mode' => ['required', Rule::in(GameSession::modes())],
            'team_ids' => ['array'],
            'team_ids.*' => ['integer', 'distinct', 'exists:teams,id'],
        ]);

        $session = $data['mode'] === GameSession::MODE_CHAMPIONS_LEAGUE
            ? $this->championsLeague->create($data['name'] ?? null)
            : $this->createNationalLeague($request, $data);

        return response()->json([
            'session' => $session->loadCounts(),
        ], 201);
    }

    public function show(GameSession $gameSession): JsonResponse
    {
        return response()->json($this->state($gameSession));
    }

    public function destroy(GameSession $gameSession): JsonResponse
    {
        $gameSession->delete();

        return response()->json([
            'deleted' => true,
        ]);
    }

    public function destroyAll(): JsonResponse
    {
        GameSession::deleteAll();

        return response()->json([
            'deleted' => true,
        ]);
    }

    public function reset(GameSession $gameSession): JsonResponse
    {
        $this->mode($gameSession)->reset($gameSession);

        return response()->json($this->state($gameSession->refresh()));
    }

    public function playNext(GameSession $gameSession): JsonResponse
    {
        return response()->json($this->state($this->mode($gameSession)->playNext($gameSession)));
    }

    public function playAll(GameSession $gameSession): JsonResponse
    {
        return response()->json($this->state($this->mode($gameSession)->playAll($gameSession)));
    }

    public function playMatch(GameSession $gameSession, MatchGame $matchGame): JsonResponse
    {
        $this->assertSessionMatch($gameSession, $matchGame);

        return response()->json($this->state($this->mode($gameSession)->playMatch($gameSession, $matchGame)));
    }

    public function updateMatchResult(
        UpdateMatchResultRequest $request,
        GameSession $gameSession,
        MatchGame $matchGame,
    ): JsonResponse {
        $this->assertSessionMatch($gameSession, $matchGame);

        $session = $this->mode($gameSession)->updateMatchResult($gameSession, $matchGame, $request->validated());

        return response()->json($this->state($session));
    }

    private function createNationalLeague(Request $request, array $data): GameSession
    {
        $request->validate([
            'team_ids' => ['required', 'array', 'min:4', 'max:18'],
            'team_ids.*' => ['required', 'integer', 'distinct', 'exists:teams,id'],
        ]);

        $teamIds = collect($data['team_ids'])->values();

        if ($teamIds->count() % 2 !== 0) {
            throw ValidationException::withMessages([
                'team_ids' => 'National league requires an even number of teams.',
            ]);
        }

        return $this->nationalLeague->create(
            $teamIds->all(),
            $data['name'] ?? null,
        );
    }

    private function assertSessionMatch(GameSession $session, MatchGame $match): void
    {
        abort_unless((int) $match->game_session_id === (int) $session->id, 404);
    }

    private function state(GameSession $session): array
    {
        $this->mode($session)->ensureReady($session);

        return $this->stateBuilder->forSession($session->refresh());
    }

    private function mode(GameSession $session): ChampionsLeagueGameModeService|NationalLeagueGameModeService
    {
        return $session->isChampionsLeague()
            ? $this->championsLeague
            : $this->nationalLeague;
    }
}
