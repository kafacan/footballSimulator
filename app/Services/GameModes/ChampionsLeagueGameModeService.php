<?php

namespace App\Services\GameModes;

use App\Enums\TournamentFormat;
use App\Enums\TournamentStage;
use App\Models\GameSession;
use App\Models\MatchGame;
use App\Services\FixtureService;
use App\Services\KnockoutGenerationService;
use App\Services\MatchSimulationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChampionsLeagueGameModeService
{
    public function __construct(
        private FixtureService $fixtures,
        private MatchSimulationService $simulation,
        private KnockoutGenerationService $knockout,
    ) {}

    public function create(?string $name = null): GameSession
    {
        return DB::transaction(function () use ($name) {
            $session = GameSession::create([
                'name' => $name ?: 'Champions League',
                'mode' => GameSession::MODE_CHAMPIONS_LEAGUE,
                'status' => GameSession::STATUS_IN_PROGRESS,
                'current_stage' => TournamentStage::GROUP_STAGE->value,
                'current_week' => 1,
                'settings' => ['format' => TournamentFormat::OLD_GROUP_STAGE->value],
            ]);

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
        $session->syncAllLeagueTeams();
        $this->fixtures->resetAndGenerate($session);

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
            $this->knockout->progress($session);
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
                $this->knockout->progress($session);
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
            $this->knockout->progress($session);
            $this->syncSession($session, $match->week);

            return $session->refresh();
        });
    }

    public function updateMatchResult(GameSession $session, MatchGame $match, array $result): GameSession
    {
        $this->assertSessionMatch($session, $match);

        $previousWinner = $this->knockout->resolvedWinnerForMatch($match, $session);
        $previousGroupQualifiers = $this->knockout->groupQualifierSignature($session);

        $match->update($result);
        $this->knockout->progressAfterEditedMatch($session, $match->refresh(), $previousWinner, $previousGroupQualifiers);
        $this->syncSession($session);

        return $session->refresh();
    }

    private function assertSessionMatch(GameSession $session, MatchGame $match): void
    {
        abort_unless((int) $match->game_session_id === (int) $session->id, 404);
    }

    private function syncSession(GameSession $session, ?int $week = null): void
    {
        $championId = $this->knockout->championId($session);
        $finished = $championId !== null || ! MatchGame::hasUnplayedMatches($session);

        $session->applyProgress(
            stage: $this->knockout->currentStage($session),
            championId: $championId,
            week: $week,
            finished: $finished,
        );
    }
}
