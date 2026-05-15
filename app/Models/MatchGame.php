<?php

namespace App\Models;

use App\Enums\TournamentStage;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class MatchGame extends Model
{
    protected $fillable = [
        'game_session_id',
        'group_id',
        'stage',
        'leg',
        'pairing_key',
        'week',
        'home_team_id',
        'away_team_id',
        'home_score',
        'away_score',
    ];

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public static function resetFixtures(?GameSession $session = null): void
    {
        self::query()
            ->when($session, fn ($query) => $query->where('game_session_id', $session->id))
            ->delete();
    }

    public static function createFixture(
        int $week,
        int $homeTeamId,
        int $awayTeamId,
        ?int $groupId = null,
        TournamentStage $stage = TournamentStage::GROUP_STAGE,
        ?int $leg = null,
        ?string $pairingKey = null,
        ?GameSession $session = null,
    ): self {
        return self::create([
            'game_session_id' => $session?->id,
            'group_id' => $groupId,
            'stage' => $stage->value,
            'leg' => $leg,
            'pairing_key' => $pairingKey,
            'week' => $week,
            'home_team_id' => $homeTeamId,
            'away_team_id' => $awayTeamId,
        ]);
    }

    public static function scheduleWithTeams(?GameSession $session = null): EloquentCollection
    {
        return self::query()
            ->when($session, fn ($query) => $query->where('game_session_id', $session->id))
            ->with(['group', 'homeTeam', 'awayTeam'])
            ->orderBy('group_id')
            ->orderBy('week')
            ->orderBy('id')
            ->get();
    }

    public static function completedMatches(?int $throughWeek = null, ?int $groupId = null, ?GameSession $session = null): EloquentCollection
    {
        return self::query()
            ->when($session, fn ($query) => $query->where('game_session_id', $session->id))
            ->where(fn ($query) => $query->where('stage', TournamentStage::GROUP_STAGE->value)->orWhereNull('stage'))
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->when($groupId !== null, fn ($query) => $query->where('group_id', $groupId))
            ->when($throughWeek !== null, fn ($query) => $query->where('week', '<=', $throughWeek))
            ->orderBy('group_id')
            ->orderBy('week')
            ->orderBy('id')
            ->get();
    }

    public static function matchesGroupedByWeek(?int $groupId = null, ?GameSession $session = null): Collection
    {
        return self::query()
            ->when($session, fn ($query) => $query->where('game_session_id', $session->id))
            ->where(fn ($query) => $query->where('stage', TournamentStage::GROUP_STAGE->value)->orWhereNull('stage'))
            ->when($groupId !== null, fn ($query) => $query->where('group_id', $groupId))
            ->orderBy('week')
            ->get()
            ->groupBy('week');
    }

    public static function firstUnplayedWeek(?GameSession $session = null): ?int
    {
        return self::query()
            ->when($session, fn ($query) => $query->where('game_session_id', $session->id))
            ->where(function ($query) {
                $query->whereNull('home_score')
                    ->orWhereNull('away_score');
            })
            ->min('week');
    }

    public static function unplayedMatchesForWeek(int $week, ?GameSession $session = null): EloquentCollection
    {
        return self::query()
            ->when($session, fn ($query) => $query->where('game_session_id', $session->id))
            ->with(['homeTeam', 'awayTeam'])
            ->where('week', $week)
            ->where(function ($query) {
                $query->whereNull('home_score')
                    ->orWhereNull('away_score');
            })
            ->orderBy('id')
            ->get();
    }

    public static function hasUnplayedMatches(?GameSession $session = null): bool
    {
        return self::query()
            ->when($session, fn ($query) => $query->where('game_session_id', $session->id))
            ->where(function ($query) {
                $query->whereNull('home_score')
                    ->orWhereNull('away_score');
            })
            ->exists();
    }

    public static function hasUnplayedMatchesBeforeWeek(int $week, ?GameSession $session = null): bool
    {
        return self::query()
            ->when($session, fn ($query) => $query->where('game_session_id', $session->id))
            ->where('week', '<', $week)
            ->where(function ($query) {
                $query->whereNull('home_score')
                    ->orWhereNull('away_score');
            })
            ->exists();
    }

    public static function remainingMatchesForGroupPrediction(?int $throughWeek = null, ?int $groupId = null, ?GameSession $session = null): EloquentCollection
    {
        return self::query()
            ->when($session, fn ($query) => $query->where('game_session_id', $session->id))
            ->with(['homeTeam', 'awayTeam'])
            ->where(fn ($query) => $query->where('stage', TournamentStage::GROUP_STAGE->value)->orWhereNull('stage'))
            ->when($groupId !== null, fn ($query) => $query->where('group_id', $groupId))
            ->when(
                $throughWeek !== null,
                fn ($query) => $query->where('week', '>', $throughWeek),
                fn ($query) => $query->where(function ($query) {
                    $query->whereNull('home_score')
                        ->orWhereNull('away_score');
                })
            )
            ->get();
    }

    public static function stageMatches(TournamentStage $stage, ?GameSession $session = null): EloquentCollection
    {
        return self::query()
            ->when($session, fn ($query) => $query->where('game_session_id', $session->id))
            ->with(['homeTeam', 'awayTeam'])
            ->where('stage', $stage->value)
            ->orderBy('pairing_key')
            ->orderBy('leg')
            ->orderBy('id')
            ->get();
    }

    public static function stageExists(TournamentStage $stage, ?GameSession $session = null): bool
    {
        return self::query()
            ->when($session, fn ($query) => $query->where('game_session_id', $session->id))
            ->where('stage', $stage->value)
            ->exists();
    }

    public static function stageIsComplete(TournamentStage $stage, ?GameSession $session = null): bool
    {
        $matches = self::stageMatches($stage, $session);

        return $matches->isNotEmpty()
            && $matches->every(fn (MatchGame $match) => $match->home_score !== null && $match->away_score !== null);
    }

    public static function allCompletedForRating(?int $throughWeek = null, ?GameSession $session = null): EloquentCollection
    {
        return self::query()
            ->when($session, fn ($query) => $query->where('game_session_id', $session->id))
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->when($throughWeek !== null, fn ($query) => $query->where('week', '<=', $throughWeek))
            ->orderBy('week')
            ->orderBy('id')
            ->get();
    }

    public static function deleteStagesForSession(GameSession $session, array $stageValues): void
    {
        if (empty($stageValues)) {
            return;
        }

        self::query()
            ->where('game_session_id', $session->id)
            ->whereIn('stage', $stageValues)
            ->delete();
    }

    public static function maxWeekForSession(?GameSession $session = null): int
    {
        return (int) self::query()
            ->when($session, fn ($query) => $query->where('game_session_id', $session->id))
            ->max('week');
    }

    public static function knockoutMatchesGroupedByStage(?GameSession $session = null): Collection
    {
        return self::query()
            ->when($session, fn ($query) => $query->where('game_session_id', $session->id))
            ->with(['homeTeam', 'awayTeam'])
            ->whereNot('stage', TournamentStage::GROUP_STAGE->value)
            ->orderBy('week')
            ->orderBy('pairing_key')
            ->orderBy('leg')
            ->get()
            ->groupBy('stage');
    }
}
