<?php

namespace App\Services;

use App\Enums\TournamentStage;
use App\Models\GameSession;
use App\Models\Group;
use App\Models\MatchGame;
use Illuminate\Support\Collection;

class KnockoutGenerationService
{
    private const ROUND_WEEKS = [
        'ROUND_OF_16' => [7, 8],
        'QUARTER_FINAL' => [9, 10],
        'SEMI_FINAL' => [11, 12],
        'FINAL' => [13],
    ];

    public function __construct(
        private StandingsService $standings,
        private KnockoutResolutionService $resolution,
    ) {}

    public function progress(?GameSession $session = null): void
    {
        if ($this->groupStageComplete($session) && ! MatchGame::stageExists(TournamentStage::ROUND_OF_16, $session)) {
            $this->generateRoundOf16($session);
        }

        if (MatchGame::stageIsComplete(TournamentStage::ROUND_OF_16, $session) && ! MatchGame::stageExists(TournamentStage::QUARTER_FINAL, $session)) {
            $this->generateTwoLegRound(TournamentStage::QUARTER_FINAL, $this->resolution->pairingWinners(TournamentStage::ROUND_OF_16, $session), $session);
        }

        if (MatchGame::stageIsComplete(TournamentStage::QUARTER_FINAL, $session) && ! MatchGame::stageExists(TournamentStage::SEMI_FINAL, $session)) {
            $this->generateTwoLegRound(TournamentStage::SEMI_FINAL, $this->resolution->pairingWinners(TournamentStage::QUARTER_FINAL, $session), $session);
        }

        if (MatchGame::stageIsComplete(TournamentStage::SEMI_FINAL, $session) && ! MatchGame::stageExists(TournamentStage::FINAL, $session)) {
            $this->generateFinal($this->resolution->pairingWinners(TournamentStage::SEMI_FINAL, $session), $session);
        }
    }

    public function championId(?GameSession $session = null): ?int
    {
        if (! MatchGame::stageIsComplete(TournamentStage::FINAL, $session)) {
            return null;
        }

        return $this->resolution->pairingWinners(TournamentStage::FINAL, $session)->first();
    }

    public function resolvedWinnerForMatch(MatchGame $match, GameSession $session): ?int
    {
        $stage = TournamentStage::tryFrom((string) $match->stage);

        if (! $stage || $stage === TournamentStage::GROUP_STAGE || ! $match->pairing_key) {
            return null;
        }

        $matches = MatchGame::stageMatches($stage, $session)
            ->where('pairing_key', $match->pairing_key)
            ->values();

        if ($matches->isEmpty() || $matches->contains(fn (MatchGame $match) => $match->home_score === null || $match->away_score === null)) {
            return null;
        }

        return $this->resolution->resolvePairing($matches);
    }

    public function groupQualifierSignature(GameSession $session): ?array
    {
        if (! $this->groupStageComplete($session)) {
            return null;
        }

        return Group::tournamentGroups($session)
            ->mapWithKeys(function (Group $group) use ($session) {
                $rows = $this->standings->calculate(null, $group->id, $session);

                return [$group->name => [
                    $rows->get(0)['team_id'],
                    $rows->get(1)['team_id'],
                ]];
            })
            ->all();
    }

    public function progressAfterEditedMatch(
        GameSession $session,
        MatchGame $match,
        ?int $previousWinner,
        ?array $previousGroupQualifiers = null,
    ): void {
        $stage = TournamentStage::tryFrom((string) $match->stage);
        $newWinner = $this->resolvedWinnerForMatch($match, $session);

        if (
            $stage
            && $stage !== TournamentStage::GROUP_STAGE
            && $previousWinner !== null
            && $newWinner !== null
            && $previousWinner !== $newWinner
        ) {
            $this->deleteStagesAfter($stage, $session);
        }

        if (
            $stage === TournamentStage::GROUP_STAGE
            && $previousGroupQualifiers !== null
            && MatchGame::stageExists(TournamentStage::ROUND_OF_16, $session)
            && $previousGroupQualifiers !== $this->groupQualifierSignature($session)
        ) {
            $this->deleteStagesFrom(TournamentStage::ROUND_OF_16, $session);
        }

        $this->progress($session);
    }

