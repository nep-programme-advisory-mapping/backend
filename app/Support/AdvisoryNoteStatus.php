<?php

namespace App\Support;

/**
 * Canonical status values for the `advisory_notes.status` column.
 *
 * These used to be duplicated as raw strings across controllers (and again,
 * independently, across the frontend), which is exactly how BUG-06 happened:
 * DashboardController::recentActivity() queried for a status string
 * ('delivered') that no writer ever produced (AdviserSubmissionController
 * only ever wrote 'advice_delivered'), so that half of the activity feed was
 * silently always empty. Route every read/write through these constants so
 * the two sides can't drift again.
 */
final class AdvisoryNoteStatus
{
    public const SUBMITTED_FOR_REVIEW = 'Submitted for review';
    public const ANALYSED = 'analysed';
    public const ADVICE_DELIVERED = 'advice_delivered';

    public const ALL = [
        self::SUBMITTED_FOR_REVIEW,
        self::ANALYSED,
        self::ADVICE_DELIVERED,
    ];
}
