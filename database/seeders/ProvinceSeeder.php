<?php

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;

class ProvinceSeeder extends Seeder
{
    private array $nameMap = [
        'Takéo' => 'Ta Keo',
        'Takeo' => 'Ta Keo',
        'Siemreap' => 'Siem Reap',
        'Mondulkiri' => 'Mondul Kiri',
        'Rattanakkiri' => 'Ratanak Kiri',
    ];

    public function run(): void
    {
        $data = json_decode(file_get_contents(__DIR__ . '/geography_data.json'), true);

        foreach ($data['provinces'] as $name) {
            $normalizedName = $this->nameMap[$name] ?? $name;
            Province::firstOrCreate(['province_name' => $normalizedName]);
        }
    }
}
