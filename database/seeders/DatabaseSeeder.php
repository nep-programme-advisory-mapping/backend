<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ProvinceSeeder::class,
            DistrictSeeder::class,
            CommuneSeeder::class,
            VillageSeeder::class,
            EducationLevelSeeder::class,
            BudgetBandSeeder::class,
            TaxonomySeeder::class,
            // OrganisationSeeder::class,
            // OrganisationAccountSeeder::class,
            // ProgrammeEntriesSeeder::class,
            UserSeeder::class,
            RolePermissionSeeder::class,
        ]);
    }
}
