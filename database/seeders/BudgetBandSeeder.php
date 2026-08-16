<?php
namespace Database\Seeders;
use App\Models\BudgetBand;
use Illuminate\Database\Seeder;
class BudgetBandSeeder extends Seeder
{
public function run(): void
    {
        $bands = [
            ['label' => 'Under $50,000',            'min_amount' => 0,          'max_amount' => 49999],
            ['label' => '$50,000–$200,000',         'min_amount' => 50000,      'max_amount' => 200000],
            ['label' => '$200,000–$500,000',        'min_amount' => 200001,     'max_amount' => 500000],
            ['label' => '$500,000–$2,000,000',      'min_amount' => 500001,     'max_amount' => 2000000],
            ['label' => 'Above $2,000,000',         'min_amount' => 2000001,    'max_amount' => null],
        ];
    foreach ($bands as $band) {
            BudgetBand::firstOrCreate(['label' => $band['label']], $band);
        }
    }
}
