<?php

namespace App\Services;

use App\Enums\TournamentStage;
use App\Models\GameSession;
use App\Models\MatchGame;
use App\Models\Team;
use Illuminate\Support\Collection;

class KnockoutResolutionService
{
    public function pairingWinners(TournamentStage $stage, ?GameSession $session = null): Collection
    {
        return MatchGame::stageMatches($stage, $session)
            ->groupBy('pairing_key')
            ->map(fn (Collection $matches) => $this->resolvePairing($matches))
            ->values();
    }

    public function resolvePairing(Collection $matches): int
    {
        $first = $matches->first();

        if ($matches->count() === 1) {
            if ($first->home_score === $first->away_score) {
                return $this->resolveTie(collect([$first->home_team_id, $first->away_team_id]));
            }

            return $first->home_score > $first->away_score
                ? $first->home_team_id
                : $first->away_team_id;
        }

        $totals = [];

        foreach ($matches as $match) {
            $totals[$match->home_team_id] = ($totals[$match->home_team_id] ?? 0) + $match->home_score;
            $totals[$match->away_team_id] = ($totals[$match->away_team_id] ?? 0) + $match->away_score;
        }

        arsort($totals);
        $teamIds = array_keys($totals);

        if ($totals[$teamIds[0]] === $totals[$teamIds[1]]) {
            return $this->resolveTie(collect($teamIds)->take(2));
        }

        return $teamIds[0];
    }

    private function resolveTie(Collection $teamIds): int
    {
        return $teamIds
            ->sort()
            ->values()
            ->first();
    }

    public function summaries(?GameSession $session = null): Collection
    {
        return collect(TournamentStage::knockoutStages())->mapWithKeys(function (TournamentStage $stage) use ($session) {
            return [
                $stage->value => MatchGame::stageMatches($stage, $session)
                    ->groupBy('pairing_key')
                    ->map(fn (Collection $matches) => $this->summary($stage, $matches))
                    ->values(),
            ];
        })->filter(fn (Collection $summaries) => $summaries->isNotEmpty());
    }

    private function summary(TournamentStage $stage, Collection $matches): array
    {
        $teamIds = $matches
            ->flatMap(fn (MatchGame $match) => [$match->home_team_id, $match->away_team_id])
            ->unique()
            ->values();

        $teams = Team::teamsByIds($teamIds)->keyBy('id');
        $totals = $teamIds->mapWithKeys(fn (int $teamId) => [$teamId => 0])->all();
        $complete = $matches->every(fn (MatchGame $match) => $match->home_score !== null && $match->away_score !== null);

        foreach ($matches as $match) {
            $totals[$match->home_team_id] += $match->home_score ?? 0;
            $totals[$match->away_team_id] += $match->away_score ?? 0;
        }

        $winnerId = $complete ? $this->resolvePairing($matches) : null;

        return [
            'stage' => $stage->value,
            'pairing_key' => $matches->first()->pairing_key,
            'complete' => $complete,
            'winner_id' => $winnerId,
            'winner' => $winnerId ? $teams->get($winnerId)?->name : null,
            'teams' => $teamIds->map(fn (int $teamId) => [
                'team_id' => $teamId,
                'team' => $teams->get($teamId)?->name,
                'aggregate' => $totals[$teamId],
            ])->values(),
            'matches' => $matches->values(),
        ];
    }
}
