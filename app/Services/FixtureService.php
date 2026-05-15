<?php

namespace App\Services;

use App\Enums\TournamentFormat;
use App\Enums\TournamentStage;
use App\Models\GameSession;
use App\Models\MatchGame;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FixtureService
{
    public function __construct(private GroupGenerationService $groups) {}

    public function resetAndGenerate(?GameSession $session = null, ?array $teamIds = null): Collection
    {
        return DB::transaction(function () use ($session, $teamIds) {
            MatchGame::resetFixtures($session);

            $teams = $teamIds
                ? Team::orderedByIdsByName($teamIds)
                : Team::leagueTeams();

            if ($teamIds === null && $this->canGenerateOldGroupStage()) {
                $this->groups->generate(TournamentFormat::OLD_GROUP_STAGE, $session)
                    ->each(function ($group) use ($session) {
                        $this->createGroupFixtures($group->id, $group->teams->pluck('id')->all(), $session, reverseReturnLegs: true);
                    });

                return MatchGame::scheduleWithTeams($session);
            }

            if ($teams->count() < 4 || $teams->count() > 18) {
                throw new InvalidArgumentException('A group must include between 4 and 18 teams.');
            }

            $groupId = null;

            if ($session) {
                $this->groups->reset($session);
                $group = $this->groups->createSingleGroup($session, $teams->pluck('id')->all());
                $groupId = $group->id;
            }

            $this->createGroupFixtures($groupId, $teams->pluck('id')->all(), $session);

            return MatchGame::scheduleWithTeams($session);
        });
    }

    private function canGenerateOldGroupStage(): bool
    {
        $teamsByPot = Team::oldGroupStageTeamsByPot();

        return $teamsByPot->count() === 4
            && $teamsByPot->every(fn ($teams) => $teams->count() === 8);
    }

    public function createGroupFixtures(
        ?int $groupId,
        array $teamIds,
        ?GameSession $session = null,
        bool $reverseReturnLegs = false,
    ): void {
        if (count($teamIds) < 4 || count($teamIds) > 18) {
            throw new InvalidArgumentException('A group must include between 4 and 18 teams.');
        }

        $rounds = $this->buildRounds($teamIds);
        $week = 1;

        foreach ($rounds as $round) {
            foreach ($round as [$homeTeamId, $awayTeamId]) {
                MatchGame::createFixture($week, $homeTeamId, $awayTeamId, $groupId, TournamentStage::GROUP_STAGE, session: $session);
            }

            $week++;
        }

        $returnRounds = $reverseReturnLegs ? array_reverse($rounds) : $rounds;

        foreach ($returnRounds as $round) {
            foreach ($round as [$homeTeamId, $awayTeamId]) {
                MatchGame::createFixture($week, $awayTeamId, $homeTeamId, $groupId, TournamentStage::GROUP_STAGE, session: $session);
            }

            $week++;
        }
    }

    public function buildRounds(array $teamIds): array
    {
        $rounds = [];
        $rotating = $teamIds;

        if (count($rotating) % 2 === 1) {
            $rotating[] = null;
        }

        $teamCount = count($rotating);

        for ($round = 0; $round < $teamCount - 1; $round++) {
            $matches = [];

            for ($i = 0; $i < $teamCount / 2; $i++) {
                $home = $rotating[$i];
                $away = $rotating[$teamCount - 1 - $i];

                if ($home === null || $away === null) {
                    continue;
                }

                $matches[] = $round % 2 === 0 ? [$home, $away] : [$away, $home];
            }

            $rounds[] = $matches;
            $fixed = array_shift($rotating);
            $last = array_pop($rotating);
            array_unshift($rotating, $fixed, $last);
        }

        return $rounds;
    }
}
