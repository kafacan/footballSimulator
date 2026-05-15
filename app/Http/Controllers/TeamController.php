<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\JsonResponse;

class TeamController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'teams' => Team::leagueTeams(),
        ]);
    }
}
