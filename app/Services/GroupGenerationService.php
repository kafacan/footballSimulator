<?php

namespace App\Services;

use App\Enums\TournamentFormat;
use App\Models\GameSession;
use App\Models\Group;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class GroupGenerationService
{
    private const OLD_GROUP_NAMES = [
        'Group A',
        'Group B',
        'Group C',
        'Group D',
        'Group E',
        'Group F',
        'Group G',
        'Group H',
    ];

    public function generate(TournamentFormat $format = TournamentFormat::OLD_GROUP_STAGE, ?GameSession $session = null): Collection
    {
        if ($format !== TournamentFormat::OLD_GROUP_STAGE) {
            throw new InvalidArgumentException('The new league phase is not implemented yet.');
        }

        Group::resetGroups($session);

        $teamsByPot = Team::oldGroupStageTeamsByPot();

        if ($teamsByPot->count() !== 4 || $teamsByPot->contains(fn ($teams) => $teams->count() !== 8)) {
            throw new InvalidArgumentException('Old group stage requires 8 teams from each of 4 pots.');
        }

        foreach (self::OLD_GROUP_NAMES as $groupIndex => $name) {
            $group = Group::createNamed($name, $session);

            foreach ([1, 2, 3, 4] as $pot) {
                $group->teams()->attach($teamsByPot->get($pot)->get($groupIndex)->id);
            }
        }

        return Group::tournamentGroups($session);
    }

    public function reset(?GameSession $session = null): void
    {
        Group::resetGroups($session);
    }

    public function createSingleGroup(GameSession $session, array $teamIds, string $name = 'League'): Group
    {
        $group = Group::createNamed($name, $session);
        $group->teams()->sync($teamIds);

        return $group->load('teams');
    }
}
