<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\MatchGame;
use App\Models\Team;
use Database\Seeders\TeamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixtureGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_home_and_away_fixtures_for_all_teams(): void
    {
        Team::factory()->count(4)->create();

        $sessionId = $this->createChampionsLeagueSession();
        $response = $this->getJson("/game-sessions/{$sessionId}");

        $response->assertOk()
            ->assertJsonCount(12, 'matches')
            ->assertJsonCount(4, 'teams')
            ->assertJsonCount(1, 'standings')
            ->assertJsonCount(4, 'standings.0.rows');

        $this->assertSame(12, MatchGame::query()->where('game_session_id', $sessionId)->count());
        $this->assertSame([1, 2, 3, 4, 5, 6], MatchGame::query()->where('game_session_id', $sessionId)->distinct()->orderBy('week')->pluck('week')->all());

        MatchGame::query()
            ->where('game_session_id', $sessionId)
            ->selectRaw('week, count(*) as matches_count')
            ->groupBy('week')
            ->pluck('matches_count')
            ->each(fn (int $matchesCount) => $this->assertSame(2, $matchesCount));

        $pairCounts = MatchGame::query()
            ->where('game_session_id', $sessionId)
            ->get()
            ->groupBy(fn (MatchGame $match) => collect([$match->home_team_id, $match->away_team_id])->sort()->implode('-'));

        $this->assertCount(6, $pairCounts);
        $pairCounts->each(fn ($matches) => $this->assertCount(2, $matches));
    }

    public function test_it_rejects_odd_team_counts_for_national_leagues(): void
    {
        $teams = Team::factory()->count(5)->create();

        $this->postJson('/game-sessions', [
            'mode' => 'national_league',
            'team_ids' => $teams->pluck('id')->all(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('team_ids');
    }

    public function test_it_rejects_duplicate_team_ids_for_national_leagues(): void
    {
        $teams = Team::factory()->count(4)->create();

        $this->postJson('/game-sessions', [
            'mode' => 'national_league',
            'team_ids' => [
                $teams[0]->id,
                $teams[0]->id,
                $teams[1]->id,
                $teams[1]->id,
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['team_ids.1', 'team_ids.3']);

        $this->assertSame(0, MatchGame::count());
    }

    public function test_it_rejects_too_few_unique_teams_for_national_leagues(): void
    {
        $teams = Team::factory()->count(2)->create();

        $this->postJson('/game-sessions', [
            'mode' => 'national_league',
            'team_ids' => $teams->pluck('id')->all(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('team_ids');

        $this->assertSame(0, MatchGame::count());
    }

    public function test_it_creates_national_leagues_with_valid_unique_even_team_counts(): void
    {
        $teams = Team::factory()->count(6)->create();

        $this->postJson('/game-sessions', [
            'mode' => 'national_league',
            'team_ids' => $teams->pluck('id')->all(),
        ])->assertCreated()
            ->assertJsonPath('session.teams_count', 6)
            ->assertJsonPath('session.matches_count', 30);

        $this->assertSame(30, MatchGame::count());
    }

    public function test_it_allows_groups_up_to_eighteen_teams(): void
    {
        $teams = Team::factory()->count(18)->create();

        $response = $this->postJson('/game-sessions', [
            'mode' => 'national_league',
            'team_ids' => $teams->pluck('id')->all(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('session.matches_count', 306);

        $this->assertSame(306, MatchGame::count());
        $this->assertSame(range(1, 34), MatchGame::query()->distinct()->orderBy('week')->pluck('week')->all());
    }

    public function test_it_rejects_groups_over_eighteen_teams(): void
    {
        $teams = Team::factory()->count(19)->create();

        $this->postJson('/game-sessions', [
            'mode' => 'national_league',
            'team_ids' => $teams->pluck('id')->all(),
        ])->assertUnprocessable();
    }

    public function test_national_league_return_legs_keep_the_same_opponent_order(): void
    {
        $teams = Team::factory()->count(4)->create();

        $this->postJson('/game-sessions', [
            'mode' => 'national_league',
            'team_ids' => $teams->pluck('id')->all(),
        ])->assertCreated();

        $teamId = $teams->first()->id;
        $opponents = $this->opponentOrderFor($teamId);

        $this->assertSame(
            array_slice($opponents, 0, 3),
            array_slice($opponents, 3, 3),
        );
    }

    public function test_ucl_return_legs_reverse_the_first_half_opponent_order(): void
    {
        $this->seed(TeamSeeder::class);
        $sessionId = $this->createChampionsLeagueSession();

        $group = Group::query()->where('game_session_id', $sessionId)->where('name', 'Group A')->with('teams')->firstOrFail();
        $teamId = $group->teams->first()->id;
        $opponents = $this->opponentOrderFor($teamId, $group->id);

        $this->assertSame(
            array_reverse(array_slice($opponents, 0, 3)),
            array_slice($opponents, 3, 3),
        );
    }

    private function opponentOrderFor(int $teamId, ?int $groupId = null): array
    {
        return MatchGame::query()
            ->when($groupId !== null, fn ($query) => $query->where('group_id', $groupId))
            ->where(fn ($query) => $query
                ->where('home_team_id', $teamId)
                ->orWhere('away_team_id', $teamId))
            ->orderBy('week')
            ->get()
            ->map(fn (MatchGame $match) => $match->home_team_id === $teamId
                ? $match->away_team_id
                : $match->home_team_id)
            ->all();
    }

    private function createChampionsLeagueSession(): int
    {
        return $this->postJson('/game-sessions', [
            'mode' => 'champions_league',
        ])->assertCreated()
            ->json('session.id');
    }
}
