<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    protected $fillable = [
        'game_session_id',
        'name',
    ];

    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withTimestamps();
    }

    public function matches(): HasMany
    {
        return $this->hasMany(MatchGame::class);
    }

    public static function resetGroups(?GameSession $session = null): void
    {
        self::query()
            ->when($session, fn ($query) => $query->where('game_session_id', $session->id))
            ->delete();
    }

    public static function createNamed(string $name, ?GameSession $session = null): self
    {
        return self::create([
            'game_session_id' => $session?->id,
            'name' => $name,
        ]);
    }

    public static function teamsOrderedByName(int $groupId): EloquentCollection
    {
        return self::query()->findOrFail($groupId)->teams()->orderBy('name')->get();
    }

    public static function tournamentGroups(?GameSession $session = null): EloquentCollection
    {
        return self::query()
            ->when($session, fn ($query) => $query->where('game_session_id', $session->id))
            ->with(['teams' => fn ($query) => $query->orderBy('pot')->orderBy('name')])
            ->orderBy('name')
            ->get();
    }
}
