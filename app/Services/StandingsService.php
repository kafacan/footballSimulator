<?php

namespace App\Services;

use App\Models\GameSession;
use App\Models\Group;
use App\Models\MatchGame;
use App\Models\Team;
use Illuminate\Support\Collection;

class StandingsService
{
    public function calculate(?int $throughWeek = null, ?int $groupId = null, ?GameSession $session = null): Collection
    {
        $standings = Team::emptyStandingsRows($groupId, $session);

        MatchGame::completedMatches($throughWeek, $groupId, $session)
            ->each(function (MatchGame $match) use (&$standings) {
                $this->applyResult($standings, $match);
            });

        return $standings
            ->values()
            ->sortBy([
                ['points', 'desc'],
                ['goal_difference', 'desc'],
                ['goals_for', 'desc'],
                ['team', 'asc'],
            ])
            ->values();
    }

    public function groupedStandings(?int $throughWeek = null, ?GameSession $session = null): Collection
    {
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

    public function completedWeekStandings(?GameSession $session = null): Collection
    {
        return MatchGame::matchesGroupedByWeek(session: $session)
            ->filter(fn (Collection $matches) => $matches->every(
                fn (MatchGame $match) => $match->home_score !== null && $match->away_score !== null
            ))
            ->mapWithKeys(fn (Collection $matches, int $week) => [
                $week => $this->groupedStandings($week, $session),
            ]);
    }

    private function applyResult(Collection $standings, MatchGame $match): void
    {
        $home = $standings->get($match->home_team_id);
        $away = $standings->get($match->away_team_id);

        $home['played']++;
        $away['played']++;
        $home['goals_for'] += $match->home_score;
        $home['goals_against'] += $match->away_score;
        $away['goals_for'] += $match->away_score;
        $away['goals_against'] += $match->home_score;

        if ($match->home_score > $match->away_score) {
            $home['won']++;
            $away['lost']++;
            $home['points'] += 3;
        } elseif ($match->home_score < $match->away_score) {
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

        $standings->put($match->home_team_id, $home);
        $standings->put($match->away_team_id, $away);
    }
}
