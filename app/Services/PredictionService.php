<?php

namespace App\Services;

use App\Enums\TournamentStage;
use App\Models\GameSession;
use App\Models\Group;
use App\Models\MatchGame;
use App\Models\Team;
use Illuminate\Support\Collection;

class PredictionService
{
    private const GROUP_SIMULATIONS = 800;

    private const KNOCKOUT_SIMULATIONS = 1500;

    private const PREDICTIONS_AVAILABLE_AFTER_WEEK = 4;

    public function __construct(
        private StandingsService $standings,
        private KnockoutResolutionService $knockoutResolution,
        private EloRatingService $elo,
        private MatchSimulationService $simulation,
    ) {}

    public function available(?int $throughWeek = null, ?GameSession $session = null): bool
    {
        $completedWeek = $throughWeek ?? $this->completedWeeks($session)->max();

        return $completedWeek !== null && $completedWeek >= self::PREDICTIONS_AVAILABLE_AFTER_WEEK;
    }

    public function calculate(?int $throughWeek = null, ?int $groupId = null, ?GameSession $session = null): Collection
    {
        if (! $this->available($throughWeek, $session)) {
            return collect();
        }

        $standings = $this->standings->calculate($throughWeek, $groupId, $session);
        $remainingMatches = MatchGame::remainingMatchesForGroupPrediction($throughWeek, $groupId, $session);
        $ratings = $this->elo->ratingsForSession($throughWeek, $session);

        $teamNames = $standings->pluck('team', 'team_id');
        $teamIds = $standings->pluck('team_id');

        if ($remainingMatches->isEmpty()) {
            return $this->deterministicPredictions($standings, $teamNames);
        }

        $rows = $standings->keyBy('team_id')->map(fn (array $row) => $row);
        $matchSpecs = $remainingMatches->map(fn (MatchGame $match) => [
            'home' => $match->home_team_id,
            'away' => $match->away_team_id,
        ])->all();

        $wins = $teamIds->mapWithKeys(fn (int $id) => [$id => 0])->all();

        for ($i = 0; $i < self::GROUP_SIMULATIONS; $i++) {
            $simRows = $rows->map(fn (array $row) => $row)->all();

            foreach ($matchSpecs as $spec) {
                [$homeScore, $awayScore] = $this->simulation->simulateScoreFromRatings(
                    $ratings->get($spec['home'], 50.0),
                    $ratings->get($spec['away'], 50.0),
                );

                $this->applySimulatedResult($simRows, $spec['home'], $spec['away'], $homeScore, $awayScore);
            }

            $winnerId = $this->topTeamId($simRows);
            $wins[$winnerId]++;
        }

        $predictions = $teamIds->map(fn (int $id) => [
            'team_id' => $id,
            'team' => $teamNames->get($id),
            'probability' => round(($wins[$id] / self::GROUP_SIMULATIONS) * 100, 1),
        ])
            ->sortByDesc('probability')
            ->values();

        return $this->balanceToHundred($predictions);
    }

    public function groupedPredictions(?int $throughWeek = null, ?GameSession $session = null): Collection
    {
        if (! $this->available($throughWeek, $session)) {
            return collect();
        }

        $groups = Group::tournamentGroups($session);

        if ($groups->isEmpty()) {
            return collect([
                [
                    'group_id' => null,
                    'group' => null,
                    'rows' => $this->calculate($throughWeek, session: $session),
                ],
            ]);
        }

        return $groups->map(fn (Group $group) => [
            'group_id' => $group->id,
            'group' => $group->name,
            'rows' => $this->calculate($throughWeek, $group->id, $session),
        ]);
    }

    public function completedWeekPredictions(?GameSession $session = null): Collection
    {
        return $this->completedWeeks($session)
            ->filter(fn (int $week) => $week >= self::PREDICTIONS_AVAILABLE_AFTER_WEEK)
            ->mapWithKeys(fn (int $week) => [
                $week => $this->groupedPredictions($week, $session),
            ]);
    }

