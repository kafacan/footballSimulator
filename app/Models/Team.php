<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'power',
        'pot',
    ];

    public function homeMatches(): HasMany
    {
        return $this->hasMany(MatchGame::class, 'home_team_id');
    }

    public function awayMatches(): HasMany
    {
        return $this->hasMany(MatchGame::class, 'away_team_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withTimestamps();
    }

    public static function leagueTeams(): EloquentCollection
    {
        return self::query()
            ->orderBy('id')
            ->get();
    }

    public static function leagueTeamsById(): Collection
    {
        return self::query()
            ->get()
            ->keyBy('id');
    }

    public static function teamsByIds(Collection $teamIds): EloquentCollection
    {
        return self::query()
            ->whereIn('id', $teamIds)
            ->get();
    }

    public static function orderedByIdsByName(array $teamIds): EloquentCollection
    {
        return self::query()
            ->whereIn('id', $teamIds)
            ->orderBy('name')
            ->get();
    }

    public static function oldGroupStageTeamsByPot(): Collection
    {
        return self::query()
            ->whereIn('pot', [1, 2, 3, 4])
            ->orderBy('pot')
            ->orderByDesc('power')
            ->orderBy('name')
            ->get()
            ->groupBy('pot')
            ->map(fn (Collection $teams) => $teams->take(8)->values());
    }

    public static function emptyStandingsRows(?int $groupId = null, ?GameSession $session = null): Collection
    {
        if ($groupId !== null) {
            $teams = Group::teamsOrderedByName($groupId);
        } elseif ($session) {
            $teams = $session->orderedTeams();
        } else {
            $teams = self::query()->orderBy('name')->get();
        }

        return $teams
            ->mapWithKeys(fn (Team $team) => [
                $team->id => [
                    'team_id' => $team->id,
                    'team' => $team->name,
                    'played' => 0,
                    'won' => 0,
                    'drawn' => 0,
                    'lost' => 0,
                    'goals_for' => 0,
                    'goals_against' => 0,
                    'goal_difference' => 0,
                    'points' => 0,
                ],
            ]);
    }
}
