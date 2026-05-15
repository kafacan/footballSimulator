<?php

use App\Http\Controllers\GameSessionController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

// SPA page routes.
Route::view('/', 'welcome');
Route::view('/national-league/setup', 'welcome');
Route::view('/sessions/{session}', 'welcome')->whereNumber('session');

// JSON endpoints used by the Vue app.
Route::get('/teams', [TeamController::class, 'index']);

Route::prefix('game-sessions')->group(function () {
    Route::get('/', [GameSessionController::class, 'index']);
    Route::post('/', [GameSessionController::class, 'store']);
    Route::delete('/', [GameSessionController::class, 'destroyAll']);
    Route::get('/{gameSession}', [GameSessionController::class, 'show']);
    Route::delete('/{gameSession}', [GameSessionController::class, 'destroy']);
    Route::post('/{gameSession}/reset', [GameSessionController::class, 'reset']);
    Route::post('/{gameSession}/play-next', [GameSessionController::class, 'playNext']);
    Route::post('/{gameSession}/play-all', [GameSessionController::class, 'playAll']);
    Route::post('/{gameSession}/matches/{matchGame}/play', [GameSessionController::class, 'playMatch']);
    Route::patch('/{gameSession}/matches/{matchGame}', [GameSessionController::class, 'updateMatchResult']);
});
