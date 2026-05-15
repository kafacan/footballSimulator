<?php

namespace App\Services;

use App\Models\GameSession;
use App\Models\MatchGame;
use App\Models\Team;
use Illuminate\Support\Collection;

class EloRatingService
{
    private const K_FACTOR = 20.0;

    private const RATING_SCALE = 40.0;

    private const HOME_ADVANTAGE = 6.0;

    public function ratingsForSession(?int $throughWeek = null, ?GameSession $session = null): Collection
    {
        $ratings = Team::leagueTeamsById()->map(fn (Team $team) => (float) $team->power);

        $matches = MatchGame::allCompletedForRating($throughWeek, $session);

        foreach ($matches as $match) {
            $homeRating = $ratings->get($match->home_team_id, 50.0);
            $awayRating = $ratings->get($match->away_team_id, 50.0);

            [$newHome, $newAway] = $this->updatePair($homeRating, $awayRating, $match->home_score, $match->away_score);

            $ratings->put($match->home_team_id, $newHome);
            $ratings->put($match->away_team_id, $newAway);
        }

        return $ratings;
    }

    public function winProbability(float $homeRating, float $awayRating): float
    {
        $diff = ($awayRating - $homeRating - self::HOME_ADVANTAGE) / self::RATING_SCALE;

        return 1.0 / (1.0 + 10 ** $diff);
    }

    private function updatePair(float $homeRating, float $awayRating, int $homeScore, int $awayScore): array
    {
        $expectedHome = $this->winProbability($homeRating, $awayRating);
        $actualHome = $homeScore === $awayScore ? 0.5 : ($homeScore > $awayScore ? 1.0 : 0.0);

        $goalDiff = abs($homeScore - $awayScore);
        $marginMultiplier = log($goalDiff + 1) * (2.2 / (abs($homeRating - $awayRating) * 0.001 + 2.2));

        $delta = self::K_FACTOR * max($marginMultiplier, 1.0) * ($actualHome - $expectedHome);

        return [$homeRating + $delta, $awayRating - $delta];
    }
}
