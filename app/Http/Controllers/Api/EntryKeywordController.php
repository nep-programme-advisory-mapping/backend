<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ScopesProgrammeEntryAccess;
use App\Http\Requests\StoreEntryKeywordsRequest;
use App\Models\ProgrammeEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "EntryKeyword",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 1),
        new OA\Property(property: "programme_entry_id", type: "integer", example: 45),
        new OA\Property(property: "keyword", type: "string", example: "education"),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ]
)]
class EntryKeywordController extends Controller
{
    use ScopesProgrammeEntryAccess;

    #[OA\Put(
        path: "/programme-entries/{programmeEntry}/keywords",
        summary: "Save keywords for a programme entry",
        description: "Accepts an array of keyword strings. Replaces all existing keywords for the entry.",
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
                required: ["keywords"],
                properties: [
                    new OA\Property(
                        property: "keywords",
                        type: "array",
                        items: new OA\Items(type: "string"),
                        example: ["education", "youth", "rural"]
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Keywords saved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Keywords saved."),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/EntryKeyword")
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
    public function store(StoreEntryKeywordsRequest $request, ProgrammeEntry $programmeEntry)
    {
        if (! $this->canWrite($request, $programmeEntry)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $keywords = $request->validated('keywords');

        DB::transaction(function () use ($programmeEntry, $keywords) {
            $programmeEntry->keywords()->delete();

            foreach ($keywords as $keyword) {
                $programmeEntry->keywords()->create([
                    'keyword' => $keyword,
                ]);
            }
        });

        return response()->json([
            'message' => 'Keywords saved.',
            'data' => $programmeEntry->keywords()->get(),
        ]);
    }

    protected function canWrite(Request $request, ProgrammeEntry $programmeEntry): bool
    {
        return $this->userCanAccessProgrammeEntry($request, $programmeEntry);
    }
}
