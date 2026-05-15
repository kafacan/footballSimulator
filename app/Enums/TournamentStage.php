<?php

namespace App\Enums;

enum TournamentStage: string
{
    case GROUP_STAGE = 'GROUP_STAGE';
    case ROUND_OF_16 = 'ROUND_OF_16';
    case QUARTER_FINAL = 'QUARTER_FINAL';
    case SEMI_FINAL = 'SEMI_FINAL';
    case FINAL = 'FINAL';
    case FINISHED = 'FINISHED';

    public static function knockoutStages(): array
    {
        return [
            self::ROUND_OF_16,
            self::QUARTER_FINAL,
            self::SEMI_FINAL,
            self::FINAL,
        ];
    }

    public static function visibleKnockoutStages(): array
    {
        return [
            self::FINAL,
            self::SEMI_FINAL,
            self::QUARTER_FINAL,
            self::ROUND_OF_16,
        ];
    }
}
