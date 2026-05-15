<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameSession extends Model
{
    public const MODE_CHAMPIONS_LEAGUE = 'champions_league';

    public const MODE_NATIONAL_LEAGUE = 'national_league';

    public const STATUS_SETUP = 'setup';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_FINISHED = 'finished';

    protected $fillable = [
        'name',
        'mode',
        'status',
        'current_stage',
        'current_week',
        'champion_team_id',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function championTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'champion_team_id');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withTimestamps();
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(MatchGame::class);
    }

    public function isChampionsLeague(): bool
    {
        return $this->mode === self::MODE_CHAMPIONS_LEAGUE;
    }

    public static function modes(): array
    {
        return [
            self::MODE_CHAMPIONS_LEAGUE,
            self::MODE_NATIONAL_LEAGUE,
        ];
    }

    public static function sessionList(): EloquentCollection
    {
        return self::query()
            ->with('championTeam')
            ->withCount(['teams', 'matches'])
            ->latest()
            ->get();
    }

    public static function deleteAll(): void
    {
        self::query()->delete();
    }

    public function syncTeams(array $teamIds): void
    {
        $this->teams()->sync($teamIds);
    }

    public function syncAllLeagueTeams(): void
    {
        $this->teams()->sync(Team::leagueTeams()->pluck('id'));
    }

    public function teamIds(): array
    {
        return $this->teams()->pluck('teams.id')->all();
    }

    public function orderedTeams(): EloquentCollection
    {
        return $this->teams()->orderBy('name')->get();
    }

    public function hasMatches(): bool
    {
        return $this->matches()->exists();
    }

    public function clearChampion(): void
    {
        $this->update(['champion_team_id' => null]);
    }

    public function markInProgress(string $stage, ?int $week = null): void
    {
        $updates = [
            'status' => self::STATUS_IN_PROGRESS,
            'current_stage' => $stage,
            'champion_team_id' => null,
        ];

        if ($week !== null) {
            $updates['current_week'] = $week;
        }

        $this->update($updates);
    }

    public function markFinished(?int $championId, string $stage, ?int $week = null): void
    {
        $updates = [
            'status' => self::STATUS_FINISHED,
            'current_stage' => $stage,
            'champion_team_id' => $championId,
        ];

        if ($week !== null) {
            $updates['current_week'] = $week;
        }

        $this->update($updates);
    }

    public function applyProgress(string $stage, ?int $championId, ?int $week, bool $finished): void
    {
        $updates = [
            'status' => $finished ? self::STATUS_FINISHED : self::STATUS_IN_PROGRESS,
            'current_stage' => $stage,
            'champion_team_id' => $championId,
        ];

        if ($week !== null) {
            $updates['current_week'] = $week;
        }

        $this->update($updates);
    }

    public function loadDashboardRelations(): self
    {
        return $this->load('championTeam')->loadCount(['teams', 'matches']);
    }

    public function loadCounts(): self
    {
        return $this->loadCount(['teams', 'matches']);
    }
}
