<?php

namespace Database\Seeders;

use App\Models\ActivityCategory;
use App\Models\ActivityItem;
use App\Models\ActivitySubcategory;
use App\Models\EducationLevel;
use App\Models\ProgrammeActivity;
use App\Models\ProgrammeActivityLevel;
use App\Models\ProgrammeEntry;
use App\Models\ProgrammeLocation;
use App\Models\Province;
use Illuminate\Database\Seeder;

class AdviserTestDataSeeder extends Seeder
{

    public function run(): void
    {
        // Get existing taxonomy data
        $category = ActivityCategory::first();
        if (! $category) {
            $this->command->error('No taxonomy categories found. Run TaxonomySeeder first.');
            return;
        }

        $subcategory = ActivitySubcategory::where('category_id', $category->id)->first();
        if (! $subcategory) {
            $this->command->error('No subcategories found for category ' . $category->id);
            return;
        }

        $item = ActivityItem::where('subcategory_id', $subcategory->id)->first();
        if (! $item) {
            $this->command->error('No activity items found for subcategory ' . $subcategory->id);
            return;
        }

        $educationLevel = EducationLevel::first();
        if (! $educationLevel) {
            $this->command->error('No education levels found.');
            return;
        }

        $province = Province::first();
        if (! $province) {
            $this->command->error('No provinces found.');
            return;
        }

        $entries = ProgrammeEntry::all();
        if ($entries->isEmpty()) {
            $this->command->error('No programme entries found.');
            return;
        }

        $this->command->info("Found: Category={$category->id}, Subcategory={$subcategory->id}, Item={$item->id}, EducationLevel={$educationLevel->id}, Province={$province->id}");

        foreach ($entries as $entry) {
            // Create an activity for each entry
            $activity = ProgrammeActivity::create([
                'programme_entry_id' => $entry->id,
                'activity_item_id' => $item->id,
                'is_primary' => true,
                'inclusion_group' => 'boys',
                'inclusion_type' => 'target',
                'taxonomy_version' => '1.0',
            ]);

            // Link education level to the activity
            ProgrammeActivityLevel::create([
                'programme_activity_id' => $activity->id,
                'education_level_id' => $educationLevel->id,
            ]);

            // Create a location for each entry
            ProgrammeLocation::create([
                'programme_entry_id' => $entry->id,
                'province_id' => $province->id,
            ]);

            $this->command->info("  Linked entry {$entry->id} -> activity {$activity->id}, province {$province->id}, education_level {$educationLevel->id}");
        }

        $this->command->info('Done! ' . $entries->count() . ' entries now have activities and locations.');
    }
}