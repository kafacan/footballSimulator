<?php

namespace Tests\Feature;

use App\Models\MatchGame;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchResultEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_a_match_updates_its_score(): void
    {
        $this->createLeagueTeams();
        $sessionId = $this->createChampionsLeagueSession();
        $match = MatchGame::query()->where('game_session_id', $sessionId)->first();

        $response = $this->patchJson("/game-sessions/{$sessionId}/matches/{$match->id}", [
            'home_score' => 3,
            'away_score' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('matches.0.home_score', 3)
            ->assertJsonPath('matches.0.away_score', 1);

        $this->assertDatabaseHas('match_games', [
            'id' => $match->id,
            'home_score' => 3,
            'away_score' => 1,
        ]);
    }

    public function test_standings_are_recalculated_after_editing(): void
    {
        $this->createLeagueTeams();
        $sessionId = $this->createChampionsLeagueSession();
        $match = MatchGame::query()->where('game_session_id', $sessionId)->with('homeTeam')->first();

        $response = $this->patchJson("/game-sessions/{$sessionId}/matches/{$match->id}", [
            'home_score' => 5,
            'away_score' => 0,
        ]);

        $response->assertOk()
            ->assertJsonPath('standings.0.rows.0.team_id', $match->home_team_id)
            ->assertJsonPath('standings.0.rows.0.team', $match->homeTeam->name)
            ->assertJsonPath('standings.0.rows.0.points', 3)
            ->assertJsonPath('standings.0.rows.0.goal_difference', 5);
    }

    public function test_invalid_scores_are_rejected(): void
    {
        $this->createLeagueTeams();
        $sessionId = $this->createChampionsLeagueSession();
        $match = MatchGame::query()->where('game_session_id', $sessionId)->first();

        $response = $this->patchJson("/game-sessions/{$sessionId}/matches/{$match->id}", [
            'home_score' => -1,
            'away_score' => 10,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['home_score', 'away_score']);

        $this->assertDatabaseHas('match_games', [
            'id' => $match->id,
            'home_score' => null,
            'away_score' => null,
        ]);
    }

    public function test_score_nine_is_accepted(): void
    {
        $this->createLeagueTeams();
        $sessionId = $this->createChampionsLeagueSession();
        $match = MatchGame::query()->where('game_session_id', $sessionId)->first();

        $this->patchJson("/game-sessions/{$sessionId}/matches/{$match->id}", [
            'home_score' => 9,
            'away_score' => 9,
        ])->assertOk();

        $this->assertDatabaseHas('match_games', [
            'id' => $match->id,
            'home_score' => 9,
            'away_score' => 9,
        ]);
    }

    public function test_score_ten_is_rejected(): void
    {
        $this->createLeagueTeams();
        $sessionId = $this->createChampionsLeagueSession();
        $match = MatchGame::query()->where('game_session_id', $sessionId)->first();

        $this->patchJson("/game-sessions/{$sessionId}/matches/{$match->id}", [
            'home_score' => 10,
            'away_score' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('home_score');
    }

    public function test_negative_scores_are_rejected(): void
    {
        $this->createLeagueTeams();
        $sessionId = $this->createChampionsLeagueSession();
        $match = MatchGame::query()->where('game_session_id', $sessionId)->first();

        $this->patchJson("/game-sessions/{$sessionId}/matches/{$match->id}", [
            'home_score' => 0,
            'away_score' => -1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('away_score');
    }

    public function test_non_integer_scores_are_rejected(): void
    {
        $this->createLeagueTeams();
        $sessionId = $this->createChampionsLeagueSession();
        $match = MatchGame::query()->where('game_session_id', $sessionId)->first();

        $this->patchJson("/game-sessions/{$sessionId}/matches/{$match->id}", [
            'home_score' => 2.5,
            'away_score' => 1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('home_score');
    }

    public function test_missing_scores_are_rejected(): void
    {
        $this->createLeagueTeams();
        $sessionId = $this->createChampionsLeagueSession();
        $match = MatchGame::query()->where('game_session_id', $sessionId)->first();

        $this->patchJson("/game-sessions/{$sessionId}/matches/{$match->id}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['home_score', 'away_score']);
    }

    public function test_predictions_remain_hidden_before_week_four_after_editing(): void
    {
        $this->createLeagueTeams();
        $sessionId = $this->createChampionsLeagueSession();
        $match = MatchGame::query()->where('game_session_id', $sessionId)->first();

        $response = $this->patchJson("/game-sessions/{$sessionId}/matches/{$match->id}", [
            'home_score' => 2,
            'away_score' => 2,
        ]);

        $response->assertOk()
            ->assertJsonMissingPath('predictions');
    }

    public function test_predictions_are_returned_after_week_four_when_editing(): void
    {
        $this->createLeagueTeams();
        $sessionId = $this->createChampionsLeagueSession();

        for ($i = 0; $i < 4; $i++) {
            $this->postJson("/game-sessions/{$sessionId}/play-next");
        }

        $match = MatchGame::query()->where('game_session_id', $sessionId)->first();

        $response = $this->patchJson("/game-sessions/{$sessionId}/matches/{$match->id}", [
            'home_score' => 1,
            'away_score' => 0,
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'predictions')
            ->assertJsonCount(4, 'predictions.0.rows');

        $this->assertSame(100.0, round(collect($response->json('predictions.0.rows'))->sum('probability'), 1));
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
