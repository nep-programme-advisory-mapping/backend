<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VillageSeeder extends Seeder
{
    public function run(): void
    {
        $all = json_decode(file_get_contents(__DIR__ . '/geography_data.json'), true);
        $data = $all['villages'];

        $rows = [];
        foreach ($data as $communeId => $villageNames) {
            foreach ($villageNames as $name) {
                $rows[] = [
                    'commune_id' => (int) $communeId,
                    'name' => $name,
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('villages')->insertOrIgnore($chunk);
        }
    }
}
