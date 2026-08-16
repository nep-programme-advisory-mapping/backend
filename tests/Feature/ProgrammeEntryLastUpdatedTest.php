<?php

namespace Tests\Feature;

use App\Models\ProgrammeEntry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProgrammeEntryLastUpdatedTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // always unfreeze time after each test
        parent::tearDown();
    }

    protected function createEntryWithState(Carbon $lastUpdatedAt, bool $unverified): ProgrammeEntry
    {
        $entry = ProgrammeEntry::factory()->create();

        DB::table('programme_entries')
            ->where('id', $entry->id)
            ->update([
                'last_updated_at' => $lastUpdatedAt,
                'is_unverified' => $unverified,
            ]);

        return $entry->fresh();
    }

    public function test_entry_older_than_18_months_is_flagged_unverified(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-11'));

        $staleEntry = $this->createEntryWithState(
            now()->subMonths(19),
            unverified: false
        );

        $this->artisan('programme-entries:flag-stale')->assertExitCode(0);

        $this->assertTrue($staleEntry->fresh()->is_unverified);
    }

    public function test_entry_updated_within_18_months_stays_verified(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-11'));

        $recentEntry = $this->createEntryWithState(
            now()->subMonths(6),
            unverified: false
        );

        $this->artisan('programme-entries:flag-stale')->assertExitCode(0);

        $this->assertFalse($recentEntry->fresh()->is_unverified);
    }

    public function test_entry_exactly_at_18_month_boundary_is_not_flagged(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-11'));

        $boundaryEntry = $this->createEntryWithState(
            now()->subMonths(18),
            unverified: false
        );

        $this->artisan('programme-entries:flag-stale')->assertExitCode(0);

        $this->assertFalse($boundaryEntry->fresh()->is_unverified);
    }

    public function test_command_is_idempotent_running_twice_does_not_change_extra_records(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-11'));

        $staleEntry = $this->createEntryWithState(now()->subMonths(20), unverified: false);
        $recentEntry = $this->createEntryWithState(now()->subMonths(2), unverified: false);

        $this->artisan('programme-entries:flag-stale')->assertExitCode(0);

        $this->assertTrue($staleEntry->fresh()->is_unverified);
        $this->assertFalse($recentEntry->fresh()->is_unverified);

        $countAfterFirstRun = ProgrammeEntry::where('is_unverified', true)->count();

        $this->artisan('programme-entries:flag-stale')->assertExitCode(0);

        $this->assertEquals($countAfterFirstRun, ProgrammeEntry::where('is_unverified', true)->count());
        $this->assertTrue($staleEntry->fresh()->is_unverified);
        $this->assertFalse($recentEntry->fresh()->is_unverified);
    }

    public function test_saving_an_entry_clears_the_unverified_flag(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-11'));

        $entry = $this->createEntryWithState(now()->subMonths(20), unverified: true);

        $this->assertTrue($entry->fresh()->is_unverified);

        $entry->programme_name = 'Updated Name';
        $entry->save();

        $this->assertFalse($entry->fresh()->is_unverified);
    }
}