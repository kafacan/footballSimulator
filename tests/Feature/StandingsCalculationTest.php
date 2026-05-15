<?php

namespace Tests\Feature;

use App\Enums\TournamentStage;
use App\Models\GameSession;
use App\Models\Group;
use App\Models\MatchGame;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StandingsCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_standings_from_completed_matches(): void
    {
        $alpha = Team::factory()->create(['name' => 'Alpha FC']);
        $bravo = Team::factory()->create(['name' => 'Bravo FC']);
        $charlie = Team::factory()->create(['name' => 'Charlie FC']);
        $delta = Team::factory()->create(['name' => 'Delta FC']);

        $session = GameSession::query()->create([
            'name' => 'Standings Test',
            'mode' => 'national_league',
            'status' => GameSession::STATUS_IN_PROGRESS,
            'current_stage' => TournamentStage::GROUP_STAGE->value,
            'current_week' => 1,
        ]);
        $session->teams()->sync([$alpha->id, $bravo->id, $charlie->id, $delta->id]);

        $group = Group::createNamed('League', $session);
        $group->teams()->sync([$alpha->id, $bravo->id, $charlie->id, $delta->id]);

        MatchGame::create([
            'game_session_id' => $session->id,
            'group_id' => $group->id,
            'stage' => TournamentStage::GROUP_STAGE->value,
            'week' => 1,
            'home_team_id' => $alpha->id,
            'away_team_id' => $bravo->id,
            'home_score' => 2,
            'away_score' => 0,
        ]);

        MatchGame::create([
            'game_session_id' => $session->id,
            'group_id' => $group->id,
            'stage' => TournamentStage::GROUP_STAGE->value,
            'week' => 1,
            'home_team_id' => $charlie->id,
            'away_team_id' => $delta->id,
            'home_score' => 1,
            'away_score' => 1,
        ]);

        MatchGame::create([
            'game_session_id' => $session->id,
            'group_id' => $group->id,
            'stage' => TournamentStage::GROUP_STAGE->value,
            'week' => 2,
            'home_team_id' => $bravo->id,
            'away_team_id' => $charlie->id,
            'home_score' => 3,
            'away_score' => 1,
        ]);

        MatchGame::create([
            'game_session_id' => $session->id,
            'group_id' => $group->id,
            'stage' => TournamentStage::GROUP_STAGE->value,
            'week' => 2,
            'home_team_id' => $delta->id,
            'away_team_id' => $alpha->id,
        ]);

        $response = $this->getJson("/game-sessions/{$session->id}");

        $response->assertOk()
            ->assertJsonPath('standings.0.rows.0.team', 'Alpha FC')
            ->assertJsonPath('standings.0.rows.0.points', 3)
            ->assertJsonPath('standings.0.rows.0.goal_difference', 2)
            ->assertJsonPath('standings.0.rows.1.team', 'Bravo FC')
            ->assertJsonPath('standings.0.rows.1.points', 3)
            ->assertJsonPath('standings.0.rows.1.goal_difference', 0)
            ->assertJsonPath('standings.0.rows.2.team', 'Delta FC')
            ->assertJsonPath('standings.0.rows.2.points', 1)
            ->assertJsonPath('standings.0.rows.2.played', 1)
            ->assertJsonPath('standings.0.rows.3.team', 'Charlie FC')
            ->assertJsonPath('standings.0.rows.3.points', 1)
            ->assertJsonPath('standings.0.rows.3.played', 2);
    }
}
