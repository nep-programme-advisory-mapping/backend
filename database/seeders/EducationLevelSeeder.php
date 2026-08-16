<?php
namespace Database\Seeders;
use App\Models\EducationLevel;
use Illuminate\Database\Seeder;
class EducationLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            'Pre-school',
            'Primary',
            'Lower Secondary',
            'Upper Secondary',
            'Higher Education / TVET',
        ];

        foreach ($levels as $name) {
            EducationLevel::firstOrCreate(['level_name' => $name]);
        }
    }
}
