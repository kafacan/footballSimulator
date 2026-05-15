<?php

namespace Tests\Feature;

use App\Enums\TournamentStage;
use App\Models\GameSession;
use App\Models\Group;
use App\Models\MatchGame;
use App\Models\Team;
use App\Services\KnockoutResolutionService;
use Database\Seeders\TeamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class KnockoutStageTest extends TestCase
{
    use RefreshDatabase;

    public function test_round_of_16_is_generated_from_group_qualifiers(): void
    {
        $this->seed(TeamSeeder::class);
        $sessionId = $this->createChampionsLeagueSession();
        $session = GameSession::findOrFail($sessionId);
        $this->playWeeks($sessionId, 6);

        $matches = MatchGame::stageMatches(TournamentStage::ROUND_OF_16, $session);

        $this->assertCount(16, $matches);
        $this->assertSame(8, $matches->groupBy('pairing_key')->count());
        $matches->groupBy('pairing_key')->each(fn ($pairing) => $this->assertSame([1, 2], $pairing->pluck('leg')->sort()->values()->all()));
    }

    public function test_round_of_16_uses_deterministic_cross_group_pairings(): void
    {
        $this->seed(TeamSeeder::class);
        $sessionId = $this->createChampionsLeagueSession();
        $this->playWeeks($sessionId, 6);

        $groupA = Group::query()->where('game_session_id', $sessionId)->where('name', 'Group A')->firstOrFail();
        $groupB = Group::query()->where('game_session_id', $sessionId)->where('name', 'Group B')->firstOrFail();
        $groupAWinner = $this->getJson("/game-sessions/{$sessionId}")->json('standings.0.rows.0.team_id');
        $groupBRunnerUp = $this->getJson("/game-sessions/{$sessionId}")->json('standings.1.rows.1.team_id');

        $legOne = MatchGame::query()
            ->where('game_session_id', $sessionId)
            ->where('stage', TournamentStage::ROUND_OF_16->value)
            ->where('pairing_key', 'R16-1')
            ->where('leg', 1)
            ->firstOrFail();

        $this->assertNotSame($groupA->id, $groupB->id);
        $this->assertSame($groupBRunnerUp, $legOne->home_team_id);
        $this->assertSame($groupAWinner, $legOne->away_team_id);
    }

    public function test_aggregate_winner_ignores_away_goals(): void
    {
        $home = Team::factory()->create();
        $away = Team::factory()->create();

        MatchGame::createFixture(7, $home->id, $away->id, null, TournamentStage::ROUND_OF_16, 1, 'TIE-1')
            ->update(['home_score' => 1, 'away_score' => 0]);
        MatchGame::createFixture(8, $away->id, $home->id, null, TournamentStage::ROUND_OF_16, 2, 'TIE-1')
            ->update(['home_score' => 0, 'away_score' => 2]);

        $winner = app(KnockoutResolutionService::class)->pairingWinners(TournamentStage::ROUND_OF_16)->first();

        $this->assertSame($home->id, $winner);
    }

    public function test_knockout_progression_generates_final_and_champion(): void
    {
        $this->seed(TeamSeeder::class);
        $sessionId = $this->createChampionsLeagueSession();
        $session = GameSession::findOrFail($sessionId);

        $response = $this->postJson("/game-sessions/{$sessionId}/play-all");

        $response->assertOk()
            ->assertJsonPath('current_stage', 'FINISHED')
            ->assertJsonCount(2, 'finalists')
            ->assertJsonStructure(['champion' => ['id', 'name']]);

        $this->assertTrue(MatchGame::stageIsComplete(TournamentStage::FINAL, $session));
    }

    public function test_champion_predictions_are_available_when_knockout_starts(): void
    {
        $this->seed(TeamSeeder::class);
        $sessionId = $this->createChampionsLeagueSession();
        $this->playWeeks($sessionId, 6);

        $response = $this->getJson("/game-sessions/{$sessionId}");

        $response->assertOk()
            ->assertJsonCount(16, 'tournament_winner_predictions');
    }

    public function test_champion_prediction_history_is_available_for_each_knockout_stage(): void
    {
        $this->seed(TeamSeeder::class);
        $sessionId = $this->createChampionsLeagueSession();
        $this->playWeeks($sessionId, 8);

        $response = $this->getJson("/game-sessions/{$sessionId}");

        $response->assertOk()
            ->assertJsonCount(8, 'tournament_winner_predictions')
            ->assertJsonCount(16, 'tournament_winner_predictions_by_stage.ROUND_OF_16')
            ->assertJsonCount(8, 'tournament_winner_predictions_by_stage.QUARTER_FINAL');
    }

    public function test_tied_aggregate_resolves_consistently_across_repeated_state_reads(): void
    {
        $this->seed(TeamSeeder::class);
        $sessionId = $this->createChampionsLeagueSession();
        $this->playWeeks($sessionId, 6);

        $pairing = $this->firstPairing($sessionId, TournamentStage::ROUND_OF_16);
        $pairing->each(fn (MatchGame $match) => $match->update([
            'home_score' => 0,
            'away_score' => 0,
        ]));

        $firstWinner = $this->summaryWinner($this->getJson("/game-sessions/{$sessionId}")->assertOk()->json(), TournamentStage::ROUND_OF_16, $pairing->first()->pairing_key);
        $secondWinner = $this->summaryWinner($this->getJson("/game-sessions/{$sessionId}")->assertOk()->json(), TournamentStage::ROUND_OF_16, $pairing->first()->pairing_key);

        $this->assertNotNull($firstWinner);
        $this->assertSame($firstWinner, $secondWinner);
    }

    public function test_tied_final_and_champion_resolve_consistently_across_repeated_state_reads(): void
    {
        $this->seed(TeamSeeder::class);
        $sessionId = $this->createChampionsLeagueSession();
        $this->playWeeks($sessionId, 12);

        MatchGame::query()
            ->where('game_session_id', $sessionId)
            ->where('stage', TournamentStage::FINAL->value)
            ->firstOrFail()
            ->update([
                'home_score' => 2,
                'away_score' => 2,
            ]);

        $first = $this->getJson("/game-sessions/{$sessionId}")->assertOk()->json();
        $second = $this->getJson("/game-sessions/{$sessionId}")->assertOk()->json();

        $this->assertSame('FINISHED', $first['current_stage']);
        $this->assertNotNull($first['champion']);
        $this->assertSame($first['champion']['id'], $second['champion']['id']);
        $this->assertSame(
            $this->summaryWinner($first, TournamentStage::FINAL, 'FINAL'),
            $this->summaryWinner($second, TournamentStage::FINAL, 'FINAL'),
        );
    }

    public function test_editing_round_of_16_winner_after_quarter_final_exists_invalidates_quarter_final_and_later_rounds(): void
    {
        $this->seed(TeamSeeder::class);
        $sessionId = $this->createChampionsLeagueSession();
        $this->playWeeks($sessionId, 6);

        $pairing = $this->firstPairing($sessionId, TournamentStage::ROUND_OF_16);
        $winnerId = $this->teamIds($pairing)->first();
        $this->setPairingWinner($pairing, $winnerId);
        $this->playWeeks($sessionId, 2);

        $oldQuarterFinalIds = $this->stageIds($sessionId, TournamentStage::QUARTER_FINAL);
        $this->flipWinnerViaPatch($sessionId, $pairing, $winnerId);

        $oldQuarterFinalIds->each(fn (int $id) => $this->assertDatabaseMissing('match_games', ['id' => $id]));
        $this->assertCount(8, $this->stageIds($sessionId, TournamentStage::QUARTER_FINAL));
        $this->assertSame(0, $this->stageIds($sessionId, TournamentStage::SEMI_FINAL)->count());
        $this->assertSame(0, $this->stageIds($sessionId, TournamentStage::FINAL)->count());
    }

    public function test_editing_quarter_final_winner_after_semi_final_exists_invalidates_semi_final_and_later_rounds(): void
    {
        $this->seed(TeamSeeder::class);
        $sessionId = $this->createChampionsLeagueSession();
        $this->playWeeks($sessionId, 8);

        $pairing = $this->firstPairing($sessionId, TournamentStage::QUARTER_FINAL);
        $winnerId = $this->teamIds($pairing)->first();
        $this->setPairingWinner($pairing, $winnerId);
        $this->playWeeks($sessionId, 2);

        $oldSemiFinalIds = $this->stageIds($sessionId, TournamentStage::SEMI_FINAL);
        $this->flipWinnerViaPatch($sessionId, $pairing, $winnerId);

        $oldSemiFinalIds->each(fn (int $id) => $this->assertDatabaseMissing('match_games', ['id' => $id]));
        $this->assertCount(4, $this->stageIds($sessionId, TournamentStage::SEMI_FINAL));
        $this->assertSame(0, $this->stageIds($sessionId, TournamentStage::FINAL)->count());
    }

    public function test_editing_semi_final_winner_after_final_exists_invalidates_final(): void
    {
        $this->seed(TeamSeeder::class);
        $sessionId = $this->createChampionsLeagueSession();
        $this->playWeeks($sessionId, 10);

        $pairing = $this->firstPairing($sessionId, TournamentStage::SEMI_FINAL);
        $winnerId = $this->teamIds($pairing)->first();
        $this->setPairingWinner($pairing, $winnerId);
        $this->playWeeks($sessionId, 2);

        $oldFinalIds = $this->stageIds($sessionId, TournamentStage::FINAL);
        $this->flipWinnerViaPatch($sessionId, $pairing, $winnerId);

        $oldFinalIds->each(fn (int $id) => $this->assertDatabaseMissing('match_games', ['id' => $id]));
        $this->assertCount(1, $this->stageIds($sessionId, TournamentStage::FINAL));
    }

    public function test_editing_knockout_result_without_changing_winner_keeps_downstream_rounds(): void
    {
        $this->seed(TeamSeeder::class);
        $sessionId = $this->createChampionsLeagueSession();
        $this->playWeeks($sessionId, 6);

        $pairing = $this->firstPairing($sessionId, TournamentStage::ROUND_OF_16);
        $winnerId = $this->teamIds($pairing)->first();
        $this->setPairingWinner($pairing, $winnerId);
        $this->playWeeks($sessionId, 2);

        $quarterFinalIds = $this->stageIds($sessionId, TournamentStage::QUARTER_FINAL);
        $match = $pairing->first(fn (MatchGame $match) => $match->home_team_id === $winnerId || $match->away_team_id === $winnerId);

        $this->patchJson("/game-sessions/{$sessionId}/matches/{$match->id}", [
            'home_score' => $match->home_team_id === $winnerId ? 2 : 0,
            'away_score' => $match->away_team_id === $winnerId ? 2 : 0,
        ])->assertOk();

        $this->assertSame($quarterFinalIds->all(), $this->stageIds($sessionId, TournamentStage::QUARTER_FINAL)->all());
    }

    public function test_editing_group_stage_result_without_changing_qualifiers_keeps_existing_knockout_rounds(): void
    {
        $this->seed(TeamSeeder::class);
        $sessionId = $this->createChampionsLeagueSession();
        $this->playWeeks($sessionId, 8);

        $quarterFinalIds = $this->stageIds($sessionId, TournamentStage::QUARTER_FINAL);
        $groupMatch = MatchGame::query()
            ->where('game_session_id', $sessionId)
            ->where('stage', TournamentStage::GROUP_STAGE->value)
            ->firstOrFail();

        $this->patchJson("/game-sessions/{$sessionId}/matches/{$groupMatch->id}", [
            'home_score' => $groupMatch->home_score,
            'away_score' => $groupMatch->away_score,
        ])->assertOk();

        $this->assertSame($quarterFinalIds->all(), $this->stageIds($sessionId, TournamentStage::QUARTER_FINAL)->all());
    }

    public function test_editing_group_stage_result_that_changes_qualifier_order_invalidates_knockout_rounds(): void
    {
        $this->seed(TeamSeeder::class);
        $sessionId = $this->createChampionsLeagueSession();
        $this->playWeeks($sessionId, 8);

        $group = Group::query()
            ->where('game_session_id', $sessionId)
            ->where('name', 'Group A')
            ->with('teams')
            ->firstOrFail();
        $groupMatches = MatchGame::query()
            ->where('game_session_id', $sessionId)
            ->where('group_id', $group->id)
            ->get();
        $target = $groupMatches->firstOrFail();

        $groupMatches->each(fn (MatchGame $match) => $match->update([
            'home_score' => 0,
            'away_score' => 0,
        ]));
        $target->update([
            'home_score' => 1,
            'away_score' => 0,
        ]);

        $oldRoundOf16Ids = $this->stageIds($sessionId, TournamentStage::ROUND_OF_16);
        $oldQuarterFinalIds = $this->stageIds($sessionId, TournamentStage::QUARTER_FINAL);

        $this->patchJson("/game-sessions/{$sessionId}/matches/{$target->id}", [
            'home_score' => 0,
            'away_score' => 1,
        ])->assertOk();

        $oldRoundOf16Ids
            ->merge($oldQuarterFinalIds)
            ->each(fn (int $id) => $this->assertDatabaseMissing('match_games', ['id' => $id]));

        $this->assertCount(16, $this->stageIds($sessionId, TournamentStage::ROUND_OF_16));
        $this->assertSame(0, $this->stageIds($sessionId, TournamentStage::QUARTER_FINAL)->count());
        $this->assertSame(0, $this->stageIds($sessionId, TournamentStage::SEMI_FINAL)->count());
        $this->assertSame(0, $this->stageIds($sessionId, TournamentStage::FINAL)->count());
    }

    private function playWeeks(int $sessionId, int $weeks): void
    {
        for ($i = 0; $i < $weeks; $i++) {
            $this->postJson("/game-sessions/{$sessionId}/play-next")->assertOk();
        }
    }

    private function createChampionsLeagueSession(): int
    {
        return $this->postJson('/game-sessions', [
            'mode' => 'champions_league',
        ])->assertCreated()
            ->json('session.id');
    }

    private function firstPairing(int $sessionId, TournamentStage $stage): Collection
    {
        return MatchGame::query()
            ->where('game_session_id', $sessionId)
            ->where('stage', $stage->value)
            ->orderBy('pairing_key')
            ->orderBy('leg')
            ->get()
            ->groupBy('pairing_key')
            ->first();
    }

    private function stageIds(int $sessionId, TournamentStage $stage): Collection
    {
        return MatchGame::query()
            ->where('game_session_id', $sessionId)
            ->where('stage', $stage->value)
            ->orderBy('id')
            ->pluck('id');
    }

    private function teamIds(Collection $matches): Collection
    {
        return $matches
            ->flatMap(fn (MatchGame $match) => [$match->home_team_id, $match->away_team_id])
            ->unique()
            ->values();
    }

    private function setPairingWinner(Collection $matches, int $winnerId): void
    {
        $matches->each(fn (MatchGame $match) => $match->update([
            'home_score' => $match->home_team_id === $winnerId ? 1 : 0,
            'away_score' => $match->away_team_id === $winnerId ? 1 : 0,
        ]));
    }

    private function flipWinnerViaPatch(int $sessionId, Collection $matches, int $oldWinnerId): void
    {
        $newWinnerId = $this->teamIds($matches)
            ->first(fn (int $teamId) => $teamId !== $oldWinnerId);
        $match = $matches->first(fn (MatchGame $match) => $match->home_team_id === $newWinnerId || $match->away_team_id === $newWinnerId);

        $this->patchJson("/game-sessions/{$sessionId}/matches/{$match->id}", [
            'home_score' => $match->home_team_id === $newWinnerId ? 9 : 0,
            'away_score' => $match->away_team_id === $newWinnerId ? 9 : 0,
        ])->assertOk();
    }

    private function summaryWinner(array $state, TournamentStage $stage, string $pairingKey): ?int
    {
        return collect($state['knockout_summaries'][$stage->value] ?? [])
            ->firstWhere('pairing_key', $pairingKey)['winner_id'] ?? null;
    }
}
