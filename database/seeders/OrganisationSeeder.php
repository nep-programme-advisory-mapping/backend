<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrganisationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organisations = [
            [
                'name' => 'Disability Development Services Program (DDSP)',
                'contact_name' => 'DDSP Director',
                'email' => 'contact@ddsp.org',
                'member_since' => 2018,
                'status' => 'active',
                'last_inactive_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Kampuchea Action for Primary Education (KAPE)',
                'contact_name' => 'KAPE Lead',
                'email' => 'contact@kapekh.org',
                'member_since' => 2019,
                'status' => 'active',
                'last_inactive_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Passerelles Numériques Cambodia (PNC)',
                'contact_name' => 'PNC Country Director',
                'email' => 'info.cambodia@passerellesnumeriques.org',
                'member_since' => 2015,
                'status' => 'active',
                'last_inactive_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Pour un Sourire d\'Enfant (PSE)',
                'contact_name' => 'PSE Executive Director',
                'email' => 'contact@pse.ngo',
                'member_since' => 2016,
                'status' => 'active',
                'last_inactive_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Caring for Cambodia (CFC)',
                'contact_name' => 'CFC Program Coordinator',
                'email' => 'support@caringforcambodia.org',
                'member_since' => 2021,
                'status' => 'inactive',
                'last_inactive_at' => now()->subDays(30),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($organisations as $org) {
            DB::table('organisations')->updateOrInsert(
                ['name' => $org['name']],
                $org
            );
        }
    }
}