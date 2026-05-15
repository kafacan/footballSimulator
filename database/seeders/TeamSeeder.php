<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $teamsByPot = [
            1 => [
                ['Real Madrid', 98],
                ['Manchester City', 97],
                ['Bayern Munich', 96],
                ['Barcelona', 94],
                ['Liverpool', 93],
                ['Paris Saint-Germain', 92],
                ['Inter Milan', 91],
                ['Arsenal', 90],
                ['Borussia Dortmund', 89],
            ],
            2 => [
                ['Atletico Madrid', 88],
                ['Juventus', 87],
                ['Bayer Leverkusen', 86],
                ['Benfica', 85],
                ['RB Leipzig', 84],
                ['Porto', 83],
                ['Napoli', 82],
                ['Ajax', 81],
                ['Tottenham Hotspur', 80],
            ],
            3 => [
                ['Sporting CP', 79],
                ['Shakhtar Donetsk', 78],
                ['AC Milan', 78],
                ['PSV Eindhoven', 77],
                ['Feyenoord', 76],
                ['Celtic', 75],
                ['Galatasaray', 74],
                ['Monaco', 73],
                ['Lille', 72],
            ],
            4 => [
                ['Fenerbahce', 71],
                ['Club Brugge', 70],
                ['Young Boys', 69],
                ['Dinamo Zagreb', 68],
                ['Red Star Belgrade', 67],
                ['Copenhagen', 66],
                ['Sturm Graz', 65],
                ['Slavia Prague', 64],
                ['Union Saint-Gilloise', 63],
            ],
        ];

        foreach ($teamsByPot as $pot => $teams) {
            foreach ($teams as [$name, $power]) {
                Team::create([
                    'name' => $name,
                    'power' => $power,
                    'pot' => $pot,
                ]);
            }
        }
    }
}
