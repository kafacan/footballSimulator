<?php

namespace Tests\Feature;

use App\Models\MatchGame;
use App\Models\Team;
use App\Services\MatchSimulationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchSimulationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_play_next_week_generates_scores_and_advances_one_week(): void
    {
        $this->createLeagueTeams();
        $sessionId = $this->createChampionsLeagueSession();

        $response = $this->postJson("/game-sessions/{$sessionId}/play-next");

        $response->assertOk()
            ->assertJsonMissingPath('predictions');

        $this->assertSame(2, MatchGame::query()->where('game_session_id', $sessionId)->where('week', 1)->whereNotNull('home_score')->whereNotNull('away_score')->count());
        $this->assertSame(0, MatchGame::query()->where('game_session_id', $sessionId)->where('week', 2)->whereNotNull('home_score')->whereNotNull('away_score')->count());

        MatchGame::query()
            ->where('game_session_id', $sessionId)
            ->where('week', 1)
            ->get()
            ->each(function (MatchGame $match) {
                $this->assertNotNull($match->home_score);
                $this->assertNotNull($match->away_score);
                $this->assertLessThanOrEqual(5, $match->home_score);
                $this->assertLessThanOrEqual(5, $match->away_score);
            });
    }

    public function test_single_match_cannot_be_played_before_previous_weeks_are_complete(): void
    {
        $this->createLeagueTeams();
        $sessionId = $this->createChampionsLeagueSession();
        $weekTwoMatch = MatchGame::query()->where('game_session_id', $sessionId)->where('week', 2)->first();

        $response = $this->postJson("/game-sessions/{$sessionId}/matches/{$weekTwoMatch->id}/play");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('match');

        $this->assertDatabaseHas('match_games', [
            'id' => $weekTwoMatch->id,
            'home_score' => null,
            'away_score' => null,
        ]);
    }

    public function test_play_all_finishes_the_league_and_returns_predictions(): void
    {
        $this->createLeagueTeams();
        $sessionId = $this->createChampionsLeagueSession();

        $response = $this->postJson("/game-sessions/{$sessionId}/play-all");

        $response->assertOk()
            ->assertJsonCount(12, 'matches')
            ->assertJsonCount(1, 'predictions')
            ->assertJsonCount(4, 'predictions.0.rows');

        $this->assertSame(12, MatchGame::query()->where('game_session_id', $sessionId)->whereNotNull('home_score')->whereNotNull('away_score')->count());
        $this->assertSame(100.0, round(collect($response->json('predictions.0.rows'))->sum('probability'), 1));
    }

    public function test_predictions_are_hidden_before_week_four_and_returned_after_week_four(): void
    {
        $this->createLeagueTeams();
        $sessionId = $this->createChampionsLeagueSession();

        $this->postJson("/game-sessions/{$sessionId}/play-next")->assertJsonMissingPath('predictions');
        $this->postJson("/game-sessions/{$sessionId}/play-next")->assertJsonMissingPath('predictions');
        $this->postJson("/game-sessions/{$sessionId}/play-next")->assertJsonMissingPath('predictions');

        $response = $this->postJson("/game-sessions/{$sessionId}/play-next");

        $response->assertOk()
            ->assertJsonCount(1, 'predictions')
            ->assertJsonCount(4, 'predictions.0.rows');

        $this->assertSame(100.0, round(collect($response->json('predictions.0.rows'))->sum('probability'), 1));
    }

    public function test_stronger_teams_generally_perform_better_in_simulation(): void
    {
        mt_srand(1234);

        $strong = Team::factory()->make(['power' => 95]);
        $weak = Team::factory()->make(['power' => 65]);
        $simulation = app(MatchSimulationService::class);
        $strongPoints = 0;
        $weakPoints = 0;

        for ($i = 0; $i < 200; $i++) {
            if ($i % 2 === 0) {
                [$strongScore, $weakScore] = $simulation->simulateScore($strong, $weak);
            } else {
                [$weakScore, $strongScore] = $simulation->simulateScore($weak, $strong);
            }

            if ($strongScore > $weakScore) {
                $strongPoints += 3;
            } elseif ($strongScore < $weakScore) {
                $weakPoints += 3;
            } else {
                $strongPoints++;
                $weakPoints++;
            }
        }

        $this->assertGreaterThan($weakPoints, $strongPoints);
    }

    private function createLeagueTeams(): void
    {
        Team::factory()->create(['name' => 'Manchester City', 'power' => 95]);
        Team::factory()->create(['name' => 'PSG', 'power' => 88]);
        Team::factory()->create(['name' => 'Fenerbahce', 'power' => 80]);
        Team::factory()->create(['name' => 'Celtic', 'power' => 72]);
    }

    private function createChampionsLeagueSession(): int
    {
        return $this->postJson('/game-sessions', [
            'mode' => 'champions_league',
        ])->assertCreated()
            ->json('session.id');
    }
}
