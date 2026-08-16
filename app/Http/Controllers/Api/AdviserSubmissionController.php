<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListAdviserSubmissionRequest;
use App\Http\Requests\StoreAdviserSubmissionRequest;
use App\Http\Requests\UpdateAdviserSubmissionRequest;
use App\Models\AdvisoryNote;
use App\Models\User;
use App\Notifications\AdviserSubmissionAssigned;
use App\Notifications\AdviceDelivered;
use App\Support\AdvisoryNoteStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "AdviserSubmission",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "submitting_party", type: "string", example: "Ministry of Education"),
        new OA\Property(property: "document_name", type: "string", example: "Education Sector Review 2026"),
        new OA\Property(property: "analysis_scope", type: "string", example: "full map", enum: ["full map", "geographic subset", "thematic subset"]),
        new OA\Property(property: "analysis_scope_detail", type: "string", example: "Focus on Phnom Penh and Siem Reap provinces", nullable: true),
        new OA\Property(property: "status", type: "string", example: "Submitted for review", description: "Current status of the submission (e.g. Submitted for review, Adviser Delivered)"),
        new OA\Property(property: "submitted_at", type: "string", format: "date-time", nullable: true),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ]
)]
#[OA\Schema(
    schema: "AdvisoryNoteResponse",
    type: "object",
    properties: [
        new OA\Property(property: "executive_summary", type: "string", example: "The submitted programme shows significant overlap with existing programmes in the education sector..."),
        new OA\Property(
            property: "similar_or_overlapping_programmes",
            type: "array",
            items: new OA\Items(
                properties: [
                    new OA\Property(property: "programme_name", type: "string"),
                    new OA\Property(property: "organisation", type: "string"),
                    new OA\Property(property: "overlap_type", type: "string", enum: ["activity", "geography", "audience", "multiple"]),
                    new OA\Property(property: "description", type: "string"),
                ]
            )
        ),
        new OA\Property(property: "potential_duplication", type: "string", example: "There is potential duplication with Programme X in the area of..."),
        new OA\Property(property: "coverage_gaps", type: "string", example: "The programme could address gaps in..."),
        new OA\Property(property: "recommendations", type: "string", example: "It is recommended to..."),
        new OA\Property(property: "confidence_notes", type: "string", example: "Analysis based on full map scope with 3 overlapping programmes identified.", nullable: true),
    ]
)]
class AdviserSubmissionController extends Controller
{
    #[OA\Post(
        path: "/adviser/submissions",
        summary: "Submit a document for Adviser analysis",
        description: "Allows NEP Coordinators and Admins to submit a document for analysis. The submitting party is captured as free text (not linked to the member database). Analysis scope defaults to 'full map' when not specified.",
        security: [["bearerAuth" => []]],
        tags: ["Adviser"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["submitting_party", "document_name"],
                properties: [
                    new OA\Property(property: "submitting_party", type: "string", example: "Ministry of Education", description: "Name of the submitting party (free text, not linked to member database)"),
                    new OA\Property(property: "document_name", type: "string", example: "Education Sector Review 2026", description: "Name or title of the document being submitted"),
                    new OA\Property(property: "analysis_scope", type: "string", example: "full map", description: "Scope of analysis: 'full map', 'geographic subset', or 'thematic subset'", enum: ["full map", "geographic subset", "thematic subset"]),
                    new OA\Property(property: "analysis_scope_detail", type: "string", example: "Focus on Phnom Penh and Siem Reap provinces", description: "Additional details about the analysis scope (required for geographic or thematic subsets)", nullable: true),
                    new OA\Property(property: "status", type: "string", example: "Submitted for review", description: "Status of the submission (e.g. Submitted for review, Adviser Delivered). Defaults to 'Submitted for review' if not provided.", nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Document submitted successfully for analysis", content: new OA\JsonContent(properties: [new OA\Property(property: "message", type: "string", example: "Document submitted for analysis."), new OA\Property(property: "data", ref: "#/components/schemas/AdviserSubmission")])),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden - Only NEP Coordinators and Admins can submit documents", content: new OA\JsonContent(properties: [new OA\Property(property: "message", type: "string", example: "Forbidden.")])),
            new OA\Response(response: 422, description: "Validation failed", content: new OA\JsonContent(properties: [new OA\Property(property: "message", type: "string", example: "The given data was invalid."), new OA\Property(property: "errors", type: "object")])),
        ]
    )]
    #[OA\Get(
        path: "/adviser/submissions",
        summary: "List Adviser document submissions",
        description: "Returns a paginated list of Adviser document submission records. Only NEP Coordinators and Admins can retrieve submission records. Supports filtering by analysis scope and status.",
        security: [["bearerAuth" => []]],
        tags: ["Adviser"],
        parameters: [
            new OA\Parameter(name: "analysis_scope", in: "query", required: false, description: "Filter by analysis scope", schema: new OA\Schema(type: "string", enum: ["full map", "geographic subset", "thematic subset"])),
            new OA\Parameter(name: "status", in: "query", required: false, description: "Filter by submission status", schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "per_page", in: "query", required: false, description: "Number of records per page (default: 25, max: 100)", schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Submission records retrieved successfully", content: new OA\JsonContent(properties: [new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/AdviserSubmission")), new OA\Property(property: "current_page", type: "integer", example: 1), new OA\Property(property: "last_page", type: "integer", example: 1), new OA\Property(property: "per_page", type: "integer", example: 25), new OA\Property(property: "total", type: "integer", example: 10)])),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden - Only NEP Coordinators and Admins can retrieve submission records", content: new OA\JsonContent(properties: [new OA\Property(property: "message", type: "string", example: "Forbidden.")])),
        ]
    )]
    public function index(ListAdviserSubmissionRequest $request)
    {
        $query = AdvisoryNote::with([
            'coordinator:id,name',
            'programmeEntry:id,programme_name,is_submitted',
        ])->orderBy('submitted_at', 'desc');

        // Exclude advisory notes linked to draft (not yet submitted) programme entries
        $query->where(function ($q) {
            $q->whereNull('programme_entry_id')
              ->orWhereHas('programmeEntry', fn($e) => $e->where('is_submitted', true));
        });

        if ($request->filled('analysis_scope')) {
            $query->whereRaw('analysis_scope = ?', [$request->input('analysis_scope')]);
        }

        if ($request->filled('status')) {
            $query->whereRaw('status = ?', [$request->input('status')]);
        }

        return response()->json($query->paginate($request->integer('per_page', 25)));
    }

    public function listProgrammeEntries()
    {
        $entries = \App\Models\ProgrammeEntry::with('organisation:id,name')
            ->where('is_submitted', true)
            ->select('id', 'programme_name', 'organisation_id')
            ->orderBy('programme_name')
            ->get()
            ->map(fn($e) => [
                'id'                => $e->id,
                'programme_name'    => $e->programme_name,
                'organisation_name' => $e->organisation?->name,
            ]);

        return response()->json(['data' => $entries]);
    }

    public function coordinators(Request $request)
    {
        $user = $request->user();

        $query = User::where('role', 'nep_coordinator')->where('status', 'active');

        // Coordinator sees all coordinators (including themselves)
        // Admin sees all coordinators
        $coordinators = $query->select('id', 'name', 'email')->orderBy('name')->get();

        return response()->json(['data' => $coordinators]);
    }

    public function store(StoreAdviserSubmissionRequest $request)
    {
        $validated = $request->validated();
        $user = $request->user();

        $validated['analysis_scope'] = $validated['analysis_scope'] ?? 'full map';

        if ($validated['analysis_scope'] === 'full map') {
            $validated['analysis_scope_detail'] = null;
        }

        $validated['status'] = $validated['status'] ?? AdvisoryNoteStatus::SUBMITTED_FOR_REVIEW;

        // Coordinator self-assigns by default unless they explicitly pick another
        if ($user->role === 'nep_coordinator' && empty($validated['coordinator_id'])) {
            $validated['coordinator_id'] = $user->id;
        }

        // Store uploaded document file if provided
        if ($request->hasFile('document')) {
            $validated['document_file'] = $request->file('document')->store('adviser-documents', 'public');
        }

        unset($validated['document']);

        $submission = AdvisoryNote::create([
            ...$validated,
            'submitted_at' => now(),
        ]);

        $this->notifyCoordinator($submission, null);

        return response()->json([
            'message' => 'Document submitted for analysis.',
            'data'    => $submission,
        ], 201);
    }

    #[OA\Get(
        path: "/adviser/submissions/{id}",
        summary: "Get a single adviser submission",
        description: "Returns a single adviser submission with its staff assignment and recommendations.",
        security: [["bearerAuth" => []]],
        tags: ["Adviser"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Submission details", content: new OA\JsonContent(properties: [new OA\Property(property: "data", ref: "#/components/schemas/AdviserSubmission")])),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden"),
            new OA\Response(response: 404, description: "Not Found"),
        ]
    )]
    public function show(AdvisoryNote $advisoryNote)
    {
        $advisoryNote->load([
            'staffUser',
            'coordinator:id,name',
            'programmeEntry.organisation:id,name',
            'programmeEntry.activities.activityItem.subcategory.category',
            'programmeEntry.locations',
            'recommendations.programmeEntry.organisation:id,name',
        ]);

        return response()->json(['data' => $advisoryNote]);
    }

    #[OA\Get(
        path: "/adviser/programme-entries/{programmeEntry}/advisory-note",
        summary: "Get the advisory note for a programme entry",
        description: "Returns the advisory note (with all sections and recommendations) linked to a specific programme entry. Used by the 'Analyse in the Adviser' button on the programme entry view.",
        security: [["bearerAuth" => []]],
        tags: ["Adviser"],
        parameters: [
            new OA\Parameter(name: "programmeEntry", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Advisory note found", content: new OA\JsonContent(properties: [new OA\Property(property: "data", ref: "#/components/schemas/AdviserSubmission")])),
            new OA\Response(response: 404, description: "No advisory note found for this programme entry"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden"),
        ]
    )]
    public function showByProgrammeEntry(Request $request, \App\Models\ProgrammeEntry $programmeEntry)
    {
        $user = $request->user();

        // An organisation-bound user (member_org, or any custom role
        // assigned one) may only view the advisory note for its own
        // programme entry — without this, any authenticated member could
        // read another organisation's delivered advisory note by
        // guessing/iterating IDs. 404 (not 403), matching the "don't confirm
        // existence" convention used elsewhere for org-scoped lookups
        // (e.g. ProgrammeEntryController).
        $isOrgBound = ! $user->hasOrganisationWideAccess();

        if ($isOrgBound && (int) $programmeEntry->organisation_id !== (int) $user->organisation_id) {
            return response()->json(['message' => 'No advisory note found for this programme entry.'], 404);
        }

        $query = AdvisoryNote::where('programme_entry_id', $programmeEntry->id)
            ->with([
                'staffUser',
                'coordinator:id,name',
                'programmeEntry.organisation:id,name',
                'programmeEntry.activities.activityItem.subcategory.category',
                'programmeEntry.locations',
                'recommendations.programmeEntry.organisation:id,name',
            ])
            ->latest();

        // Org-bound users only see delivered notes
        if ($isOrgBound) {
            $query->where('status', AdvisoryNoteStatus::ADVICE_DELIVERED);
        }

        $advisoryNote = $query->first();

        if (! $advisoryNote) {
            return response()->json(['message' => 'No advisory note found for this programme entry.'], 404);
        }

        // Hide internal coordinator notes from org-bound users
        if ($isOrgBound) {
            $advisoryNote->makeHidden('section_coordinators_notes');
        }

        return response()->json(['data' => $advisoryNote]);
    }

    #[OA\Patch(
        path: "/adviser/submissions/{id}",
        summary: "Update an adviser submission",
        description: "Update the coordinator assignment and/or sections for an adviser submission.",
        security: [["bearerAuth" => []]],
        tags: ["Adviser"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, description: "The advisory note submission ID", schema: new OA\Schema(type: "integer")),
        ],
        requestBody: new OA\RequestBody(
            description: "Fields to update",
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "assign_to_staff_user_id", type: "integer", nullable: true, example: 1, description: "Staff user ID to assign the submission to"),
                    new OA\Property(property: "section_profile", type: "string", nullable: true, example: "Profile section content"),
                    new OA\Property(property: "section_gaps", type: "string", nullable: true, example: "Gaps section content"),
                    new OA\Property(property: "section_coordinators_notes", type: "string", nullable: true, example: "Coordinator notes"),
                    new OA\Property(property: "final_note_file", type: "string", nullable: true, example: "final-report.pdf"),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Submission updated successfully", content: new OA\JsonContent(properties: [new OA\Property(property: "message", type: "string", example: "Submission updated successfully."), new OA\Property(property: "data", ref: "#/components/schemas/AdviserSubmission")])),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden"),
            new OA\Response(response: 404, description: "Not Found"),
            new OA\Response(response: 422, description: "Validation failed"),
        ]
    )]
    public function update(UpdateAdviserSubmissionRequest $request, AdvisoryNote $advisoryNote)
    {
        // Block section edits if the linked programme entry has not been submitted by the member org
        if ($advisoryNote->programme_entry_id) {
            $advisoryNote->loadMissing('programmeEntry');
            if ($advisoryNote->programmeEntry && ! $advisoryNote->programmeEntry->is_submitted) {
                return response()->json([
                    'message' => 'This advisory note cannot be edited until the member organisation submits the programme.',
                ], 422);
            }
        }

        $validated = $request->validated();

        if ($request->hasFile('file')) {
            if ($advisoryNote->final_note_file && !str_starts_with($advisoryNote->final_note_file, 'http')) {
                Storage::disk('public')->delete($advisoryNote->final_note_file);
            }
            $validated['final_note_file'] = $request->file('file')->store('adviser-notes', 'public');
        }

        // Save recommendations (Section B) if provided
        if ($request->has('recommendations')) {
            $advisoryNote->recommendations()->delete();
            foreach ($request->input('recommendations', []) as $rec) {
                $advisoryNote->recommendations()->create([
                    'programme_entry_id' => $rec['programme_entry_id'] ?? null,
                    'organisation_name'  => $rec['organisation_name']  ?? null,
                    'programme_name'     => $rec['programme_name']     ?? null,
                    'type'               => $rec['type']               ?? 'Geographic overlap',
                    'relational'         => $rec['relational']         ?? null,
                    'rationale'          => $rec['rationale']          ?? null,
                ]);
            }
        }

        unset($validated['file']);

        $previousCoordinatorId = $advisoryNote->coordinator_id;

        $advisoryNote->update($validated);

        if (
            array_key_exists('coordinator_id', $validated) &&
            $validated['coordinator_id'] !== $previousCoordinatorId
        ) {
            $this->notifyCoordinator($advisoryNote->fresh(), $previousCoordinatorId);
        }

        return response()->json([
            'message' => 'Submission updated successfully.',
            'data'    => $advisoryNote->fresh()->load(['recommendations.programmeEntry.organisation:id,name']),
        ]);
    }

    #[OA\Patch(
        path: "/adviser/submissions/{id}/deliver",
        summary: "Mark an adviser submission as delivered",
        description: "Sets the submission status to 'advice_delivered' and records the delivery timestamp.",
        security: [["bearerAuth" => []]],
        tags: ["Adviser"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Submission marked as delivered", content: new OA\JsonContent(properties: [new OA\Property(property: "message", type: "string", example: "Submission marked as delivered."), new OA\Property(property: "data", ref: "#/components/schemas/AdviserSubmission")])),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden"),
            new OA\Response(response: 404, description: "Not Found"),
        ]
    )]
    private function notifyCoordinator(AdvisoryNote $submission, ?int $previousCoordinatorId): void
    {
        if (! $submission->coordinator_id) {
            return;
        }

        // Don't re-notify if coordinator didn't change
        if ($submission->coordinator_id === $previousCoordinatorId) {
            return;
        }

        $coordinator = User::find($submission->coordinator_id);
        $coordinator?->notify(new AdviserSubmissionAssigned($submission));
    }

    #[OA\Get(
        path: "/adviser/submissions/{id}/file",
        summary: "Download the uploaded advisory note PDF file",
        description: "Streams the uploaded final advisory note PDF file. Only accessible by NEP Admin and NEP Coordinator.",
        security: [["bearerAuth" => []]],
        tags: ["Adviser"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(response: 200, description: "File download successful", content: new OA\MediaType(mediaType: "application/pdf", schema: new OA\Schema(type: "string", format: "binary"))),
            new OA\Response(response: 404, description: "No file uploaded for this advisory note"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden"),
        ]
    )]
    public function fileToken(AdvisoryNote $advisoryNote)
    {
        if (! $advisoryNote->final_note_file) {
            return response()->json(['message' => 'No file uploaded for this advisory note.'], 404);
        }

        $token = Str::random(40);
        Cache::put('adviser_file_token:' . $token, $advisoryNote->id, now()->addMinutes(5));

        return response()->json(['token' => $token]);
    }

    public function downloadFile(Request $request, AdvisoryNote $advisoryNote)
    {
        $token = $request->query('token');
        $cacheKey = 'adviser_file_token:' . $token;

        if (! $token || Cache::get($cacheKey) !== $advisoryNote->id) {
            return response()->json(['message' => 'Invalid or expired download token.'], 403);
        }

        Cache::forget($cacheKey);

        $path = $advisoryNote->final_note_file;

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return redirect($path);
        }

        if (! Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'File not found on storage.'], 404);
        }

        $fullPath = Storage::disk('public')->path($path);
        $mimeType = mime_content_type($fullPath) ?: 'application/octet-stream';

        return response()->file($fullPath, [
            'Content-Type'        => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
        ]);
    }

    public function exportPdf(AdvisoryNote $advisoryNote)
    {
        $advisoryNote->load([
            'coordinator:id,name',
            'programmeEntry.organisation:id,name',
            'recommendations.programmeEntry.organisation:id,name',
        ]);

        $pdf = Pdf::loadView('exports.advisory-note-pdf', [
            'note'        => $advisoryNote,
            'generatedAt' => now()->format('d M Y, H:i'),
        ])->setPaper('a4', 'portrait');

        $filename = 'advisory-note-' . $advisoryNote->id . '-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    public function markDelivered(Request $request, AdvisoryNote $advisoryNote)
    {
        if ($advisoryNote->status === AdvisoryNoteStatus::ADVICE_DELIVERED) {
            return response()->json([
                'message' => 'Submission has already been marked as delivered.',
                'data'    => $advisoryNote,
            ]);
        }

        // Block delivery if the linked programme entry has not been submitted by the member org
        if ($advisoryNote->programme_entry_id) {
            $advisoryNote->loadMissing('programmeEntry');
            if ($advisoryNote->programmeEntry && ! $advisoryNote->programmeEntry->is_submitted) {
                return response()->json([
                    'message' => 'Advisory advice cannot be delivered on a programme that has not been submitted by the member organisation.',
                ], 422);
            }
        }

        $advisoryNote->update([
            'status'       => AdvisoryNoteStatus::ADVICE_DELIVERED,
            'delivered_at' => now(),
        ]);

        // Notify all member_org users of the linked programme entry's organisation
        if ($advisoryNote->programme_entry_id) {
            $advisoryNote->load('programmeEntry.organisation.users');
            $coordinatorName = $request->user()->name;
            $members = $advisoryNote->programmeEntry?->organisation?->users
                ?->where('role', 'member_org')
                ?->where('status', 'active') ?? collect();

            foreach ($members as $member) {
                $member->notify(new AdviceDelivered($advisoryNote, $coordinatorName));
            }
        }

        return response()->json([
            'message' => 'Submission marked as delivered.',
            'data'    => $advisoryNote->fresh(),
        ]);
    }
}
