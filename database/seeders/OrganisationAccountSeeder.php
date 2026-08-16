<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Organisation;

class OrganisationAccountSeeder extends Seeder
{
    public function run(): void
    {
        $organisations = Organisation::all();

        foreach ($organisations as $organisation) {
            $isActive = $organisation->status === 'active';

            DB::table('organisation_accounts')->insert([
                [
                    'organisation_id' => $organisation->id,
                    'account_name' => $organisation->name . ' - Main Funding Account',
                    'allocated_amount' => $isActive ? 100000.00 : 50000.00,
                    'used_amount' => $isActive ? 35000.00 : 50000.00,
                    'currency' => 'USD',
                    'period_start' => now()->startOfYear(),
                    'period_end' => now()->endOfYear(),
                    'status' => $isActive ? 'active' : 'inactive',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }
}