<?php

namespace App\Services\GameModes;

use App\Enums\TournamentFormat;
use App\Enums\TournamentStage;
use App\Models\GameSession;
use App\Models\MatchGame;
use App\Services\FixtureService;
use App\Services\MatchSimulationService;
use App\Services\StandingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NationalLeagueGameModeService
{
    public function __construct(
        private FixtureService $fixtures,
        private MatchSimulationService $simulation,
        private StandingsService $standings,
    ) {}

    public function create(array $teamIds, ?string $name = null): GameSession
    {
        return DB::transaction(function () use ($teamIds, $name) {
            $session = GameSession::create([
                'name' => $name ?: 'National League',
                'mode' => GameSession::MODE_NATIONAL_LEAGUE,
                'status' => GameSession::STATUS_IN_PROGRESS,
                'current_stage' => TournamentStage::GROUP_STAGE->value,
                'current_week' => 1,
                'settings' => ['format' => TournamentFormat::SINGLE_GROUP->value],
            ]);

            $session->syncTeams($teamIds);
            $this->reset($session);

            return $session->refresh();
        });
    }

    public function ensureReady(GameSession $session): void
    {
        if ($session->hasMatches()) {
            return;
        }

        $this->reset($session);
    }

    public function reset(GameSession $session): void
    {
        $this->fixtures->resetAndGenerate($session, $session->teamIds());

        $session->markInProgress(TournamentStage::GROUP_STAGE->value, 1);
    }

    public function playNext(GameSession $session): GameSession
    {
        return DB::transaction(function () use ($session) {
            $week = MatchGame::firstUnplayedWeek($session);

            if ($week === null) {
                return $session->refresh();
            }

            $this->simulation->simulateMatches(MatchGame::unplayedMatchesForWeek($week, $session));
            $this->syncSession($session, $week);

            return $session->refresh();
        });
    }

    public function playAll(GameSession $session): GameSession
    {
        return DB::transaction(function () use ($session) {
            while (MatchGame::hasUnplayedMatches($session)) {
                $week = MatchGame::firstUnplayedWeek($session);

                if ($week === null) {
                    break;
                }

                $this->simulation->simulateMatches(MatchGame::unplayedMatchesForWeek($week, $session));
                $this->syncSession($session, $week);
            }

            return $session->refresh();
        });
    }

    public function playMatch(GameSession $session, MatchGame $match): GameSession
    {
        $this->assertSessionMatch($session, $match);

        if ($match->home_score !== null && $match->away_score !== null) {
            return $session->refresh();
        }

        if (! $this->simulation->canPlayMatch($match, $session)) {
            throw ValidationException::withMessages([
                'match' => 'Previous week matches must be played first.',
            ]);
        }

        return DB::transaction(function () use ($session, $match) {
            $this->simulation->simulate($match);
            $this->syncSession($session, $match->week);

            return $session->refresh();
        });
    }

    public function updateMatchResult(GameSession $session, MatchGame $match, array $result): GameSession
    {
        $this->assertSessionMatch($session, $match);
        $match->update($result);
        $this->syncSession($session);

        return $session->refresh();
    }

    private function assertSessionMatch(GameSession $session, MatchGame $match): void
    {
        abort_unless((int) $match->game_session_id === (int) $session->id, 404);
    }

    private function syncSession(GameSession $session, ?int $week = null): void
    {
        if (MatchGame::hasUnplayedMatches($session)) {
            $session->markInProgress(TournamentStage::GROUP_STAGE->value, $week);

            return;
        }

        $winnerId = $this->standings
            ->groupedStandings(session: $session)
            ->first()['rows']
            ->first()['team_id'] ?? null;

        $session->markFinished(
            championId: $winnerId,
            stage: TournamentStage::FINISHED->value,
            week: MatchGame::maxWeekForSession($session),
        );
    }
}
