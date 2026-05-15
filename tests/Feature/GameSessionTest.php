<?php

namespace Tests\Feature;

use App\Models\GameSession;
use App\Models\MatchGame;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_teams_for_session_setup(): void
    {
        Team::factory()->count(4)->create();

        $this->getJson('/teams')
            ->assertOk()
            ->assertJsonCount(4, 'teams')
            ->assertJsonStructure([
                'teams' => [
                    ['id', 'name', 'power', 'pot'],
                ],
            ]);
    }

    public function test_legacy_league_json_routes_are_removed(): void
    {
        $this->getJson('/league')->assertNotFound();
        $this->postJson('/league/reset')->assertNotFound();
        $this->postJson('/league/play-next-week')->assertNotFound();
        $this->postJson('/league/play-all')->assertNotFound();
        $this->postJson('/league/matches/1/play')->assertNotFound();
        $this->patchJson('/league/matches/1')->assertNotFound();
    }

    public function test_it_lists_and_resumes_sessions(): void
    {
        $teams = Team::factory()->count(4)->create();

        $sessionId = $this->postJson('/game-sessions', [
            'name' => 'Weekend League',
            'mode' => GameSession::MODE_NATIONAL_LEAGUE,
            'team_ids' => $teams->pluck('id')->all(),
        ])->assertCreated()
            ->json('session.id');

        $this->getJson('/game-sessions')
            ->assertOk()
            ->assertJsonPath('sessions.0.name', 'Weekend League')
            ->assertJsonPath('sessions.0.matches_count', 12);

        $this->getJson("/game-sessions/{$sessionId}")
            ->assertOk()
            ->assertJsonPath('session.name', 'Weekend League')
            ->assertJsonCount(12, 'matches')
            ->assertJsonCount(4, 'standings.0.rows');
    }

    public function test_it_continues_a_session_from_the_next_unplayed_week(): void
    {
        $teams = Team::factory()->count(4)->create();

        $sessionId = $this->postJson('/game-sessions', [
            'mode' => GameSession::MODE_NATIONAL_LEAGUE,
            'team_ids' => $teams->pluck('id')->all(),
        ])->assertCreated()
            ->json('session.id');

        $this->postJson("/game-sessions/{$sessionId}/play-next")
            ->assertOk()
            ->assertJsonPath('session.current_week', 1);

        $this->postJson("/game-sessions/{$sessionId}/play-next")
            ->assertOk()
            ->assertJsonPath('session.current_week', 2);
    }

    public function test_session_match_play_endpoint_cannot_mutate_another_sessions_match(): void
    {
        $teams = Team::factory()->count(4)->create();
        $firstSessionId = $this->createNationalLeagueSession($teams->pluck('id')->all());
        $secondSessionId = $this->createNationalLeagueSession($teams->pluck('id')->all());
        $otherMatch = MatchGame::query()->where('game_session_id', $secondSessionId)->firstOrFail();

        $this->postJson("/game-sessions/{$firstSessionId}/matches/{$otherMatch->id}/play")
            ->assertNotFound();

        $this->assertDatabaseHas('match_games', [
            'id' => $otherMatch->id,
            'home_score' => null,
            'away_score' => null,
        ]);
    }

    public function test_session_match_update_endpoint_cannot_mutate_another_sessions_match(): void
    {
        $teams = Team::factory()->count(4)->create();
        $firstSessionId = $this->createNationalLeagueSession($teams->pluck('id')->all());
        $secondSessionId = $this->createNationalLeagueSession($teams->pluck('id')->all());
        $otherMatch = MatchGame::query()->where('game_session_id', $secondSessionId)->firstOrFail();

        $this->patchJson("/game-sessions/{$firstSessionId}/matches/{$otherMatch->id}", [
            'home_score' => 2,
            'away_score' => 1,
        ])->assertNotFound();

        $this->assertDatabaseHas('match_games', [
            'id' => $otherMatch->id,
            'home_score' => null,
            'away_score' => null,
        ]);
    }

    public function test_it_deletes_a_session_and_its_matches(): void
    {
        $teams = Team::factory()->count(4)->create();

        $sessionId = $this->postJson('/game-sessions', [
            'mode' => GameSession::MODE_NATIONAL_LEAGUE,
            'team_ids' => $teams->pluck('id')->all(),
        ])->assertCreated()
            ->json('session.id');

        $this->assertDatabaseHas('game_sessions', ['id' => $sessionId]);
        $this->assertSame(12, MatchGame::query()->where('game_session_id', $sessionId)->count());

        $this->deleteJson("/game-sessions/{$sessionId}")
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('game_sessions', ['id' => $sessionId]);
        $this->assertSame(0, MatchGame::query()->where('game_session_id', $sessionId)->count());
    }

    public function test_it_deletes_all_sessions(): void
    {
        $teams = Team::factory()->count(4)->create();

        $this->postJson('/game-sessions', [
            'mode' => GameSession::MODE_NATIONAL_LEAGUE,
            'team_ids' => $teams->pluck('id')->all(),
        ])->assertCreated();

        $this->postJson('/game-sessions', [
            'mode' => GameSession::MODE_CHAMPIONS_LEAGUE,
        ])->assertCreated();

        $this->assertSame(2, GameSession::count());
        $this->assertGreaterThan(0, MatchGame::count());

        $this->deleteJson('/game-sessions')
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertSame(0, GameSession::count());
        $this->assertSame(0, MatchGame::count());
    }

    public function test_national_league_finishes_with_table_winner_without_knockouts(): void
    {
        $teams = Team::factory()->count(4)->sequence(
            ['name' => 'Alpha FC', 'power' => 99],
            ['name' => 'Bravo FC', 'power' => 80],
            ['name' => 'Charlie FC', 'power' => 70],
            ['name' => 'Delta FC', 'power' => 60],
        )->create();

        $sessionId = $this->postJson('/game-sessions', [
            'mode' => GameSession::MODE_NATIONAL_LEAGUE,
            'team_ids' => $teams->pluck('id')->all(),
        ])->assertCreated()
            ->json('session.id');

        MatchGame::query()
            ->where('game_session_id', $sessionId)
            ->get()
            ->each(function (MatchGame $match) use ($teams) {
                $alphaId = $teams->firstWhere('name', 'Alpha FC')->id;

                $match->update([
                    'home_score' => $match->home_team_id === $alphaId ? 3 : 0,
                    'away_score' => $match->away_team_id === $alphaId ? 3 : 0,
                ]);
            });

        $alphaId = $teams->firstWhere('name', 'Alpha FC')->id;
        $targetMatch = MatchGame::query()->where('game_session_id', $sessionId)->first();

        $response = $this->patchJson("/game-sessions/{$sessionId}/matches/{$targetMatch->id}", [
            'home_score' => $targetMatch->home_team_id === $alphaId ? 3 : 0,
            'away_score' => $targetMatch->away_team_id === $alphaId ? 3 : 0,
        ]);

        $response->assertOk()
            ->assertJsonPath('session.status', GameSession::STATUS_FINISHED)
            ->assertJsonPath('current_stage', 'FINISHED')
            ->assertJsonPath('champion.name', 'Alpha FC')
            ->assertJsonPath('knockout_matches', []);
    }

    private function createNationalLeagueSession(array $teamIds): int
    {
        return $this->postJson('/game-sessions', [
            'mode' => GameSession::MODE_NATIONAL_LEAGUE,
            'team_ids' => $teamIds,
        ])->assertCreated()
            ->json('session.id');
    }
}
