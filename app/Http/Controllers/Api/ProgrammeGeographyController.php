<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ScopesProgrammeEntryAccess;
use App\Http\Requests\StoreProgrammeGeographyRequest;
use App\Models\ProgrammeEntry;
use App\Models\ProgrammeLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "ProgrammeGeography",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "integer", example: 15),
        new OA\Property(property: "programme_entry_id", type: "integer", example: 8),
        new OA\Property(property: "province_id", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "district_id", type: "integer", nullable: true, example: 5),
        new OA\Property(property: "commune_id", type: "integer", nullable: true, example: 10),
        new OA\Property(property: "village_id", type: "integer", nullable: true, example: 50),
        new OA\Property(property: "country", type: "string", nullable: true, example: "Thailand"),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ]
)]
class ProgrammeGeographyController extends Controller
{
    use ScopesProgrammeEntryAccess;

    #[OA\Get(
        path: "/programme-entries/{programmeEntry}/geography",
        summary: "Get saved geographic coverage for a programme entry",
        description: "Returns all saved province, district, and other-country rows for the entry.",
        security: [["bearerAuth" => []]],
        tags: ["Programme Geography"],
        parameters: [
            new OA\Parameter(
                name: "programmeEntry",
                in: "path",
                required: true,
                description: "Programme entry ID",
                schema: new OA\Schema(type: "integer")
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Geography retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/ProgrammeGeography")
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Programme entry not found, or caller not authorized"),
        ]
    )]
    public function index(Request $request, ProgrammeEntry $programmeEntry)
    {
        if (! $this->canView($request, $programmeEntry)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json([
            'data' => $programmeEntry->locations()->get()->map(fn ($loc) => [
                'province_id' => $loc->province_id,
                'district_id' => $loc->district_id,
                'commune_id' => $loc->commune_id,
                'village_id' => $loc->village_id,
                'country' => $loc->country,
            ]),
        ]);
    }

    #[OA\Put(
        path: "/programme-entries/{programmeEntry}/geography",
        summary: "Save Section 3 geographic coverage for a programme entry",
        description: "Accepts selected provinces with optional districts per province, and free-text other countries. Districts are validated as children of their selected province. Zero districts per province is supported. This replaces all existing geography rows for the entry.",
        security: [["bearerAuth" => []]],
        tags: ["Programme Geography"],
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
                required: ["provinces", "other_countries"],
                properties: [
                    new OA\Property(
                        property: "provinces",
                        type: "array",
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: "province_id", type: "integer", example: 1),
                            new OA\Property(
                                property: "district_ids",
                                type: "array",
                                items: new OA\Items(type: "integer"),
                                example: [5, 6],
                                description: "Can be an empty array — districts are optional"
                            ),
                            new OA\Property(
                                property: "commune_ids",
                                type: "array",
                                items: new OA\Items(type: "integer"),
                                example: [10, 11],
                                description: "Optional commune IDs"
                            ),
                            new OA\Property(
                                property: "village_ids",
                                type: "array",
                                items: new OA\Items(type: "integer"),
                                example: [50, 51],
                                description: "Optional village IDs"
                            ),
                        ]
                    )
                    ),
                    new OA\Property(
                        property: "other_countries",
                        type: "array",
                        items: new OA\Items(type: "string"),
                        example: ["Thailand", "Vietnam"]
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Geography saved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Geography saved."),
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/ProgrammeGeography")
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Programme entry not found, or caller not authorized"),
            new OA\Response(
                response: 422,
                description: "Validation failed — includes district-does-not-belong-to-province errors",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string"),
                        new OA\Property(property: "errors", type: "object"),
                    ]
                )
            ),
        ]
    )]
    public function store(StoreProgrammeGeographyRequest $request, ProgrammeEntry $programmeEntry)
    {
        if (! $this->canWrite($request, $programmeEntry)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        DB::transaction(function () use ($programmeEntry, $request) {
            $programmeEntry->locations()->delete();

            // Prepare all locations for bulk insert
            $locationsToCreate = [];

            foreach ($request->validated('provinces') as $provinceData) {
                $districtIds = $provinceData['district_ids'] ?? [];

                if (empty($districtIds)) {
                    $locationsToCreate[] = [
                        'programme_entry_id' => $programmeEntry->id,
                        'province_id' => $provinceData['province_id'],
                        'district_id' => null,
                        'commune_id' => null,
                        'village_id' => null,
                        'country' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                } else {
                    $communeIds = $provinceData['commune_ids'] ?? [];

                    foreach ($districtIds as $districtId) {
                        if (!empty($communeIds)) {
                            foreach ($communeIds as $communeId) {
                                $villageIds = $provinceData['village_ids'] ?? [];
                                if (!empty($villageIds)) {
                                    foreach ($villageIds as $villageId) {
                                        $locationsToCreate[] = [
                                            'programme_entry_id' => $programmeEntry->id,
                                            'province_id' => $provinceData['province_id'],
                                            'district_id' => $districtId,
                                            'commune_id' => $communeId,
                                            'village_id' => $villageId,
                                            'country' => null,
                                            'created_at' => now(),
                                            'updated_at' => now(),
                                        ];
                                    }
                                } else {
                                    $locationsToCreate[] = [
                                        'programme_entry_id' => $programmeEntry->id,
                                        'province_id' => $provinceData['province_id'],
                                        'district_id' => $districtId,
                                        'commune_id' => $communeId,
                                        'village_id' => null,
                                        'country' => null,
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                    ];
                                }
                            }
                        } else {
                            $locationsToCreate[] = [
                                'programme_entry_id' => $programmeEntry->id,
                                'province_id' => $provinceData['province_id'],
                                'district_id' => $districtId,
                                'commune_id' => null,
                                'village_id' => null,
                                'country' => null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }
                }
            }

            foreach ($request->validated('other_countries') as $country) {
                $locationsToCreate[] = [
                    'programme_entry_id' => $programmeEntry->id,
                    'province_id' => null,
                    'district_id' => null,
                    'commune_id' => null,
                    'village_id' => null,
                    'country' => $country,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Bulk insert all locations
            if (!empty($locationsToCreate)) {
                ProgrammeLocation::insert($locationsToCreate);
            }
        });

        return response()->json([
            'message' => 'Geography saved.',
            'data' => $programmeEntry->locations()->get(),
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