    public function currentStage(?GameSession $session = null): string
    {
        foreach (TournamentStage::visibleKnockoutStages() as $stage) {
            if (MatchGame::stageExists($stage, $session)) {
                return MatchGame::stageIsComplete($stage, $session) && $stage === TournamentStage::FINAL
                    ? TournamentStage::FINISHED->value
                    : $stage->value;
            }
        }

        return TournamentStage::GROUP_STAGE->value;
    }

    private function groupStageComplete(?GameSession $session = null): bool
    {
        return Group::tournamentGroups($session)->count() === 8
            && MatchGame::stageIsComplete(TournamentStage::GROUP_STAGE, $session);
    }

    private function generateRoundOf16(?GameSession $session = null): void
    {
        $qualifiers = Group::tournamentGroups($session)
            ->mapWithKeys(function (Group $group) use ($session) {
                $rows = $this->standings->calculate(null, $group->id, $session);

                return [$group->name => [
                    'winner' => $rows->get(0)['team_id'],
                    'runner_up' => $rows->get(1)['team_id'],
                ]];
            });

        $pairings = [
            ['Group A', 'Group B'],
            ['Group B', 'Group A'],
            ['Group C', 'Group D'],
            ['Group D', 'Group C'],
            ['Group E', 'Group F'],
            ['Group F', 'Group E'],
            ['Group G', 'Group H'],
            ['Group H', 'Group G'],
        ];

        foreach ($pairings as $index => [$winnerGroup, $runnerUpGroup]) {
            $winnerId = $qualifiers->get($winnerGroup)['winner'];
            $runnerUpId = $qualifiers->get($runnerUpGroup)['runner_up'];
            $pairingKey = 'R16-'.($index + 1);

            MatchGame::createFixture(self::ROUND_WEEKS['ROUND_OF_16'][0], $runnerUpId, $winnerId, null, TournamentStage::ROUND_OF_16, 1, $pairingKey, $session);
            MatchGame::createFixture(self::ROUND_WEEKS['ROUND_OF_16'][1], $winnerId, $runnerUpId, null, TournamentStage::ROUND_OF_16, 2, $pairingKey, $session);
        }
    }

    private function generateTwoLegRound(TournamentStage $stage, Collection $teamIds, ?GameSession $session = null): void
    {
        $weeks = self::ROUND_WEEKS[$stage->value];
        $chunks = $teamIds->values()->chunk(2);

        foreach ($chunks as $index => $pair) {
            $pair = $pair->values();
            $pairingKey = $stage->value.'-'.($index + 1);
            MatchGame::createFixture($weeks[0], $pair->get(0), $pair->get(1), null, $stage, 1, $pairingKey, $session);
            MatchGame::createFixture($weeks[1], $pair->get(1), $pair->get(0), null, $stage, 2, $pairingKey, $session);
        }
    }

    private function generateFinal(Collection $teamIds, ?GameSession $session = null): void
    {
        MatchGame::createFixture(self::ROUND_WEEKS['FINAL'][0], $teamIds->get(0), $teamIds->get(1), null, TournamentStage::FINAL, 1, 'FINAL', $session);
    }

    private function deleteStagesAfter(TournamentStage $stage, GameSession $session): void
    {
        $stages = collect(TournamentStage::knockoutStages());

        $downstreamStages = $stages
            ->slice($stages->search($stage) + 1)
            ->map(fn (TournamentStage $stage) => $stage->value)
            ->values();

        if ($downstreamStages->isNotEmpty()) {
            MatchGame::deleteStagesForSession($session, $downstreamStages->all());
        }

        $session->clearChampion();
    }

    private function deleteStagesFrom(TournamentStage $stage, GameSession $session): void
    {
        $stages = collect(TournamentStage::knockoutStages());

        $stagesToDelete = $stages
            ->slice($stages->search($stage))
            ->map(fn (TournamentStage $stage) => $stage->value)
            ->values();

        MatchGame::deleteStagesForSession($session, $stagesToDelete->all());
        $session->clearChampion();
    }
}
