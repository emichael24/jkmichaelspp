<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class XpLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [];

        // Progressive XP curve: each level costs more
        // Level 1 = 0 XP, Level 2 = 100 XP, etc.
        $titles = [
            1 => 'Novice', 2 => 'Novice', 3 => 'Novice', 4 => 'Novice',
            5 => 'Apprentice', 6 => 'Apprentice', 7 => 'Apprentice', 8 => 'Apprentice', 9 => 'Apprentice',
            10 => 'Practitioner', 11 => 'Practitioner', 12 => 'Practitioner', 13 => 'Practitioner',
            14 => 'Practitioner', 15 => 'Practitioner', 16 => 'Practitioner', 17 => 'Practitioner',
            18 => 'Practitioner', 19 => 'Practitioner',
            20 => 'Professional', 21 => 'Professional', 22 => 'Professional', 23 => 'Professional',
            24 => 'Professional', 25 => 'Professional', 26 => 'Professional', 27 => 'Professional',
            28 => 'Professional', 29 => 'Professional',
            30 => 'Expert', 31 => 'Expert', 32 => 'Expert', 33 => 'Expert', 34 => 'Expert',
            35 => 'Expert', 36 => 'Expert', 37 => 'Expert', 38 => 'Expert', 39 => 'Expert',
            40 => 'Master', 41 => 'Master', 42 => 'Master', 43 => 'Master', 44 => 'Master',
            45 => 'Master', 46 => 'Master', 47 => 'Master', 48 => 'Master', 49 => 'Master',
            50 => 'Guru',
        ];

        $cumulative = 0;
        for ($level = 1; $level <= 50; $level++) {
            $levels[] = [
                'level' => $level,
                'xp_required' => $cumulative,
                'title' => $titles[$level] ?? 'Guru',
            ];

            // XP per level increases: 100, 120, 145, 175, 210 ...
            $cumulative += (int) round(100 * pow(1.08, $level - 1));
        }

        DB::table('xp_levels')->upsert($levels, ['level'], ['xp_required', 'title']);
    }
}
