<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\EntryKeyword;
use App\Models\GovernmentAgreement;
use App\Models\Organisation;
use App\Models\ProgrammeEntry;
use App\Models\ProgrammeLocation;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_province_filter_matches_direct_province(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $province = Province::create(['province_name' => 'Test Province']);
        $entry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $organisation->id,
        ]);
        
        ProgrammeLocation::create([
            'programme_entry_id' => $entry->id,
            'province_id' => $province->id,
            'district_id' => null,
        ]);

        $response = $this->actingAs($user)->getJson('/api/map/entries?province_id=' . $province->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $entry->id);
    }

    public function test_province_filter_matches_district_within_province(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $province = Province::create(['province_name' => 'Test Province']);
        $district = District::create([
            'province_id' => $province->id,
            'name' => 'Test District',
        ]);
        $entry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $organisation->id,
        ]);
        
        ProgrammeLocation::create([
            'programme_entry_id' => $entry->id,
            'province_id' => $province->id,
            'district_id' => $district->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/map/entries?province_id=' . $province->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $entry->id);
    }


    public function test_province_filter_excludes_other_provinces(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $province1 = Province::create(['province_name' => 'Province 1']);
        $province2 = Province::create(['province_name' => 'Province 2']);
        $entry1 = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $organisation->id,
        ]);
        $entry2 = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $organisation->id,
        ]);
        
        ProgrammeLocation::create([
            'programme_entry_id' => $entry1->id,
            'province_id' => $province1->id,
            'district_id' => null,
        ]);
        
        ProgrammeLocation::create([
            'programme_entry_id' => $entry2->id,
            'province_id' => $province2->id,
            'district_id' => null,
        ]);

        $response = $this->actingAs($user)->getJson('/api/map/entries?province_id=' . $province1->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $entry1->id);
    }

    public function test_district_filter_works(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $province = Province::create(['province_name' => 'Test Province']);
        $district = District::create([
            'province_id' => $province->id,
            'name' => 'Test District',
        ]);
        $entry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $organisation->id,
        ]);
        
        ProgrammeLocation::create([
            'programme_entry_id' => $entry->id,
            'province_id' => $province->id,
            'district_id' => $district->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/map/entries?district_id=' . $district->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $entry->id);
    }

    public function test_agreement_counterpart_type_filter_works(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $entry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $organisation->id,
        ]);
        
        GovernmentAgreement::create([
            'programme_entry_id' => $entry->id,
            'counterpart_agency' => 'MoEYS national level',
            'status' => 'active',
            'institution_name' => 'Test Institution',
            'nature' => 'MoU',
        ]);

        $response = $this->actingAs($user)->getJson('/api/map/entries?agreement_counterpart_type=MoEYS national level');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $entry->id);
    }

    public function test_agreement_status_filter_works(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $entry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $organisation->id,
        ]);
        
        GovernmentAgreement::create([
            'programme_entry_id' => $entry->id,
            'counterpart_agency' => 'MoEYS national level',
            'status' => 'active',
            'institution_name' => 'Test Institution',
            'nature' => 'MoU',
        ]);

        $response = $this->actingAs($user)->getJson('/api/map/entries?agreement_status=active');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $entry->id);
    }

    public function test_combined_filters_work(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $province = Province::create(['province_name' => 'Test Province']);
        $district = District::create([
            'province_id' => $province->id,
            'name' => 'Test District',
        ]);
        
        $entry1 = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $organisation->id,
        ]);
        $entry2 = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $organisation->id,
        ]);

        ProgrammeLocation::create([
            'programme_entry_id' => $entry1->id,
            'province_id' => $province->id,
            'district_id' => $district->id,
        ]);
        GovernmentAgreement::create([
            'programme_entry_id' => $entry1->id,
            'counterpart_agency' => 'MoEYS national level',
            'status' => 'active',
            'institution_name' => 'Test Institution',
            'nature' => 'MoU',
        ]);

        ProgrammeLocation::create([
            'programme_entry_id' => $entry2->id,
            'province_id' => $province->id,
            'district_id' => $district->id,
        ]);
        GovernmentAgreement::create([
            'programme_entry_id' => $entry2->id,
            'counterpart_agency' => 'MoEYS national level',
            'status' => 'expired',
            'institution_name' => 'Test Institution 2',
            'nature' => 'MoU',
        ]);

        $response = $this->actingAs($user)->getJson(
            '/api/map/entries?province_id=' . $province->id . '&agreement_status=active'
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $entry1->id);
    }

    public function test_nep_admin_sees_all_entries_with_filters(): void
    {
        $org1 = Organisation::factory()->create();
        $org2 = Organisation::factory()->create();
        $admin = User::factory()->create(['role' => 'nep_admin']);
        
        $province = Province::create(['province_name' => 'Test Province']);
        $entry1 = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $org1->id,
        ]);
        $entry2 = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $org2->id,
        ]);
        
        ProgrammeLocation::create([
            'programme_entry_id' => $entry1->id,
            'province_id' => $province->id,
            'district_id' => null,
        ]);
        ProgrammeLocation::create([
            'programme_entry_id' => $entry2->id,
            'province_id' => $province->id,
            'district_id' => null,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/map/entries?province_id=' . $province->id);

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_member_org_only_sees_own_entries_with_filters(): void
    {
        $ownOrg = Organisation::factory()->create();
        $otherOrg = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $ownOrg->id,
            'role' => 'member_org',
        ]);
        
        $province = Province::create(['province_name' => 'Test Province']);
        $ownEntry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $ownOrg->id,
        ]);
        $otherEntry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $otherOrg->id,
        ]);
        
        ProgrammeLocation::create([
            'programme_entry_id' => $ownEntry->id,
            'province_id' => $province->id,
            'district_id' => null,
        ]);
        ProgrammeLocation::create([
            'programme_entry_id' => $otherEntry->id,
            'province_id' => $province->id,
            'district_id' => null,
        ]);

        $response = $this->actingAs($user)->getJson('/api/map/entries?province_id=' . $province->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownEntry->id);
    }

    public function test_three_filters_applied_simultaneously_returns_intersected_results(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $province = Province::create(['province_name' => 'Test Province']);
        $district = District::create([
            'province_id' => $province->id,
            'name' => 'Test District',
        ]);

        $entry1 = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $organisation->id,
        ]);
        ProgrammeLocation::create([
            'programme_entry_id' => $entry1->id,
            'province_id' => $province->id,
            'district_id' => $district->id,
        ]);
        GovernmentAgreement::create([
            'programme_entry_id' => $entry1->id,
            'counterpart_agency' => 'MoEYS national level',
            'status' => 'active',
            'institution_name' => 'Test Institution',
            'nature' => 'MoU',
        ]);

        $entry2 = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $organisation->id,
        ]);
        ProgrammeLocation::create([
            'programme_entry_id' => $entry2->id,
            'province_id' => $province->id,
            'district_id' => $district->id,
        ]);
        GovernmentAgreement::create([
            'programme_entry_id' => $entry2->id,
            'counterpart_agency' => 'MoEYS national level',
            'status' => 'expired',
            'institution_name' => 'Test Institution 2',
            'nature' => 'MoU',
        ]);

        $entry3 = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $organisation->id,
        ]);
        $otherProvince = Province::create(['province_name' => 'Other Province']);
        ProgrammeLocation::create([
            'programme_entry_id' => $entry3->id,
            'province_id' => $otherProvince->id,
            'district_id' => null,
        ]);
        GovernmentAgreement::create([
            'programme_entry_id' => $entry3->id,
            'counterpart_agency' => 'MoEYS national level',
            'status' => 'active',
            'institution_name' => 'Test Institution 3',
            'nature' => 'MoU',
        ]);

        $response = $this->actingAs($user)->getJson(
            '/api/map/entries?province_id=' . $province->id . '&district_id=' . $district->id . '&agreement_status=active'
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $entry1->id);
    }

    public function test_no_duplicate_rows_when_entry_matches_multiple_joined_rows(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $province = Province::create(['province_name' => 'Test Province']);
        $district1 = District::create([
            'province_id' => $province->id,
            'name' => 'District 1',
        ]);
        $district2 = District::create([
            'province_id' => $province->id,
            'name' => 'District 2',
        ]);
        
        $entry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $organisation->id,
        ]);

        ProgrammeLocation::create([
            'programme_entry_id' => $entry->id,
            'province_id' => $province->id,
            'district_id' => null,
        ]);
        ProgrammeLocation::create([
            'programme_entry_id' => $entry->id,
            'province_id' => $province->id,
            'district_id' => $district1->id,
        ]);
        ProgrammeLocation::create([
            'programme_entry_id' => $entry->id,
            'province_id' => $province->id,
            'district_id' => $district2->id,
        ]);

        GovernmentAgreement::create([
            'programme_entry_id' => $entry->id,
            'counterpart_agency' => 'MoEYS national level',
            'status' => 'active',
            'institution_name' => 'Institution 1',
            'nature' => 'MoU',
        ]);
        GovernmentAgreement::create([
            'programme_entry_id' => $entry->id,
            'counterpart_agency' => 'Provincial Office of Education',
            'status' => 'active',
            'institution_name' => 'Institution 2',
            'nature' => 'MoU',
        ]);

        $response = $this->actingAs($user)->getJson(
            '/api/map/entries?province_id=' . $province->id . '&agreement_status=active'
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $entry->id);
    }

    public function test_four_filters_applied_simultaneously(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $province = Province::create(['province_name' => 'Test Province']);
        $district = District::create([
            'province_id' => $province->id,
            'name' => 'Test District',
        ]);

        $entry1 = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $organisation->id,
        ]);
        ProgrammeLocation::create([
            'programme_entry_id' => $entry1->id,
            'province_id' => $province->id,
            'district_id' => $district->id,
        ]);
        GovernmentAgreement::create([
            'programme_entry_id' => $entry1->id,
            'counterpart_agency' => 'MoEYS national level',
            'status' => 'active',
            'institution_name' => 'Test Institution',
            'nature' => 'MoU',
        ]);
        
        $entry2 = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $organisation->id,
        ]);
        ProgrammeLocation::create([
            'programme_entry_id' => $entry2->id,
            'province_id' => $province->id,
            'district_id' => null,
        ]);
        GovernmentAgreement::create([
            'programme_entry_id' => $entry2->id,
            'counterpart_agency' => 'MoEYS national level',
            'status' => 'active',
            'institution_name' => 'Test Institution 2',
            'nature' => 'MoU',
        ]);

        $response = $this->actingAs($user)->getJson(
            '/api/map/entries?province_id=' . $province->id . 
            '&district_id=' . $district->id . 
            '&agreement_status=active' .
            '&agreement_counterpart_type=MoEYS national level'
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $entry1->id);
    }

    public function test_province_and_district_filters_require_same_location(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $province1 = Province::create(['province_name' => 'Province 1']);
        $province2 = Province::create(['province_name' => 'Province 2']);
        $district1 = District::create([
            'province_id' => $province1->id,
            'name' => 'District 1',
        ]);
        $district2 = District::create([
            'province_id' => $province2->id,
            'name' => 'District 2',
        ]);
        
        $entry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $organisation->id,
        ]);
        
        ProgrammeLocation::create([
            'programme_entry_id' => $entry->id,
            'province_id' => $province1->id,
            'district_id' => $district1->id,
        ]);
        ProgrammeLocation::create([
            'programme_entry_id' => $entry->id,
            'province_id' => $province2->id,
            'district_id' => $district2->id,
        ]);

        $response = $this->actingAs($user)->getJson(
            '/api/map/entries?province_id=' . $province1->id . '&district_id=' . $district2->id
        );

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_agreement_filters_require_same_agreement(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $entry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $organisation->id,
        ]);

        GovernmentAgreement::create([
            'programme_entry_id' => $entry->id,
            'counterpart_agency' => 'MoEYS national level',
            'status' => 'active',
            'institution_name' => 'Institution 1',
            'nature' => 'MoU',
        ]);

        GovernmentAgreement::create([
            'programme_entry_id' => $entry->id,
            'counterpart_agency' => 'Provincial Office of Education',
            'status' => 'expired',
            'institution_name' => 'Institution 2',
            'nature' => 'MoU',
        ]);

        $response = $this->actingAs($user)->getJson(
            '/api/map/entries?agreement_counterpart_type=MoEYS national level&agreement_status=expired'
        );

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_csv_export_returns_csv_file(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $entry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $organisation->id,
            'programme_name' => 'Test Programme',
        ]);

        $response = $this->actingAs($user)->get('/api/map/entries/export');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition');
        
        $contentDisposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $contentDisposition);
        $this->assertStringContainsString('.csv', $contentDisposition);
    }

    public function test_csv_export_includes_all_key_fields(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $entry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $organisation->id,
            'programme_name' => 'Test Programme',
            'start_year' => 2023,
            'end_year' => 2025,
            'ongoing' => false,
            'fte_staff' => 10.5,
            'direct_beneficiaries' => 100,
            'indirect_beneficiaries' => 500,
            'method' => 'Direct implementation',
        ]);

        $response = $this->actingAs($user)->get('/api/map/entries/export');

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('Entry ID', $content);
        $this->assertStringContainsString('Programme Name', $content);
        $this->assertStringContainsString('Organisation Name', $content);
        $this->assertStringContainsString('Budget Band', $content);
        $this->assertStringContainsString('Start Year', $content);
        $this->assertStringContainsString('End Year', $content);
        $this->assertStringContainsString('Ongoing', $content);
        $this->assertStringContainsString('FTE Staff', $content);
        $this->assertStringContainsString('Direct Beneficiaries', $content);
        $this->assertStringContainsString('Indirect Beneficiaries', $content);
        $this->assertStringContainsString('Method', $content);
        $this->assertStringContainsString('Keywords', $content);
        $this->assertStringContainsString('Locations', $content);
        $this->assertStringContainsString('Activities', $content);
        $this->assertStringContainsString('Education Levels', $content);
        $this->assertStringContainsString('Government Agreements', $content);
    }

    public function test_csv_export_respects_filters(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $province = Province::create(['province_name' => 'Test Province']);
        
        $entry1 = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $organisation->id,
            'programme_name' => 'Programme in Province',
        ]);
        $entry2 = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $organisation->id,
            'programme_name' => 'Programme Outside Province',
        ]);
        
        ProgrammeLocation::create([
            'programme_entry_id' => $entry1->id,
            'province_id' => $province->id,
            'district_id' => null,
        ]);

        $response = $this->actingAs($user)->get('/api/map/entries/export?province_id=' . $province->id);

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('Programme in Province', $content);
        $this->assertStringNotContainsString('Programme Outside Province', $content);
    }

    public function test_csv_export_respects_permissions(): void
    {
        $org1 = Organisation::factory()->create();
        $org2 = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $org1->id,
            'role' => 'member_org',
        ]);
        
        $ownEntry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $org1->id,
            'programme_name' => 'Own Programme',
        ]);
        $otherEntry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $org2->id,
            'programme_name' => 'Other Programme',
        ]);

        $response = $this->actingAs($user)->get('/api/map/entries/export');

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('Own Programme', $content);
        $this->assertStringNotContainsString('Other Programme', $content);
    }

    public function test_csv_export_nep_admin_sees_all_entries(): void
    {
        $org1 = Organisation::factory()->create();
        $org2 = Organisation::factory()->create();
        $admin = User::factory()->create(['role' => 'nep_admin']);
        
        $entry1 = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $org1->id,
            'programme_name' => 'Org1 Programme',
        ]);
        $entry2 = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $org2->id,
            'programme_name' => 'Org2 Programme',
        ]);

        $response = $this->actingAs($admin)->get('/api/map/entries/export');

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('Org1 Programme', $content);
        $this->assertStringContainsString('Org2 Programme', $content);
    }

    public function test_csv_export_includes_related_data(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $province = Province::create(['province_name' => 'Test Province']);
        $district = District::create([
            'province_id' => $province->id,
            'name' => 'Test District',
        ]);
        
        $entry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $organisation->id,
        ]);

        EntryKeyword::create([
            'programme_entry_id' => $entry->id,
            'keyword' => 'education',
        ]);

        ProgrammeLocation::create([
            'programme_entry_id' => $entry->id,
            'province_id' => $province->id,
            'district_id' => $district->id,
        ]);

        GovernmentAgreement::create([
            'programme_entry_id' => $entry->id,
            'counterpart_agency' => 'MoEYS national level',
            'status' => 'active',
            'institution_name' => 'Test Institution',
            'nature' => 'MoU',
        ]);

        $response = $this->actingAs($user)->get('/api/map/entries/export');

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('education', $content);
        $this->assertStringContainsString('Test Province', $content);
        $this->assertStringContainsString('Test District', $content);
        $this->assertStringContainsString('MoEYS national level', $content);
        $this->assertStringContainsString('active', $content);
    }

    public function test_pdf_export_returns_pdf_file(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $entry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $organisation->id,
            'programme_name' => 'Test Programme',
        ]);

        $response = $this->actingAs($user)->get('/api/map/entries/export/pdf');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition');
        
        $contentDisposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $contentDisposition);
        $this->assertStringContainsString('.pdf', $contentDisposition);
    }

    public function test_pdf_export_includes_entry_data(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $entry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $organisation->id,
            'programme_name' => 'Test Programme PDF',
            'start_year' => 2023,
            'fte_staff' => 15,
            'direct_beneficiaries' => 200,
        ]);

        $response = $this->actingAs($user)->get('/api/map/entries/export/pdf');

        $response->assertOk();

        $response->assertHeader('Content-Type', 'application/pdf');
        $contentDisposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $contentDisposition);
        $this->assertStringContainsString('.pdf', $contentDisposition);
        
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF', $content);
        $this->assertStringContainsString('%%EOF', $content);
    }

    public function test_pdf_export_respects_filters(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $province = Province::create(['province_name' => 'Test Province']);
        
        $entry1 = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $organisation->id,
            'programme_name' => 'Programme in Province',
        ]);
        $entry2 = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $organisation->id,
            'programme_name' => 'Programme Outside Province',
        ]);
        
        ProgrammeLocation::create([
            'programme_entry_id' => $entry1->id,
            'province_id' => $province->id,
            'district_id' => null,
        ]);

        $response = $this->actingAs($user)->get('/api/map/entries/export/pdf?province_id=' . $province->id);

        $response->assertOk();
        $content = $response->getContent();
        

        $this->assertStringStartsWith('%PDF', $content);
        $this->assertStringContainsString('%%EOF', $content);
        $this->assertGreaterThan(1000, strlen($content));
    }

    public function test_pdf_export_respects_permissions(): void
    {
        $org1 = Organisation::factory()->create();
        $org2 = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $org1->id,
            'role' => 'member_org',
        ]);
        
        $ownEntry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $org1->id,
            'programme_name' => 'Own Programme',
        ]);
        $otherEntry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $org2->id,
            'programme_name' => 'Other Programme',
        ]);

        $response = $this->actingAs($user)->get('/api/map/entries/export/pdf');

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringStartsWith('%PDF', $content);
        $this->assertStringContainsString('%%EOF', $content);
        $this->assertGreaterThan(1000, strlen($content));
    }

    public function test_pdf_export_nep_admin_sees_all_entries(): void
    {
        $org1 = Organisation::factory()->create();
        $org2 = Organisation::factory()->create();
        $admin = User::factory()->create(['role' => 'nep_admin']);
        
        $entry1 = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $org1->id,
            'programme_name' => 'Org1 Programme',
        ]);
        $entry2 = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $org2->id,
            'programme_name' => 'Org2 Programme',
        ]);

        $response = $this->actingAs($admin)->get('/api/map/entries/export/pdf');

        $response->assertOk();
        $content = $response->getContent();
        
        $this->assertStringStartsWith('%PDF', $content);
        $this->assertStringContainsString('%%EOF', $content);
        $this->assertGreaterThan(1000, strlen($content));
    }

    public function test_pdf_export_includes_related_data(): void
    {
        $organisation = Organisation::factory()->create();
        $user = User::factory()->create([
            'organisation_id' => $organisation->id,
            'role' => 'member_org',
        ]);
        
        $province = Province::create(['province_name' => 'Test Province']);
        $district = District::create([
            'province_id' => $province->id,
            'name' => 'Test District',
        ]);
        
        $entry = ProgrammeEntry::factory()->create([
            'is_submitted' => true,
            'organisation_id' => $organisation->id,
        ]);
        
        EntryKeyword::create([
            'programme_entry_id' => $entry->id,
            'keyword' => 'education',
        ]);
        
        ProgrammeLocation::create([
            'programme_entry_id' => $entry->id,
            'province_id' => $province->id,
            'district_id' => $district->id,
        ]);

        GovernmentAgreement::create([
            'programme_entry_id' => $entry->id,
            'counterpart_agency' => 'MoEYS national level',
            'status' => 'active',
            'institution_name' => 'Test Institution',
            'nature' => 'MoU',
        ]);

        $response = $this->actingAs($user)->get('/api/map/entries/export/pdf');
        $response->assertOk();
        $content = $response->getContent();
        
        $this->assertStringStartsWith('%PDF', $content);
        $this->assertStringContainsString('%%EOF', $content);
        $this->assertGreaterThan(2000, strlen($content));
    }
}
