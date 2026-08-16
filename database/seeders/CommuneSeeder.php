<?php

namespace Database\Seeders;

use App\Models\District;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CommuneSeeder extends Seeder
{
    private array $districtNameMap = [
        'Kaeb' => 'Kep',
        'Damnak Chang\'aeur' => 'Damnak Chang\'aeur',
        'Saen Monourom' => 'Sen Monorom',
        'Preah Sihanouk' => 'Sihanoukville',
        'Prek Prasab' => 'Preaek Prasab',
        'Kracheh' => 'Kratie',
        'Prasat Ballangk' => 'Prasat Balangk',
        'Stueng Saen' => 'Stung Sen',
        'Anlong Veaeng' => 'Anlong Veng',
        'Ban Lung' => 'Banlung',
        'Angkor Thum' => 'Angkor Thom',
        'Samlout' => 'Samlot',
        'Rukh Kiri' => 'Ratanak Mondol',
        'Aek Phnum' => 'Ek Phnom',
        'Sampov Lun' => 'Sampov Loun',
        'Kuleaen' => 'Kulen',
        'Pur SenChey' => 'Pou Senchey',
        'Praek Pnov' => 'Prek Pnov',
        'Chraoy Chongvar' => 'Chroy Changvar',
        'Saensokh' => 'Sen Sok',
        'Doun Penh' => 'Daun Penh',
        'Stueng Trang' => 'Stung Trang',
        'Srei Santhor' => 'Srey Santhor',
        'Svay Teab' => 'Svay Theab',
        'Paoy Paet' => 'Poipet',
        'Phnum Srok' => 'Phnom Srok',
        'Khemara Phoumin' => 'Smach Mean Chey',
        'Mondol Seima' => 'Mondul Seima',
        'Kaoh Kong' => 'Koh Kong',
        'Veal Veaeng' => 'Veal Veng',
        'S\'ang' => 'Saang',
        'Kaoh Thum' => 'Kaoh Thom',
        'Stueng Traeng' => 'Stung Treng',
        'Me Sang' => 'Mesang',
        'Pur SenChey' => 'Pou Senchey',
        'Odongk Maechay' => 'Odongk',
        'Samkkei Munichay' => 'Samraong Tong',
        'Preah Vihear' => 'Tbaeng Mean Chey',
        'Talou Sen Chey' => 'Talou Sen Chey',
        'Ou Krieng Saenchey' => 'Sambour',
    ];

    public function run(): void
    {
        $districtIdByName = District::pluck('id', 'name');

        $data = json_decode(file_get_contents(__DIR__ . '/geography_data.json'), true);
        $provinces = $data['communes'];

        $rows = [];
        foreach ($provinces as $provinceName => $districts) {
            foreach ($districts as $districtName => $communeNames) {
                $mappedName = $this->districtNameMap[$districtName] ?? $districtName;
                $districtId = $districtIdByName[$mappedName] ?? null;
                if (!$districtId) {
                    $this->command->warn("District not found: {$districtName} ({$provinceName})");
                    continue;
                }
                foreach ($communeNames as $communeName) {
                    $rows[] = [
                        'district_id' => $districtId,
                        'name' => $communeName,
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('communes')->insertOrIgnore($chunk);
        }
    }
}
