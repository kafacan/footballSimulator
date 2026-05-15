<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\MatchGame;
use App\Models\Team;
use Database\Seeders\TeamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OldGroupStageFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_tournament_has_36_teams_across_4_pots(): void
    {
        $this->seed(TeamSeeder::class);

        $this->assertSame(36, Team::count());

        foreach ([1, 2, 3, 4] as $pot) {
            $this->assertSame(9, Team::query()->where('pot', $pot)->count());
        }
    }

    public function test_old_group_stage_uses_32_teams_and_generates_valid_groups(): void
    {
        $this->seed(TeamSeeder::class);

        $sessionId = $this->createChampionsLeagueSession();
        $response = $this->getJson("/game-sessions/{$sessionId}");

        $response->assertOk()
            ->assertJsonCount(36, 'teams')
            ->assertJsonCount(8, 'groups');

        $this->assertSame(32, Group::query()->where('game_session_id', $sessionId)->withCount('teams')->get()->sum('teams_count'));

        Group::query()
            ->where('game_session_id', $sessionId)
            ->with('teams')
            ->get()
            ->each(function (Group $group) {
                $this->assertCount(4, $group->teams);
                $this->assertSame([1, 2, 3, 4], $group->teams->pluck('pot')->sort()->values()->all());
            });
    }

    public function test_old_group_stage_fixtures_are_generated_per_group(): void
    {
        $this->seed(TeamSeeder::class);

        $sessionId = $this->createChampionsLeagueSession();

        $this->assertSame(96, MatchGame::query()->where('game_session_id', $sessionId)->count());

        Group::query()
            ->where('game_session_id', $sessionId)
            ->get()
            ->each(function (Group $group) {
                $this->assertSame(12, MatchGame::query()->where('group_id', $group->id)->count());
                $this->assertSame(
                    [1, 2, 3, 4, 5, 6],
                    MatchGame::query()->where('group_id', $group->id)->distinct()->orderBy('week')->pluck('week')->all()
                );

                MatchGame::query()
                    ->where('group_id', $group->id)
                    ->selectRaw('week, count(*) as matches_count')
                    ->groupBy('week')
                    ->pluck('matches_count')
                    ->each(fn (int $matchesCount) => $this->assertSame(2, $matchesCount));
            });
    }

    public function test_group_standings_are_calculated_independently(): void
    {
        $this->seed(TeamSeeder::class);
        $sessionId = $this->createChampionsLeagueSession();

        $groupA = Group::query()->where('game_session_id', $sessionId)->where('name', 'Group A')->firstOrFail();
        $groupB = Group::query()->where('game_session_id', $sessionId)->where('name', 'Group B')->firstOrFail();
        $groupAMatch = MatchGame::query()->where('group_id', $groupA->id)->firstOrFail();
        $groupBMatch = MatchGame::query()->where('group_id', $groupB->id)->firstOrFail();

        $groupAMatch->update(['home_score' => 4, 'away_score' => 0]);
        $groupBMatch->update(['home_score' => 0, 'away_score' => 3]);

        $response = $this->getJson("/game-sessions/{$sessionId}");

        $response->assertOk();

        $groupAStandings = collect($response->json('standings'))->firstWhere('group_id', $groupA->id);
        $groupBStandings = collect($response->json('standings'))->firstWhere('group_id', $groupB->id);

        $this->assertSame($groupAMatch->home_team_id, $groupAStandings['rows'][0]['team_id']);
        $this->assertSame(4, $groupAStandings['rows'][0]['goal_difference']);
        $this->assertSame($groupBMatch->away_team_id, $groupBStandings['rows'][0]['team_id']);
        $this->assertSame(3, $groupBStandings['rows'][0]['goal_difference']);
    }

    public function test_group_predictions_are_calculated_independently_after_week_four(): void
    {
        $this->seed(TeamSeeder::class);
        $sessionId = $this->createChampionsLeagueSession();

        for ($i = 0; $i < 4; $i++) {
            $this->postJson("/game-sessions/{$sessionId}/play-next")->assertOk();
        }

        $response = $this->getJson("/game-sessions/{$sessionId}");

        $response->assertOk()
            ->assertJsonCount(8, 'predictions');

        collect($response->json('predictions'))->each(function (array $groupPredictions) {
            $this->assertCount(4, $groupPredictions['rows']);
            $this->assertSame(100.0, round(collect($groupPredictions['rows'])->sum('probability'), 1));
        });
    }

    private function createChampionsLeagueSession(): int
    {
        return $this->postJson('/game-sessions', [
            'mode' => 'champions_league',
        ])->assertCreated()
            ->json('session.id');
    }
}
