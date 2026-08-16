<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdvisoryNote;
use App\Models\Organisation;
use App\Models\ProgrammeEntry;
use App\Support\AdvisoryNoteStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "DashboardStats",
    type: "object",
    properties: [
        new OA\Property(property: "total_organizations", type: "integer", example: 42),
        new OA\Property(property: "total_program_entries", type: "integer", example: 156),
        new OA\Property(property: "unverified_program_entries", type: "integer", example: 12),
        new OA\Property(property: "coordinator_advisory_notes", type: "integer", nullable: true, example: 5),
        new OA\Property(property: "total_advisory_notes", type: "integer", example: 28),
    ]
)]
class DashboardController extends Controller
{
    #[OA\Get(
        path: "/dashboard/stats",
        summary: "Get dashboard statistics",
        description: "Returns aggregate counts for the dashboard. Accessible to NEP Admins and Coordinators. For admins, coordinator_advisory_notes is null.",
        security: [["bearerAuth" => []]],
        tags: ["Dashboard"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Dashboard statistics retrieved successfully",
                content: new OA\JsonContent(ref: "#/components/schemas/DashboardStats")
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden — not a nep_admin or nep_coordinator"),
        ]
    )]
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user->isNepAdmin();

        $shared = Cache::remember('dashboard:stats:shared', 30, function () {
            return [
                'total_organizations'       => Organisation::count(),
                'total_program_entries'     => ProgrammeEntry::where('is_submitted', true)->count(),
                'unverified_program_entries'=> ProgrammeEntry::where('is_unverified', true)->count(),
                'total_advisory_notes'      => AdvisoryNote::count(),
            ];
        });

        $coordinatorAdvisoryNotes = $isAdmin
            ? null
            : Cache::remember("dashboard:stats:coordinator:{$user->id}", 30, fn () =>
                AdvisoryNote::where('coordinator_id', $user->id)->count()
              );

        return response()->json(array_merge($shared, [
            'coordinator_advisory_notes' => $coordinatorAdvisoryNotes,
        ]));
    }

    public function recentActivity(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user->isNepAdmin();
        $cacheKey = $isAdmin ? 'dashboard:recent:admin' : "dashboard:recent:coordinator:{$user->id}";

        $activity = Cache::remember($cacheKey, 30, function () use ($user, $isAdmin) {
            // 1. Advisory notes delivered
            // BUG-06: the only writer of this status (AdviserSubmissionController::
            // markDelivered) sets 'advice_delivered', never the bare 'delivered'
            // this used to query for — no row could ever match, so this half of
            // the feed was silently always empty. Now routed through the shared
            // AdvisoryNoteStatus constant so the two sides can't drift apart again.
            $notesQuery = AdvisoryNote::where('status', AdvisoryNoteStatus::ADVICE_DELIVERED);
            if (!$isAdmin) {
                $notesQuery->where('coordinator_id', $user->id);
            }
            $notes = $notesQuery->orderBy('delivered_at', 'desc')
                ->limit(10)
                ->get()
                ->map(fn($note) => [
                    'type'        => 'advisory_note',
                    'id'          => $note->id,
                    'label'       => $note->submitting_party,
                    'occurred_at' => $note->delivered_at,
                ]);

            // 2. New submitted entries — join advisory_notes in DB instead of loading all into PHP
            // BUG-10: this used to join on `an.submitting_party = organisations.name`
            // — a free-text field explicitly documented elsewhere as "not
            // linked to the member database" — so a typo, casing difference,
            // or shared name between two organisations produced a wrong or
            // missing advisory_note_id. programme_entry_id is a real FK and
            // always correct.
            $newEntries = ProgrammeEntry::select(
                    'programme_entries.id',
                    'programme_entries.programme_name',
                    'programme_entries.created_at',
                    'organisations.name as org_name',
                    'an.id as advisory_note_id'
                )
                ->join('organisations', 'organisations.id', '=', 'programme_entries.organisation_id')
                ->leftJoin('advisory_notes as an', function ($join) {
                    $join->on('an.programme_entry_id', '=', 'programme_entries.id')
                         ->whereIn('an.status', [
                             AdvisoryNoteStatus::SUBMITTED_FOR_REVIEW,
                             AdvisoryNoteStatus::ANALYSED,
                         ]);
                })
                ->where('programme_entries.is_submitted', true)
                ->whereColumn('programme_entries.last_updated_at', '<=', 'programme_entries.created_at')
                ->orderBy('programme_entries.created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(fn($e) => [
                    'type'             => 'programme_new',
                    'id'               => $e->id,
                    'label'            => $e->org_name ?? 'Unknown',
                    'programme'        => $e->programme_name,
                    'advisory_note_id' => $e->advisory_note_id,
                    'occurred_at'      => $e->created_at,
                ]);

            // 3. Updated submitted entries
            $updatedEntries = ProgrammeEntry::select(
                    'programme_entries.id',
                    'programme_entries.programme_name',
                    'programme_entries.last_updated_at',
                    'organisations.name as org_name'
                )
                ->join('organisations', 'organisations.id', '=', 'programme_entries.organisation_id')
                ->where('programme_entries.is_submitted', true)
                ->whereColumn('programme_entries.last_updated_at', '>', 'programme_entries.created_at')
                ->orderBy('programme_entries.last_updated_at', 'desc')
                ->limit(10)
                ->get()
                ->map(fn($e) => [
                    'type'        => 'programme_updated',
                    'id'          => $e->id,
                    'label'       => $e->org_name ?? 'Unknown',
                    'programme'   => $e->programme_name,
                    'occurred_at' => $e->last_updated_at,
                ]);

            return collect()
                ->merge($notes)
                ->merge($newEntries)
                ->merge($updatedEntries)
                ->sortByDesc('occurred_at')
                ->values()
                ->take(15)
                ->all();
        });

        return response()->json($activity);
    }
}
