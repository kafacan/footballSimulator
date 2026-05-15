<?php

namespace App\Enums;

enum TournamentFormat: string
{
    case OLD_GROUP_STAGE = 'old_group_stage';
    case NEW_LEAGUE_PHASE = 'new_league_phase';
    case SINGLE_GROUP = 'single_group';
}