    public function tournamentWinnerPredictions(?GameSession $session = null): Collection
    {
        foreach (TournamentStage::visibleKnockoutStages() as $stage) {
            if (! MatchGame::stageExists($stage, $session)) {
                continue;
            }

            return $this->bracketPredictionsFromStage($stage, $session);
        }

        return collect();
    }

    public function tournamentWinnerPredictionHistory(?GameSession $session = null): Collection
    {
        return collect(TournamentStage::knockoutStages())
            ->filter(fn (TournamentStage $stage) => MatchGame::stageExists($stage, $session))
            ->mapWithKeys(fn (TournamentStage $stage) => [
                $stage->value => $this->bracketPredictionsFromStage($stage, $session),
            ]);
    }

    private function bracketPredictionsFromStage(TournamentStage $stage, ?GameSession $session): Collection
    {
        $matches = MatchGame::stageMatches($stage, $session);

        if ($matches->isEmpty()) {
            return collect();
        }

        $pairings = $matches->groupBy('pairing_key')->values();
        $teamIds = $matches
            ->flatMap(fn (MatchGame $match) => [$match->home_team_id, $match->away_team_id])
            ->unique()
            ->values();

        if (MatchGame::stageIsComplete($stage, $session) && $stage === TournamentStage::FINAL) {
            $winnerId = $this->knockoutResolution->pairingWinners($stage, $session)->first();

            return Team::teamsByIds($teamIds)
                ->keyBy('id')
                ->map(fn (Team $team) => [
                    'team_id' => $team->id,
                    'team' => $team->name,
                    'probability' => $team->id === $winnerId ? 100.0 : 0.0,
                ])
                ->values()
                ->sortByDesc('probability')
                ->values();
        }

        $ratings = $this->elo->ratingsForSession(null, $session);
        $teams = Team::teamsByIds($teamIds)->keyBy('id');
        $stageOrder = collect(TournamentStage::knockoutStages())->values();
        $startIndex = $stageOrder->search($stage);

        $champions = $teamIds->mapWithKeys(fn (int $id) => [$id => 0])->all();

        for ($i = 0; $i < self::KNOCKOUT_SIMULATIONS; $i++) {
            $survivors = $pairings->map(fn ($pairMatches) => $this->simulatePairing($pairMatches, $ratings))->values();

            for ($stageIndex = $startIndex + 1; $stageIndex < $stageOrder->count(); $stageIndex++) {
                $isFinal = $stageOrder->get($stageIndex) === TournamentStage::FINAL;
                $survivors = $this->simulateNextRound($survivors, $ratings, $isFinal);
            }

            $champions[$survivors->first()]++;
        }

        $predictions = $teamIds->map(fn (int $id) => [
            'team_id' => $id,
            'team' => $teams->get($id)?->name,
            'probability' => round(($champions[$id] / self::KNOCKOUT_SIMULATIONS) * 100, 1),
        ])
            ->sortByDesc('probability')
            ->values();

        return $this->balanceToHundred($predictions);
    }

    private function simulatePairing(Collection $matches, Collection $ratings): int
    {
        $aggregates = [];
        $ordered = $matches->sortBy('leg')->values();
        $first = $ordered->first();
        $teamA = $first->home_team_id;
        $teamB = $first->away_team_id;
        $aggregates[$teamA] = 0;
        $aggregates[$teamB] = 0;

        foreach ($ordered as $match) {
            if ($match->home_score !== null && $match->away_score !== null) {
                $aggregates[$match->home_team_id] += $match->home_score;
                $aggregates[$match->away_team_id] += $match->away_score;

                continue;
            }

            [$homeScore, $awayScore] = $this->simulation->simulateScoreFromRatings(
                $ratings->get($match->home_team_id, 50.0),
                $ratings->get($match->away_team_id, 50.0),
            );

            $aggregates[$match->home_team_id] += $homeScore;
            $aggregates[$match->away_team_id] += $awayScore;
        }

        if ($aggregates[$teamA] === $aggregates[$teamB]) {
            return $this->elo->winProbability($ratings->get($teamA, 50.0), $ratings->get($teamB, 50.0)) >= 0.5
                ? $teamA
                : $teamB;
        }

        return $aggregates[$teamA] > $aggregates[$teamB] ? $teamA : $teamB;
    }

