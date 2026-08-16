<?php

namespace Tests\Unit;

use App\Support\AdvisoryNoteStatus;
use PHPUnit\Framework\TestCase;

/**
 * BUG-06 regression guard, at the unit level: the whole point of these
 * constants is that AdviserSubmissionController/AdviserAnalysisController
 * (writers) and DashboardController (reader) can never drift back to
 * mismatched literal strings. Pinning the literal values here means any
 * accidental edit to AdvisoryNoteStatus is caught immediately, without
 * needing to run a full HTTP feature test.
 */
class AdvisoryNoteStatusTest extends TestCase
{
    public function test_constants_have_the_expected_literal_values(): void
    {
        $this->assertSame('Submitted for review', AdvisoryNoteStatus::SUBMITTED_FOR_REVIEW);
        $this->assertSame('analysed', AdvisoryNoteStatus::ANALYSED);
        $this->assertSame('advice_delivered', AdvisoryNoteStatus::ADVICE_DELIVERED);
    }

    public function test_all_contains_every_defined_status_exactly_once(): void
    {
        $this->assertSame(
            [
                AdvisoryNoteStatus::SUBMITTED_FOR_REVIEW,
                AdvisoryNoteStatus::ANALYSED,
                AdvisoryNoteStatus::ADVICE_DELIVERED,
            ],
            AdvisoryNoteStatus::ALL
        );
        $this->assertCount(3, array_unique(AdvisoryNoteStatus::ALL));
    }
}
