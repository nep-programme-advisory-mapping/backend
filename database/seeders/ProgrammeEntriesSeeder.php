<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Organisation;
use App\Models\BudgetBand;
use App\Models\Province;
use App\Models\EducationLevel;
use App\Models\ActivityItem;
use App\Models\ProgrammeEntry;

class ProgrammeEntriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ddspOrg = Organisation::where('name', 'like', '%DDSP%')
            ->orWhere('name', 'like', '%Disability Development Services Program%')
            ->first() ?? Organisation::first();

        if (!$ddspOrg) {
            return;
        }

        $pursat = Province::where('province_name', 'Pursat')->first();
        $siemReap = Province::where('province_name', 'Siem Reap')->first();
        $phnomPenh = Province::where('province_name', 'Phnom Penh')->first();
        $battambang = Province::where('province_name', 'Battambang')->first();
        $kampongCham = Province::where('province_name', 'Kampong Cham')->first();

        $under50k = BudgetBand::where('label', 'like', '%Under $50,000%')->first();
        $band50k200k = BudgetBand::where('label', 'like', '%50,000%')->where('label', 'like', '%200,000%')->first();
        $band200k500k = BudgetBand::where('label', 'like', '%200,000%')->where('label', 'like', '%500,000%')->first();

        $kapeOrg = Organisation::where('name', 'like', '%KAPE%')
            ->orWhere('name', 'like', '%PNC%')
            ->first() ?? Organisation::skip(1)->first();

        $primaryLevel = EducationLevel::where('level_name', 'Primary')->first();
        $lowerSecLevel = EducationLevel::where('level_name', 'Lower Secondary')->first();
        $upperSecLevel = EducationLevel::where('level_name', 'Upper Secondary')->first();

        $programmes = [
            // Document 1
            [
                'identity' => [
                    'programme_name' => 'Advocacy and Communication for Change (ACFC)',
                    'organisation_id' => $ddspOrg->id,
                    'budget_band_id' => $under50k?->id,
                    'start_year' => 2020,
                    'end_year' => 2020,
                    'ongoing' => false,
                    'fte_staff' => 5.0,
                    'direct_beneficiaries' => 0,
                    'indirect_beneficiaries' => 0,
                    'method' => 'To increase capacity of rural people with disabilities in communication and advocacy in order to empower them to voice their concerns and claim their rights.',
                    'is_submitted' => true,
                    'is_unverified' => false,
                    'verified_date' => '2020-12-31',
                ],
                'activities' => [
                    ['code' => 'B3.2.02', 'is_primary' => false, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => []],
                    ['code' => 'B3.2.03', 'is_primary' => false, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => []],
                    ['code' => 'B1.1.01', 'is_primary' => false, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => array_filter([$primaryLevel?->id, $lowerSecLevel?->id, $upperSecLevel?->id])],
                    ['code' => 'B5.2.04', 'is_primary' => false, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => []],
                ],
                'geography' => [
                    'province_id' => $pursat?->id,
                    'country' => 'Cambodia',
                ],
                'agreements' => [
                    ['agency' => 'other government ministry', 'inst' => 'Disability Action Council (DAC)', 'status' => 'active', 'nature' => 'informal working arrangement'],
                    ['agency' => 'other government ministry', 'inst' => 'Commune Councils and local authorities', 'status' => 'active', 'nature' => 'informal working arrangement'],
                    ['agency' => 'other government ministry', 'inst' => 'Commune Disability Representatives and Village Disability Representatives', 'status' => 'active', 'nature' => 'informal working arrangement'],
                    ['agency' => 'other government ministry', 'inst' => 'Persons With Disability Foundation (PWDF)', 'status' => 'active', 'nature' => 'informal working arrangement'],
                ],
                'keywords' => [
                    'disability inclusion', 'persons with disabilities', 'advocacy', 'communication', 'disability rights', 'community mobilisation', 'local governance', 'commune investment planning', 'commune councils', 'scholarships', 'livelihood', 'agricultural training', 'capacity building'
                ],
            ],

            // Document 2
            [
                'identity' => [
                    'programme_name' => 'Improving Daily Income of Life of Persons with Disabilities in Pursat',
                    'organisation_id' => $ddspOrg->id,
                    'budget_band_id' => $band50k200k?->id,
                    'start_year' => 2019,
                    'end_year' => 2020,
                    'ongoing' => false,
                    'fte_staff' => 8.0,
                    'direct_beneficiaries' => 0,
                    'indirect_beneficiaries' => 0,
                    'method' => 'Persons with disabilities improved daily income and have appropriate jobs and businesses through acquiring new skills.',
                    'is_submitted' => true,
                    'is_unverified' => false,
                    'verified_date' => '2020-06-30',
                ],
                'activities' => [
                    ['code' => 'B5.4.04', 'is_primary' => true, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => []],
                    ['code' => 'B5.4.05', 'is_primary' => false, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => []],
                    ['code' => 'B5.4.06', 'is_primary' => true, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => []],
                    ['code' => 'B5.4.07', 'is_primary' => true, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => []],
                    ['code' => 'B5.4.12', 'is_primary' => false, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => []],
                    ['code' => 'B5.2.04', 'is_primary' => true, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => []],
                    ['code' => 'B1.3.02', 'is_primary' => false, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => []],
                    ['code' => 'B1.3.03', 'is_primary' => false, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => []],
                    ['code' => 'B3.4.05', 'is_primary' => false, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type A', 'levels' => []],
                ],
                'geography' => [
                    'province_id' => $pursat?->id,
                    'country' => 'Cambodia',
                ],
                'agreements' => [
                    ['agency' => 'other government ministry', 'inst' => 'Department of Social Affairs, Veterans and Youth Rehabilitation', 'status' => 'active', 'nature' => 'informal working arrangement'],
                    ['agency' => 'Provincial Office of Education', 'inst' => 'Department of Education, Youth and Sport', 'status' => 'active', 'nature' => 'informal working arrangement'],
                    ['agency' => 'other government ministry', 'inst' => 'Department of Health, Provincial Referral Hospital and Health Centers', 'status' => 'active', 'nature' => 'informal working arrangement'],
                    ['agency' => 'other government ministry', 'inst' => 'Department of Agriculture', 'status' => 'active', 'nature' => 'informal working arrangement'],
                    ['agency' => 'other government ministry', 'inst' => 'Commune Councils and Disabled Representatives', 'status' => 'active', 'nature' => 'informal working arrangement'],
                ],
                'keywords' => [
                    'TVET', 'youth with disabilities', 'vocational training', 'inclusive training center', 'sewing', 'cooking', 'motorbike repair', 'computer skills', 'English language', 'employment', 'job placement', 'entrepreneurship', 'financial literacy', 'income generation', 'self-help groups', 'accessible infrastructure', 'disability inclusion'
                ],
            ],

            // Document 3
            [
                'identity' => [
                    'programme_name' => 'Improving Daily Income of Life of Persons with Disabilities in Pursat (Phase 2)',
                    'organisation_id' => $ddspOrg->id,
                    'budget_band_id' => $band50k200k?->id,
                    'start_year' => 2019,
                    'end_year' => 2020,
                    'ongoing' => false,
                    'fte_staff' => 8.0,
                    'direct_beneficiaries' => 0,
                    'indirect_beneficiaries' => 0,
                    'method' => 'Persons with disabilities improved daily income and have appropriate jobs and businesses through acquiring new skills.',
                    'is_submitted' => true,
                    'is_unverified' => false,
                    'verified_date' => '2020-06-30',
                ],
                'activities' => [
                    ['code' => 'B5.4.04', 'is_primary' => true, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => []],
                    ['code' => 'B5.4.05', 'is_primary' => false, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => []],
                    ['code' => 'B5.4.06', 'is_primary' => true, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => []],
                    ['code' => 'B5.4.07', 'is_primary' => true, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => []],
                    ['code' => 'B5.4.12', 'is_primary' => false, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => []],
                    ['code' => 'B5.2.04', 'is_primary' => true, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => []],
                    ['code' => 'B1.3.02', 'is_primary' => false, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => []],
                    ['code' => 'B1.3.03', 'is_primary' => false, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => []],
                    ['code' => 'B3.4.05', 'is_primary' => false, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type A', 'levels' => []],
                ],
                'geography' => [
                    'province_id' => $pursat?->id,
                    'country' => 'Cambodia',
                ],
                'agreements' => [
                    ['agency' => 'other government ministry', 'inst' => 'Department of Social Affairs, Veterans and Youth Rehabilitation', 'status' => 'active', 'nature' => 'informal working arrangement'],
                    ['agency' => 'Provincial Office of Education', 'inst' => 'Department of Education, Youth and Sport', 'status' => 'active', 'nature' => 'informal working arrangement'],
                    ['agency' => 'other government ministry', 'inst' => 'Department of Health, Provincial Referral Hospital and Health Centers', 'status' => 'active', 'nature' => 'informal working arrangement'],
                    ['agency' => 'other government ministry', 'inst' => 'Department of Agriculture', 'status' => 'active', 'nature' => 'informal working arrangement'],
                    ['agency' => 'other government ministry', 'inst' => 'Commune Councils and Disabled Representatives', 'status' => 'active', 'nature' => 'informal working arrangement'],
                ],
                'keywords' => [
                    'TVET', 'youth with disabilities', 'vocational training', 'inclusive training center', 'sewing', 'cooking', 'motorbike repair', 'computer skills', 'English language', 'employment', 'job placement', 'entrepreneurship', 'financial literacy', 'income generation', 'self-help groups', 'accessible infrastructure', 'disability inclusion'
                ],
            ],

            // Document 4
            [
                'identity' => [
                    'programme_name' => 'Mith Komar Pikar Project (Friend of Disabled Children)',
                    'organisation_id' => $ddspOrg->id,
                    'budget_band_id' => $under50k?->id,
                    'start_year' => 2019,
                    'end_year' => 2019,
                    'ongoing' => false,
                    'fte_staff' => 10.0,
                    'direct_beneficiaries' => 196,
                    'indirect_beneficiaries' => 372,
                    'method' => 'Educational and rehabilitation needs of children with disabilities and socio-economic needs of their families.',
                    'is_submitted' => true,
                    'is_unverified' => false,
                    'verified_date' => '2019-12-31',
                ],
                'activities' => [
                    ['code' => 'B3.7.01', 'is_primary' => true, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => array_filter([$primaryLevel?->id])],
                    ['code' => 'B2.2.05', 'is_primary' => true, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => array_filter([$primaryLevel?->id])],
                    ['code' => 'B2.2.06', 'is_primary' => true, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => array_filter([$primaryLevel?->id])],
                    ['code' => 'B2.3.02', 'is_primary' => false, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => array_filter([$primaryLevel?->id])],
                    ['code' => 'B1.2.01', 'is_primary' => false, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => array_filter([$primaryLevel?->id])],
                    ['code' => 'B1.2.03', 'is_primary' => false, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => array_filter([$primaryLevel?->id])],
                    ['code' => 'B1.6.01', 'is_primary' => true, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => array_filter([$primaryLevel?->id])],
                    ['code' => 'B3.3.02', 'is_primary' => false, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => array_filter([$primaryLevel?->id])],
                    ['code' => 'B3.4.02', 'is_primary' => false, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type A', 'levels' => array_filter([$primaryLevel?->id])],
                    ['code' => 'B3.4.05', 'is_primary' => false, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type A', 'levels' => array_filter([$primaryLevel?->id])],
                    ['code' => 'B3.2.02', 'is_primary' => false, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => array_filter([$primaryLevel?->id])],
                    ['code' => 'B6.1.08', 'is_primary' => false, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => []],
                ],
                'geography' => [
                    'province_id' => $pursat?->id,
                    'country' => 'Cambodia',
                ],
                'agreements' => [
                    ['agency' => 'Provincial Office of Education', 'inst' => 'Provincial Department of Education, Youth and Sport', 'status' => 'active', 'nature' => 'official approval letter'],
                    ['agency' => 'specific school or cluster', 'inst' => 'Local School Directors and government primary schools', 'status' => 'active', 'nature' => 'informal working arrangement'],
                    ['agency' => 'other government ministry', 'inst' => 'Pursat Hospital', 'status' => 'active', 'nature' => 'informal working arrangement'],
                ],
                'keywords' => [
                    'inclusive education', 'children with disabilities', 'intellectual disability', 'special education', 'integrated classes', 'primary education', 'teacher training', 'school accessibility', 'school ramps', 'adapted latrines', 'school enrolment', 'transportation', 'learning materials', 'rehabilitation', 'physiotherapy', 'cerebral palsy', 'daycare', 'disability inclusion'
                ],
            ],

            // Document 5
            [
                'identity' => [
                    'programme_name' => 'Paraplegic and Quadriplegic Rehabilitation',
                    'organisation_id' => $ddspOrg->id,
                    'budget_band_id' => $band50k200k?->id,
                    'start_year' => 2018,
                    'end_year' => 2020,
                    'ongoing' => false,
                    'fte_staff' => 6.0,
                    'direct_beneficiaries' => 120,
                    'indirect_beneficiaries' => 1445,
                    'method' => 'To improve quality of life of persons with paraplegic and quadriplegic disabilities through access to health, rehabilitation, livelihood and social inclusion.',
                    'is_submitted' => true,
                    'is_unverified' => false,
                    'verified_date' => '2020-11-30',
                ],
                'activities' => [
                    ['code' => 'B6.1.03', 'is_primary' => true, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => []],
                    ['code' => 'B6.1.08', 'is_primary' => true, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => []],
                    ['code' => 'B5.2.04', 'is_primary' => false, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => []],
                    ['code' => 'B3.2.02', 'is_primary' => false, 'inclusion_group' => 'disability', 'inclusion_type' => 'Type B', 'levels' => []],
                ],
                'geography' => [
                    'province_id' => $pursat?->id,
                    'country' => 'Cambodia',
                ],
                'agreements' => [
                    ['agency' => 'other government ministry', 'inst' => 'Department of Social Affairs, Veterans and Youth Rehabilitation', 'status' => 'active', 'nature' => 'informal working arrangement'],
                    ['agency' => 'other government ministry', 'inst' => 'Department of Health', 'status' => 'active', 'nature' => 'informal working arrangement'],
                    ['agency' => 'Provincial Office of Education', 'inst' => 'Department of Education, Youth and Sport', 'status' => 'active', 'nature' => 'informal working arrangement'],
                    ['agency' => 'other government ministry', 'inst' => 'Local authorities, village chiefs and commune chiefs', 'status' => 'active', 'nature' => 'informal working arrangement'],
                ],
                'keywords' => [
                    'paraplegia', 'quadriplegia', 'rehabilitation', 'physiotherapy', 'assistive devices', 'wheelchairs', 'mobility support', 'health referral', 'home visits', 'counselling', 'livelihood', 'revolving fund', 'small business', 'social inclusion', 'disability rights', 'disability awareness'
                ],
            ],

            // KAPE Organisation (orgadmin@example.com) - Submitted Programme 1
            [
                'identity' => [
                    'programme_name' => 'Early Childhood Care and Education Program',
                    'organisation_id' => $kapeOrg?->id ?? $ddspOrg->id,
                    'budget_band_id' => $band200k500k?->id,
                    'start_year' => 2023,
                    'end_year' => null,
                    'ongoing' => true,
                    'fte_staff' => 6.5,
                    'direct_beneficiaries' => 450,
                    'indirect_beneficiaries' => 1200,
                    'method' => 'Promoting early childhood literacy, health screening, and parental engagement in rural communities.',
                    'is_submitted' => true,
                    'is_unverified' => false,
                    'verified_date' => '2024-01-15',
                ],
                'activities' => [
                    ['code' => 'B1.6.01', 'is_primary' => true, 'inclusion_group' => 'gender', 'inclusion_type' => 'girls', 'levels' => array_filter([$primaryLevel?->id])],
                    ['code' => 'B3.3.02', 'is_primary' => false, 'inclusion_group' => 'poverty', 'inclusion_type' => 'IDPoor', 'levels' => array_filter([$primaryLevel?->id])],
                    ['code' => 'B3.2.02', 'is_primary' => false, 'inclusion_group' => null, 'inclusion_type' => null, 'levels' => array_filter([$primaryLevel?->id])],
                ],
                'geography' => [
                    'province_id' => $siemReap?->id,
                    'country' => 'Cambodia',
                ],
                'agreements' => [
                    ['agency' => 'MoEYS national level', 'inst' => 'Ministry of Education, Youth and Sport', 'status' => 'active', 'nature' => 'MoU'],
                    ['agency' => 'Provincial Office of Education', 'inst' => 'Siem Reap Provincial Office of Education', 'status' => 'active', 'nature' => 'official approval letter'],
                ],
                'keywords' => [
                    'early childhood', 'literacy', 'health screening', 'rural education'
                ],
            ],

            // KAPE Organisation (orgadmin@example.com) - Submitted Programme 2
            [
                'identity' => [
                    'programme_name' => 'Youth Digital Skills and Workforce Empowerment',
                    'organisation_id' => $kapeOrg?->id ?? $ddspOrg->id,
                    'budget_band_id' => $band50k200k?->id,
                    'start_year' => 2022,
                    'end_year' => 2025,
                    'ongoing' => false,
                    'fte_staff' => 8.0,
                    'direct_beneficiaries' => 320,
                    'indirect_beneficiaries' => 850,
                    'method' => 'Providing vocational IT training, digital literacy, and job placement assistance for underprivileged youth.',
                    'is_submitted' => true,
                    'is_unverified' => false,
                    'verified_date' => '2024-03-20',
                ],
                'activities' => [
                    ['code' => 'B5.4.04', 'is_primary' => true, 'inclusion_group' => 'youth', 'inclusion_type' => 'at_risk', 'levels' => array_filter([$upperSecLevel?->id])],
                    ['code' => 'B5.4.06', 'is_primary' => true, 'inclusion_group' => null, 'inclusion_type' => null, 'levels' => array_filter([$upperSecLevel?->id])],
                    ['code' => 'B5.4.07', 'is_primary' => false, 'inclusion_group' => null, 'inclusion_type' => null, 'levels' => array_filter([$upperSecLevel?->id])],
                ],
                'geography' => [
                    'province_id' => $phnomPenh?->id,
                    'country' => 'Cambodia',
                ],
                'agreements' => [
                    ['agency' => 'Provincial Office of Education', 'inst' => 'Phnom Penh Department of Education', 'status' => 'active', 'nature' => 'Letter of Understanding'],
                ],
                'keywords' => [
                    'digital skills', 'TVET', 'youth employment', 'workforce'
                ],
            ],

            // ABC Organisation (orgadmin@example.com) - Draft Programme 1
            [
                'identity' => [
                    'programme_name' => 'Primary School STEM and Digital Classroom Initiative',
                    'organisation_id' => $abcOrg?->id ?? $ddspOrg->id,
                    'budget_band_id' => $under50k?->id,
                    'start_year' => 2024,
                    'end_year' => null,
                    'ongoing' => true,
                    'fte_staff' => 4.0,
                    'direct_beneficiaries' => 200,
                    'indirect_beneficiaries' => 500,
                    'method' => 'Equipping public primary schools with basic computer labs and STEM learning materials.',
                    'is_submitted' => false,
                    'is_unverified' => false,
                    'verified_date' => null,
                ],
                'activities' => [
                    ['code' => 'B3.7.05', 'is_primary' => true, 'inclusion_group' => null, 'inclusion_type' => null, 'levels' => array_filter([$primaryLevel?->id])],
                    ['code' => 'B1.2.01', 'is_primary' => false, 'inclusion_group' => null, 'inclusion_type' => null, 'levels' => array_filter([$primaryLevel?->id])],
                ],
                'geography' => [
                    'province_id' => $battambang?->id,
                    'country' => 'Cambodia',
                ],
                'agreements' => [
                    ['agency' => 'specific school or cluster', 'inst' => 'Battambang Primary School Cluster', 'status' => 'under negotiation', 'nature' => 'informal working arrangement'],
                ],
                'keywords' => [
                    'STEM', 'digital learning', 'primary education'
                ],
            ],

            // ABC Organisation (orgadmin@example.com) - Draft Programme 2
            [
                'identity' => [
                    'programme_name' => 'Inclusive Community Library & Reading Clubs',
                    'organisation_id' => $abcOrg?->id ?? $ddspOrg->id,
                    'budget_band_id' => $under50k?->id,
                    'start_year' => 2024,
                    'end_year' => 2026,
                    'ongoing' => true,
                    'fte_staff' => 3.0,
                    'direct_beneficiaries' => 150,
                    'indirect_beneficiaries' => 400,
                    'method' => 'Establishing accessible community reading corners and mobile library services in villages.',
                    'is_submitted' => false,
                    'is_unverified' => false,
                    'verified_date' => null,
                ],
                'activities' => [
                    ['code' => 'B3.2.02', 'is_primary' => true, 'inclusion_group' => null, 'inclusion_type' => null, 'levels' => []],
                    ['code' => 'B1.1.01', 'is_primary' => false, 'inclusion_group' => null, 'inclusion_type' => null, 'levels' => []],
                ],
                'geography' => [
                    'province_id' => $kampongCham?->id,
                    'country' => 'Cambodia',
                ],
                'agreements' => [
                    ['agency' => 'other government ministry', 'inst' => 'Kampong Cham Local Commune Council', 'status' => 'under negotiation', 'nature' => 'informal working arrangement'],
                ],
                'keywords' => [
                    'community library', 'reading clubs', 'literacy'
                ],
            ],
        ];

        foreach ($programmes as $progData) {
            $entry = ProgrammeEntry::firstOrCreate(
                [
                    'organisation_id' => $progData['identity']['organisation_id'],
                    'programme_name' => $progData['identity']['programme_name'],
                ],
                $progData['identity']
            );

            // Seed Activities
            foreach ($progData['activities'] as $act) {
                $item = ActivityItem::where('code', $act['code'])->first();
                if ($item) {
                    $activity = $entry->activities()->firstOrCreate([
                        'activity_item_id' => $item->id,
                    ], [
                        'is_primary' => $act['is_primary'],
                        'inclusion_group' => $act['inclusion_group'],
                        'inclusion_type' => $act['inclusion_type'],
                        'taxonomy_version' => $item->version ?? 'v1',
                    ]);

                    if (!empty($act['levels'])) {
                        foreach ($act['levels'] as $levelId) {
                            $activity->activityLevels()->firstOrCreate([
                                'education_level_id' => $levelId,
                            ]);
                        }
                    }
                }
            }

            // Seed Geography
            if (!empty($progData['geography']['province_id'])) {
                $entry->locations()->firstOrCreate([
                    'province_id' => $progData['geography']['province_id'],
                    'country' => $progData['geography']['country'] ?? null,
                ]);
            }

            // Seed Government Agreements
            foreach ($progData['agreements'] as $agr) {
                $entry->governmentAgreements()->firstOrCreate([
                    'counterpart_agency' => $agr['agency'],
                    'institution_name' => $agr['inst'],
                ], [
                    'status' => $agr['status'],
                    'nature' => $agr['nature'],
                ]);
            }

            // Seed Keywords
            foreach ($progData['keywords'] as $kw) {
                $entry->keywords()->firstOrCreate([
                    'keyword' => $kw,
                ]);
            }
        }
    }
}