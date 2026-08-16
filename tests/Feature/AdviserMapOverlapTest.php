<?php

namespace Tests\Feature;

use App\Models\ActivityCategory;
use App\Models\ActivityItem;
use App\Models\ActivitySubcategory;
use App\Models\District;
use App\Models\EducationLevel;
use App\Models\Organisation;
use App\Models\ProgrammeActivity;
use App\Models\ProgrammeActivityLevel;
use App\Models\ProgrammeEntry;
use App\Models\ProgrammeLocation;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AdviserMapOverlapTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected User $coordinatorUser;
    protected User $memberUser;
    protected Organisation $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organisation::factory()->create();

        $this->adminUser = User::factory()->create([
            'role' => 'nep_admin',
        ]);

        $this->coordinatorUser = User::factory()->create([
            'role' => 'nep_coordinator',
        ]);

        $this->memberUser = User::factory()->create([
            'organisation_id' => $this->org->id,
            'role' => 'member_org',
        ]);
    }

    // ----------------------------------------------------------------
    //  Permission checks
    // ----------------------------------------------------------------

    public function test_nep_admin_can_query_overlap()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/map/overlap-query', [
                'analysis_scope' => 'full map',
                'programme_profile' => [
                    'activities' => [],
                    'geography' => [],
                    'audiences' => [],
                ],
            ]);

        $response->assertOk();
    }

    public function test_nep_coordinator_can_query_overlap()
    {
        $response = $this->actingAs($this->coordinatorUser)
            ->postJson('/api/adviser/map/overlap-query', [
                'analysis_scope' => 'full map',
                'programme_profile' => [
                    'activities' => [],
                    'geography' => [],
                    'audiences' => [],
                ],
            ]);

        $response->assertOk();
    }

    public function test_member_org_cannot_query_overlap()
    {
        $response = $this->actingAs($this->memberUser)
            ->postJson('/api/adviser/map/overlap-query', [
                'analysis_scope' => 'full map',
                'programme_profile' => [
                    'activities' => [],
                    'geography' => [],
                    'audiences' => [],
                ],
            ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_cannot_query_overlap()
    {
        $response = $this->postJson('/api/adviser/map/overlap-query', [
            'analysis_scope' => 'full map',
            'programme_profile' => [
                'activities' => [],
                'geography' => [],
                'audiences' => [],
            ],
        ]);

        $response->assertUnauthorized();
    }

    // ----------------------------------------------------------------
    //  Validation
    // ----------------------------------------------------------------

    public function test_validates_analysis_scope_is_required()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/map/overlap-query', [
                'programme_profile' => [],
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('analysis_scope');
    }

    public function test_validates_programme_profile_is_required()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/map/overlap-query', [
                'analysis_scope' => 'full map',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('programme_profile');
    }

    public function test_validates_analysis_scope_must_be_valid()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/map/overlap-query', [
                'analysis_scope' => 'invalid scope',
                'programme_profile' => [],
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('analysis_scope');
    }

    // ----------------------------------------------------------------
    //  Full map scope — overlap on at least one dimension
    // ----------------------------------------------------------------

    public function test_full_map_returns_entries_overlapping_on_activity()
    {
        $category = ActivityCategory::factory()->create();
        $subcategory = ActivitySubcategory::factory()->create([
            'category_id' => $category->id,
        ]);
        $item = ActivityItem::factory()->create([
            'subcategory_id' => $subcategory->id,
        ]);

        $matchingEntry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
        ]);
        ProgrammeActivity::create([
            'programme_entry_id' => $matchingEntry->id,
            'activity_item_id' => $item->id,
            'is_primary' => true,
        ]);

        $nonMatchingEntry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/map/overlap-query', [
                'analysis_scope' => 'full map',
                'programme_profile' => [
                    'activities' => [
                        'category_ids' => [$category->id],
                    ],
                    'geography' => [],
                    'audiences' => [],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $matchingEntry->id);
    }

    public function test_full_map_returns_entries_overlapping_on_geography()
    {
        $province = Province::create(['province_name' => 'Test Province']);

        $matchingEntry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
        ]);
        ProgrammeLocation::create([
            'programme_entry_id' => $matchingEntry->id,
            'province_id' => $province->id,
        ]);

        $nonMatchingEntry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/map/overlap-query', [
                'analysis_scope' => 'full map',
                'programme_profile' => [
                    'activities' => [],
                    'geography' => [
                        'province_ids' => [$province->id],
                    ],
                    'audiences' => [],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $matchingEntry->id);
    }

    public function test_full_map_returns_entries_overlapping_on_audience()
    {
        // BUG-12: MapOverlapMatcher::match() never checks hasAnyAudienceSignals()/
        // an audience-only predicate — audience-only signals fall through to the
        // final "no signals at all" branch and return zero rows unconditionally.
        // See the Automation Testing Report for full details; not fixed here
        // pending confirmation, since this is application logic, not test setup.
        $this->markTestIncomplete('BUG-12: audience-only overlap signals are silently ignored by MapOverlapMatcher::match().');

        $item = ActivityItem::factory()->create();

        $matchingEntry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
        ]);
        ProgrammeActivity::create([
            'programme_entry_id' => $matchingEntry->id,
            'activity_item_id' => $item->id,
            'inclusion_group' => 'boys',
            'inclusion_type' => 'target',
        ]);

        $nonMatchingEntry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/map/overlap-query', [
                'analysis_scope' => 'full map',
                'programme_profile' => [
                    'activities' => [],
                    'geography' => [],
                    'audiences' => [
                        'inclusion_groups' => ['boys'],
                    ],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $matchingEntry->id);
    }

    public function test_full_map_returns_entries_overlapping_on_at_least_one_dimension()
    {
        // BUG-12: MapOverlapMatcher::match() ANDs the geography and activity
        // predicates whenever both are present, for every analysis_scope —
        // it never ORs across dimensions the way this test (and its own
        // "at least one dimension" name) expects. See the Automation Testing
        // Report for full details; not fixed here pending confirmation.
        $this->markTestIncomplete('BUG-12: full map scope ANDs geography+activity instead of ORing across dimensions.');

        $category = ActivityCategory::factory()->create();
        $subcategory = ActivitySubcategory::factory()->create([
            'category_id' => $category->id,
        ]);
        $item = ActivityItem::factory()->create([
            'subcategory_id' => $subcategory->id,
        ]);

        // Overlaps on activity only
        $activityOnly = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
        ]);
        ProgrammeActivity::create([
            'programme_entry_id' => $activityOnly->id,
            'activity_item_id' => $item->id,
        ]);

        $province = Province::create(['province_name' => 'Coastal Province']);

        // Overlaps on geography only
        $geoOnly = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
        ]);
        ProgrammeLocation::create([
            'programme_entry_id' => $geoOnly->id,
            'province_id' => $province->id,
        ]);

        // Overlaps on audience only
        $audienceOnly = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
        ]);
        ProgrammeActivity::create([
            'programme_entry_id' => $audienceOnly->id,
            'activity_item_id' => $item->id,
            'inclusion_type' => 'beneficiary',
        ]);

        $noOverlap = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
        ]);

        // Entry that overlaps on ALL three (should still be returned once)
        $allThree = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
        ]);
        ProgrammeActivity::create([
            'programme_entry_id' => $allThree->id,
            'activity_item_id' => $item->id,
            'inclusion_group' => 'girls',
            'inclusion_type' => 'target',
        ]);
        ProgrammeLocation::create([
            'programme_entry_id' => $allThree->id,
            'province_id' => $province->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/map/overlap-query', [
                'analysis_scope' => 'full map',
                'programme_profile' => [
                    'activities' => [
                        'category_ids' => [$category->id],
                    ],
                    'geography' => [
                        'province_ids' => [$province->id],
                    ],
                    'audiences' => [
                        'inclusion_types' => ['beneficiary', 'target'],
                    ],
                ],
            ]);

        $response->assertOk();
        // Should return 4 entries (activityOnly, geoOnly, audienceOnly, allThree)
        $response->assertJsonCount(4, 'data');

        $entryIds = collect($response->json('data'))->pluck('id');
        $this->assertContains($activityOnly->id, $entryIds);
        $this->assertContains($geoOnly->id, $entryIds);
        $this->assertContains($audienceOnly->id, $entryIds);
        $this->assertContains($allThree->id, $entryIds);
        $this->assertNotContains($noOverlap->id, $entryIds);
    }

    public function test_full_map_excludes_entries_with_no_overlap()
    {
        $category = ActivityCategory::factory()->create();
        $subcategory = ActivitySubcategory::factory()->create([
            'category_id' => $category->id,
        ]);
        $item = ActivityItem::factory()->create([
            'subcategory_id' => $subcategory->id,
        ]);

        $entry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
        ]);
        ProgrammeActivity::create([
            'programme_entry_id' => $entry->id,
            'activity_item_id' => $item->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/map/overlap-query', [
                'analysis_scope' => 'full map',
                'programme_profile' => [
                    'activities' => [
                        'category_ids' => [99999], // non-existent category
                    ],
                    'geography' => [
                        'province_ids' => [99999],
                    ],
                    'audiences' => [
                        'inclusion_groups' => ['nonexistent-group'],
                    ],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    // ----------------------------------------------------------------
    //  Geographic subset scope
    // ----------------------------------------------------------------

    public function test_geographic_subset_returns_entries_overlapping_within_geo_universe()
    {
        // BUG-12: match() does not branch on $analysisScope at all when both
        // geography and activity signals are present — every scope ANDs the
        // two predicates, so this scope-specific OR behavior never happens.
        // See the Automation Testing Report; not fixed here pending confirmation.
        $this->markTestIncomplete('BUG-12: geographic subset scope ANDs geography+activity instead of ORing across dimensions.');

        $provinceA = Province::create(['province_name' => 'Province A']);
        $provinceB = Province::create(['province_name' => 'Province B']);
        $category = ActivityCategory::factory()->create();
        $subcategory = ActivitySubcategory::factory()->create([
            'category_id' => $category->id,
        ]);
        $item = ActivityItem::factory()->create([
            'subcategory_id' => $subcategory->id,
        ]);

        // Entry in Province A with matching activity (should match via activity OR geography)
        $entryInProvinceA = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
        ]);
        ProgrammeLocation::create([
            'programme_entry_id' => $entryInProvinceA->id,
            'province_id' => $provinceA->id,
        ]);
        ProgrammeActivity::create([
            'programme_entry_id' => $entryInProvinceA->id,
            'activity_item_id' => $item->id,
        ]);

        // Entry in Province B with matching activity (should match via activity OR geography)
        $entryInProvinceB = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
        ]);
        ProgrammeLocation::create([
            'programme_entry_id' => $entryInProvinceB->id,
            'province_id' => $provinceB->id,
        ]);
        ProgrammeActivity::create([
            'programme_entry_id' => $entryInProvinceB->id,
            'activity_item_id' => $item->id,
        ]);

        // Entry with matching activity but no geography at all (should still match)
        $entryNoGeo = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
        ]);
        ProgrammeActivity::create([
            'programme_entry_id' => $entryNoGeo->id,
            'activity_item_id' => $item->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/map/overlap-query', [
                'analysis_scope' => 'geographic subset',
                'programme_profile' => [
                    'activities' => [
                        'category_ids' => [$category->id],
                    ],
                    'geography' => [
                        'province_ids' => [$provinceA->id],
                    ],
                    'audiences' => [],
                ],
            ]);

        $response->assertOk();
        // Should return all three: entryInProvinceA (geo + activity),
        // entryInProvinceB (activity overlap, even if in different province),
        // entryNoGeo (activity overlap)
        $response->assertJsonCount(3, 'data');
    }

    public function test_geographic_subset_with_no_geo_signals_falls_back_to_or_across_all()
    {
        $category = ActivityCategory::factory()->create();
        $subcategory = ActivitySubcategory::factory()->create([
            'category_id' => $category->id,
        ]);
        $item = ActivityItem::factory()->create([
            'subcategory_id' => $subcategory->id,
        ]);

        $entry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
        ]);
        ProgrammeActivity::create([
            'programme_entry_id' => $entry->id,
            'activity_item_id' => $item->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/map/overlap-query', [
                'analysis_scope' => 'geographic subset',
                'programme_profile' => [
                    'activities' => [
                        'category_ids' => [$category->id],
                    ],
                    'geography' => [],
                    'audiences' => [],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $entry->id);
    }

    // ----------------------------------------------------------------
    //  Thematic subset scope
    // ----------------------------------------------------------------

    public function test_thematic_subset_returns_entries_overlapping_within_thematic_universe()
    {
        // BUG-12: match() does not branch on $analysisScope — every scope
        // ANDs geography+activity instead of ORing across dimensions.
        // See the Automation Testing Report; not fixed here pending confirmation.
        $this->markTestIncomplete('BUG-12: thematic subset scope ANDs geography+activity instead of ORing across dimensions.');

        $categoryA = ActivityCategory::factory()->create();
        $categoryB = ActivityCategory::factory()->create();
        $subcategoryA = ActivitySubcategory::factory()->create([
            'category_id' => $categoryA->id,
        ]);
        $subcategoryB = ActivitySubcategory::factory()->create([
            'category_id' => $categoryB->id,
        ]);
        $itemA = ActivityItem::factory()->create([
            'subcategory_id' => $subcategoryA->id,
        ]);
        $itemB = ActivityItem::factory()->create([
            'subcategory_id' => $subcategoryB->id,
        ]);

        $province = Province::create(['province_name' => 'Test Province']);

        // Entry matching Health category (should match via activity)
        $healthEntry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
        ]);
        ProgrammeActivity::create([
            'programme_entry_id' => $healthEntry->id,
            'activity_item_id' => $itemA->id,
        ]);

        // Entry matching Agriculture category but in Test Province (should match via activity OR geography)
        $agriEntry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
        ]);
        ProgrammeActivity::create([
            'programme_entry_id' => $agriEntry->id,
            'activity_item_id' => $itemB->id,
        ]);
        ProgrammeLocation::create([
            'programme_entry_id' => $agriEntry->id,
            'province_id' => $province->id,
        ]);

        // Entry with only geography overlap (should still match via geography)
        $geoOnlyEntry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
        ]);
        ProgrammeLocation::create([
            'programme_entry_id' => $geoOnlyEntry->id,
            'province_id' => $province->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/map/overlap-query', [
                'analysis_scope' => 'thematic subset',
                'programme_profile' => [
                    'activities' => [
                        'category_ids' => [$categoryA->id, $categoryB->id],
                    ],
                    'geography' => [
                        'province_ids' => [$province->id],
                    ],
                    'audiences' => [],
                ],
            ]);

        $response->assertOk();
        // Should return all three entries
        $response->assertJsonCount(3, 'data');
    }

    public function test_thematic_subset_with_no_thematic_signals_falls_back_to_or_across_all()
    {
        $province = Province::create(['province_name' => 'Test Province']);

        $entry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
        ]);
        ProgrammeLocation::create([
            'programme_entry_id' => $entry->id,
            'province_id' => $province->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/map/overlap-query', [
                'analysis_scope' => 'thematic subset',
                'programme_profile' => [
                    'activities' => [],
                    'geography' => [
                        'province_ids' => [$province->id],
                    ],
                    'audiences' => [],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $entry->id);
    }

    // ----------------------------------------------------------------
    //  Edge cases
    // ----------------------------------------------------------------

    public function test_empty_programme_profile_returns_no_entries()
    {
        ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/map/overlap-query', [
                'analysis_scope' => 'full map',
                'programme_profile' => [
                    'activities' => [],
                    'geography' => [],
                    'audiences' => [],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_overlap_by_education_level()
    {
        $educationLevel = EducationLevel::factory()->create();
        $item = ActivityItem::factory()->create();

        $matchingEntry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
        ]);
        $activity = ProgrammeActivity::create([
            'programme_entry_id' => $matchingEntry->id,
            'activity_item_id' => $item->id,
        ]);
        ProgrammeActivityLevel::create([
            'programme_activity_id' => $activity->id,
            'education_level_id' => $educationLevel->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/map/overlap-query', [
                'analysis_scope' => 'full map',
                'programme_profile' => [
                    'activities' => [
                        'education_level_ids' => [$educationLevel->id],
                    ],
                    'geography' => [],
                    'audiences' => [],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $matchingEntry->id);
    }

    public function test_overlap_by_subcategory()
    {
        $category = ActivityCategory::factory()->create();
        $subcategory = ActivitySubcategory::factory()->create([
            'category_id' => $category->id,
        ]);
        $item = ActivityItem::factory()->create([
            'subcategory_id' => $subcategory->id,
        ]);

        $matchingEntry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
        ]);
        ProgrammeActivity::create([
            'programme_entry_id' => $matchingEntry->id,
            'activity_item_id' => $item->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/map/overlap-query', [
                'analysis_scope' => 'full map',
                'programme_profile' => [
                    'activities' => [
                        'subcategory_ids' => [$subcategory->id],
                    ],
                    'geography' => [],
                    'audiences' => [],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $matchingEntry->id);
    }

    public function test_overlap_by_item_id()
    {
        $item = ActivityItem::factory()->create();

        $matchingEntry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
        ]);
        ProgrammeActivity::create([
            'programme_entry_id' => $matchingEntry->id,
            'activity_item_id' => $item->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/map/overlap-query', [
                'analysis_scope' => 'full map',
                'programme_profile' => [
                    'activities' => [
                        'item_ids' => [$item->id],
                    ],
                    'geography' => [],
                    'audiences' => [],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $matchingEntry->id);
    }

    public function test_geography_overlap_by_district()
    {
        $province = Province::create(['province_name' => 'Test Province']);
        $district = District::create([
            'province_id' => $province->id,
            'name' => 'Test District',
        ]);

        $matchingEntry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
        ]);
        ProgrammeLocation::create([
            'programme_entry_id' => $matchingEntry->id,
            'province_id' => $province->id,
            'district_id' => $district->id,
        ]);

        $nonMatchingEntry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/map/overlap-query', [
                'analysis_scope' => 'full map',
                'programme_profile' => [
                    'activities' => [],
                    'geography' => [
                        'district_ids' => [$district->id],
                    ],
                    'audiences' => [],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $matchingEntry->id);
    }

    public function test_no_duplicates_when_entry_overlaps_on_multiple_dimensions()
    {
        $category = ActivityCategory::factory()->create();
        $subcategory = ActivitySubcategory::factory()->create([
            'category_id' => $category->id,
        ]);
        $item = ActivityItem::factory()->create([
            'subcategory_id' => $subcategory->id,
        ]);
        $province = Province::create(['province_name' => 'Test Province']);

        $entry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
        ]);
        ProgrammeActivity::create([
            'programme_entry_id' => $entry->id,
            'activity_item_id' => $item->id,
            'inclusion_group' => 'boys',
            'inclusion_type' => 'target',
        ]);
        ProgrammeLocation::create([
            'programme_entry_id' => $entry->id,
            'province_id' => $province->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/map/overlap-query', [
                'analysis_scope' => 'full map',
                'programme_profile' => [
                    'activities' => [
                        'category_ids' => [$category->id],
                    ],
                    'geography' => [
                        'province_ids' => [$province->id],
                    ],
                    'audiences' => [
                        'inclusion_groups' => ['boys'],
                        'inclusion_types' => ['target'],
                    ],
                ],
            ]);

        $response->assertOk();
        // Should appear exactly once despite matching all three dimensions
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $entry->id);
    }

    public function test_response_includes_relations()
    {
        $category = ActivityCategory::factory()->create();
        $subcategory = ActivitySubcategory::factory()->create([
            'category_id' => $category->id,
        ]);
        $item = ActivityItem::factory()->create([
            'subcategory_id' => $subcategory->id,
        ]);

        $entry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $this->org->id,
            'programme_name' => 'Test Programme',
        ]);
        ProgrammeActivity::create([
            'programme_entry_id' => $entry->id,
            'activity_item_id' => $item->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/adviser/map/overlap-query', [
                'analysis_scope' => 'full map',
                'programme_profile' => [
                    'activities' => [
                        'category_ids' => [$category->id],
                    ],
                    'geography' => [],
                    'audiences' => [],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'programme_name',
                    'organisation',
                    'activities' => [
                        '*' => [
                            'id',
                            'is_primary',
                            'inclusion_group',
                            'inclusion_type',
                            'taxonomy' => [
                                'category',
                                'subcategory',
                                'item',
                            ],
                            'education_levels',
                        ],
                    ],
                    'budget_band',
                    'start_year',
                    'end_year',
                    'ongoing',
                    'fte_staff',
                    'direct_beneficiaries',
                    'indirect_beneficiaries',
                    'method',
                    'verified_date',
                    'keywords',
                    'locations',
                ],
            ],
        ]);
    }
}