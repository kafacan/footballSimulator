<?php

namespace App\Services;

use App\Models\GameSession;
use App\Models\MatchGame;
use App\Models\Team;
use Illuminate\Support\Collection;

class MatchSimulationService
{
    private const HOME_ADVANTAGE = 6;

    private const MAX_GOALS = 5;

    public function canPlayMatch(MatchGame $match, ?GameSession $session = null): bool
    {
        return ! MatchGame::hasUnplayedMatchesBeforeWeek($match->week, $session);
    }

    public function simulateMatches(Collection $matches): Collection
    {
        return $matches->each(fn (MatchGame $match) => $this->simulate($match));
    }

    public function simulate(MatchGame $match): MatchGame
    {
        $match->loadMissing(['homeTeam', 'awayTeam']);

        [$homeScore, $awayScore] = $this->simulateScore($match->homeTeam, $match->awayTeam);

        $match->update([
            'home_score' => $homeScore,
            'away_score' => $awayScore,
        ]);

        return $match;
    }

    public function simulateScore(Team $homeTeam, Team $awayTeam): array
    {
        return $this->simulateScoreFromRatings($homeTeam->power, $awayTeam->power);
    }

    public function simulateScoreFromRatings(float $homeRating, float $awayRating): array
    {
        $ratingDifference = ($homeRating + self::HOME_ADVANTAGE) - $awayRating;

        $homeExpected = $this->clamp(1.25 + ($ratingDifference / 28), 0.25, 3.4);
        $awayExpected = $this->clamp(1.05 - ($ratingDifference / 32), 0.2, 3.1);

        return [
            $this->goalsFromExpected($homeExpected),
            $this->goalsFromExpected($awayExpected),
        ];
    }

    private function goalsFromExpected(float $expected): int
    {
        $goals = (int) round($expected + ($this->gaussianNoise() * 0.9));

        return (int) $this->clamp($goals, 0, self::MAX_GOALS);
    }

    private function gaussianNoise(): float
    {
        $first = max($this->randomFloat(), 0.0001);
        $second = $this->randomFloat();

        return sqrt(-2 * log($first)) * cos(2 * M_PI * $second);
    }

    private function randomFloat(): float
    {
        return mt_rand() / mt_getrandmax();
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return min($max, max($min, $value));
    }
}