    private function simulateNextRound(Collection $survivors, Collection $ratings, bool $isFinal): Collection
    {
        $next = collect();

        foreach ($survivors->chunk(2) as $pair) {
            $pair = $pair->values();
            $a = $pair->get(0);
            $b = $pair->get(1);

            $next->push($this->simulateBracketPair($a, $b, $ratings, $isFinal));
        }

        return $next;
    }

    private function simulateBracketPair(int $teamA, int $teamB, Collection $ratings, bool $singleLeg): int
    {
        $ratingA = $ratings->get($teamA, 50.0);
        $ratingB = $ratings->get($teamB, 50.0);

        if ($singleLeg) {
            [$scoreA, $scoreB] = $this->simulation->simulateScoreFromRatings($ratingA, $ratingB);
        } else {
            [$leg1A, $leg1B] = $this->simulation->simulateScoreFromRatings($ratingA, $ratingB);
            [$leg2B, $leg2A] = $this->simulation->simulateScoreFromRatings($ratingB, $ratingA);
            $scoreA = $leg1A + $leg2A;
            $scoreB = $leg1B + $leg2B;
        }

        if ($scoreA === $scoreB) {
            return $this->elo->winProbability($ratingA, $ratingB) >= 0.5 ? $teamA : $teamB;
        }

        return $scoreA > $scoreB ? $teamA : $teamB;
    }

    private function deterministicPredictions(Collection $standings, Collection $teamNames): Collection
    {
        $top = $standings->first()['team_id'] ?? null;

        return $standings->map(fn (array $row) => [
            'team_id' => $row['team_id'],
            'team' => $teamNames->get($row['team_id']),
            'probability' => $row['team_id'] === $top ? 100.0 : 0.0,
        ])->values();
    }

    private function applySimulatedResult(array &$rows, int $homeId, int $awayId, int $homeScore, int $awayScore): void
    {
        $home = $rows[$homeId];
        $away = $rows[$awayId];

        $home['played']++;
        $away['played']++;
        $home['goals_for'] += $homeScore;
        $home['goals_against'] += $awayScore;
        $away['goals_for'] += $awayScore;
        $away['goals_against'] += $homeScore;

        if ($homeScore > $awayScore) {
            $home['won']++;
            $away['lost']++;
            $home['points'] += 3;
        } elseif ($homeScore < $awayScore) {
            $away['won']++;
            $home['lost']++;
            $away['points'] += 3;
        } else {
            $home['drawn']++;
            $away['drawn']++;
            $home['points']++;
            $away['points']++;
        }

        $home['goal_difference'] = $home['goals_for'] - $home['goals_against'];
        $away['goal_difference'] = $away['goals_for'] - $away['goals_against'];

        $rows[$homeId] = $home;
        $rows[$awayId] = $away;
    }

    private function topTeamId(array $rows): int
    {
        $sorted = collect($rows)
            ->sortBy([
                ['points', 'desc'],
                ['goal_difference', 'desc'],
                ['goals_for', 'desc'],
                ['team', 'asc'],
            ])
            ->values();

        return $sorted->first()['team_id'];
    }

    private function balanceToHundred(Collection $predictions): Collection
    {
        if ($predictions->isEmpty()) {
            return $predictions;
        }

        $difference = round(100 - $predictions->sum('probability'), 1);

        if ($difference === 0.0) {
            return $predictions;
        }

        $topTeamId = $predictions->sortByDesc('probability')->first()['team_id'];

        return $predictions->map(function (array $row) use ($topTeamId, $difference) {
            if ($row['team_id'] === $topTeamId) {
                $row['probability'] = round($row['probability'] + $difference, 1);
            }

            return $row;
        })->values();
    }

    private function completedWeeks(?GameSession $session = null): Collection
    {
        return MatchGame::matchesGroupedByWeek(session: $session)
            ->filter(fn (Collection $matches) => $matches->every(
                fn (MatchGame $match) => $match->home_score !== null && $match->away_score !== null
            ))
            ->keys();
    }
}
