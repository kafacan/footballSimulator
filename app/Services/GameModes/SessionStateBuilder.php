<?php

namespace App\Services\GameModes;

use App\Enums\TournamentFormat;
use App\Enums\TournamentStage;
use App\Models\GameSession;
use App\Models\Group;
use App\Models\MatchGame;
use App\Models\Team;
use App\Services\KnockoutGenerationService;
use App\Services\KnockoutResolutionService;
use App\Services\PredictionService;
use App\Services\StandingsService;

class SessionStateBuilder
{
    public function __construct(
        private StandingsService $standings,
        private PredictionService $predictions,
        private KnockoutGenerationService $knockout,
        private KnockoutResolutionService $knockoutResolution,
    ) {}

    public function forSession(GameSession $session): array
    {
        return $session->isChampionsLeague()
            ? $this->championsLeague($session)
            : $this->nationalLeague($session);
    }

    public function championsLeague(GameSession $session): array
    {
        $championId = $this->knockout->championId($session);
        $finalistIds = MatchGame::stageMatches(TournamentStage::FINAL, $session)
            ->flatMap(fn (MatchGame $match) => [$match->home_team_id, $match->away_team_id])
            ->unique()
            ->values();

        $schedule = MatchGame::scheduleWithTeams($session);

        $state = [
            'format' => TournamentFormat::OLD_GROUP_STAGE->value,
            'session' => $session->loadDashboardRelations(),
            'current_stage' => $this->knockout->currentStage($session),
            'teams' => Team::leagueTeams(),
            'groups' => Group::tournamentGroups($session),
            'matches' => $schedule,
            'group_stage_matches' => $schedule
                ->where('stage', TournamentStage::GROUP_STAGE->value)
                ->values(),
            'knockout_matches' => MatchGame::knockoutMatchesGroupedByStage($session),
            'knockout_summaries' => $this->knockoutResolution->summaries($session),
            'standings' => $this->standings->groupedStandings(session: $session),
            'standings_by_week' => $this->standings->completedWeekStandings($session),
            'finalists' => $finalistIds->isEmpty() ? [] : Team::teamsByIds($finalistIds),
            'champion' => $championId === null ? null : Team::teamsByIds(collect([$championId]))->first(),
        ];

        if ($this->predictions->available(session: $session)) {
            $state['predictions'] = $this->predictions->groupedPredictions(session: $session);
            $state['predictions_by_week'] = $this->predictions->completedWeekPredictions($session);
        }

        if ($this->predictions->tournamentWinnerPredictions($session)->isNotEmpty()) {
            $state['tournament_winner_predictions'] = $this->predictions->tournamentWinnerPredictions($session);
            $state['tournament_winner_predictions_by_stage'] = $this->predictions->tournamentWinnerPredictionHistory($session);
        }

        return $state;
    }

    public function nationalLeague(GameSession $session): array
    {
        $schedule = MatchGame::scheduleWithTeams($session);

        $state = [
            'format' => TournamentFormat::SINGLE_GROUP->value,
            'session' => $session->loadDashboardRelations(),
            'current_stage' => $session->status === GameSession::STATUS_FINISHED
                ? TournamentStage::FINISHED->value
                : TournamentStage::GROUP_STAGE->value,
            'teams' => $session->orderedTeams(),
            'groups' => Group::tournamentGroups($session),
            'matches' => $schedule,
            'group_stage_matches' => $schedule
                ->where('stage', TournamentStage::GROUP_STAGE->value)
                ->values(),
            'knockout_matches' => [],
            'knockout_summaries' => [],
            'standings' => $this->standings->groupedStandings(session: $session),
            'standings_by_week' => $this->standings->completedWeekStandings($session),
            'champion' => $session->championTeam,
        ];

        if ($this->predictions->available(session: $session)) {
            $state['predictions'] = $this->predictions->groupedPredictions(session: $session);
            $state['predictions_by_week'] = $this->predictions->completedWeekPredictions($session);
        }

        return $state;
    }
}
