<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ScopesProgrammeEntryAccess;
use App\Http\Requests\StoreEntryCollaborationsRequest;
use App\Models\ProgrammeEntry;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "EntryCollaboration",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "programme_entry_id", type: "integer", example: 45),
        new OA\Property(property: "organisation_name", type: "string", example: "Example Education NGO"),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ]
)]
class EntryCollaborationController extends Controller
{
    use ScopesProgrammeEntryAccess;

    #[OA\Put(
        path: "/programme-entries/{programmeEntry}/collaborations",
        summary: "Save current collaborations for a programme entry",
        description: "Accepts up to 5 organisation names as free text. Replaces all existing collaborations for the entry; an empty array clears them.",
        security: [["bearerAuth" => []]],
        tags: ["Programme Entries"],
        parameters: [
            new OA\Parameter(
                name: "programmeEntry",
                in: "path",
                required: true,
                description: "Programme entry ID",
                schema: new OA\Schema(type: "integer")
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["collaborations"],
                properties: [
                    new OA\Property(
                        property: "collaborations",
                        type: "array",
                        maxItems: 5,
                        items: new OA\Items(type: "string", maxLength: 255),
                        example: ["Example Education NGO", "Example Private Foundation"]
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Collaborations saved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Collaborations saved."),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/EntryCollaboration")
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Programme entry not found, or caller not authorized"),
            new OA\Response(response: 422, description: "Validation failed"),
        ]
    )]
    public function store(StoreEntryCollaborationsRequest $request, ProgrammeEntry $programmeEntry)
    {
        if (! $this->userCanAccessProgrammeEntry($request, $programmeEntry)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $collaborations = $request->validated('collaborations');

        DB::transaction(function () use ($programmeEntry, $collaborations) {
            $programmeEntry->collaborations()->delete();

            foreach ($collaborations as $organisationName) {
                $programmeEntry->collaborations()->create([
                    'organisation_name' => $organisationName,
                ]);
            }
        });

        return response()->json([
            'message' => 'Collaborations saved.',
            'data' => $programmeEntry->collaborations()->get(),
        ]);
    }
}
