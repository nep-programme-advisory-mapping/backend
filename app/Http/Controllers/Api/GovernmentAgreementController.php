<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ScopesProgrammeEntryAccess;
use App\Http\Requests\StoreGovernmentAgreementsRequest;
use App\Models\ProgrammeEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class GovernmentAgreementController extends Controller
{
    use ScopesProgrammeEntryAccess;

    #[OA\Schema(
        schema: "GovernmentAgreement",
        type: "object",
        properties: [
            new OA\Property(property: "id", type: "integer", example: 3),
            new OA\Property(property: "programme_entry_id", type: "integer", example: 8),
            new OA\Property(property: "counterpart_agency", type: "string"),
            new OA\Property(property: "status", type: "string"),
            new OA\Property(property: "institution_name", type: "string"),
            new OA\Property(property: "nature", type: "string"),
            new OA\Property(property: "created_at", type: "string", format: "date-time"),
            new OA\Property(property: "updated_at", type: "string", format: "date-time"),
        ]
    )]

    #[OA\Put(
        path: "/programme-entries/{programmeEntry}/government-agreements",
        summary: "Save Section 4 government agreements for a programme entry",
        description: "Accepts an array of agreement records. Existing rows (identified by id) are updated, new rows (no id) are created, and any existing rows not present in the array are deleted. Each enum field is validated against its allowed values.",
        security: [["bearerAuth" => []]],
        tags: ["Government Agreements"],
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
                required: ["agreements"],
                properties: [
                    new OA\Property(
                        property: "agreements",
                        type: "array",
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: "id", type: "integer", nullable: true, example: 3, description: "Omit for new rows"),
                                new OA\Property(property: "counterpart_agency", type: "string", enum: [
                                    "MoEYS national level",
                                    "Provincial Office of Education",
                                    "District Office of Education",
                                    "Teacher Education Institution",
                                    "specific school or cluster",
                                    "other government ministry",
                                ]),
                                new OA\Property(property: "status", type: "string", enum: ["active", "expired", "under renewal", "under negotiation"]),
                                new OA\Property(property: "institution_name", type: "string", example: "Kampong Cham Provincial Office of Education"),
                                new OA\Property(property: "nature", type: "string", enum: [
                                    "MoU",
                                    "Letter of Understanding",
                                    "official approval letter",
                                    "informal working arrangement",
                                ]),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Agreements saved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Agreements saved."),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/GovernmentAgreement")
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Programme entry not found, or caller not authorized"),
            new OA\Response(
                response: 422,
                description: "Validation failed",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string"),
                        new OA\Property(property: "errors", type: "object"),
                    ]
                )
            ),
        ]
    )]
    public function store(StoreGovernmentAgreementsRequest $request, ProgrammeEntry $programmeEntry)
    {
        if (! $this->canWrite($request, $programmeEntry)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $incoming = collect($request->validated('agreements'));
        $incomingIds = $incoming->pluck('id')->filter()->all();

        DB::transaction(function () use ($programmeEntry, $incoming, $incomingIds) {
            
            $programmeEntry->governmentAgreements()
                ->whereNotIn('id', $incomingIds)
                ->delete();

            foreach ($incoming as $row) {
                $attributes = [
                    'counterpart_agency' => $row['counterpart_agency'],
                    'status' => $row['status'],
                    'institution_name' => $row['institution_name'],
                    'nature' => $row['nature'],
                ];

                if (! empty($row['id'])) {
                    
                    $programmeEntry->governmentAgreements()
                        ->where('id', $row['id'])
                        ->update($attributes);
                } else {
                    
                    $programmeEntry->governmentAgreements()->create($attributes);
                }
            }
        });

        return response()->json([
            'message' => 'Agreements saved.',
            'data' => $programmeEntry->governmentAgreements()->get(),
        ]);
    }

    public function index(Request $request, ProgrammeEntry $programmeEntry)
    {
        if (! $this->canView($request, $programmeEntry)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json([
            'data' => $programmeEntry->governmentAgreements()->get(),
        ]);
    }

    protected function canView(Request $request, ProgrammeEntry $programmeEntry): bool
    {
        return $this->userCanAccessProgrammeEntry($request, $programmeEntry);
    }

    protected function canWrite(Request $request, ProgrammeEntry $programmeEntry): bool
    {
        return $this->userCanAccessProgrammeEntry($request, $programmeEntry);
    }
}
