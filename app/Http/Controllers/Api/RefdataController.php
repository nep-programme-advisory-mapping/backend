<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BudgetBand;
use App\Models\EducationLevel;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "EducationLevel",
    properties: [
        new OA\Property(property: "id", type: "integer"),
        new OA\Property(property: "level_name", type: "string"),
    ],
    type: "object"
)]
#[OA\Schema(
    schema: "BudgetBand",
    properties: [
        new OA\Property(property: "id", type: "integer"),
        new OA\Property(property: "label", type: "string"),
        new OA\Property(property: "min_amount", type: "integer", nullable: true),
        new OA\Property(property: "max_amount", type: "integer", nullable: true),
    ],
    type: "object"
)]
class RefdataController extends Controller
{
    #[OA\Get(
        path: "/refdata/education-levels",
        summary: "Get all education levels",
        description: "Returns a list of education levels for populating frontend dropdowns.",
        security: [["bearerAuth" => []]],
        tags: ["Reference Data"],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of education levels",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/EducationLevel")
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]
    public function educationLevels()
    {
        $levels = Cache::remember('refdata:education-levels', now()->addHours(24), function () {
            return EducationLevel::all();
        });

        return response()->json(['data' => $levels]);
    }

    #[OA\Get(
        path: "/refdata/budget-bands",
        summary: "Get all budget bands",
        description: "Returns a list of budget bands for populating frontend dropdowns.",
        security: [["bearerAuth" => []]],
        tags: ["Reference Data"],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of budget bands",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/BudgetBand")
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]
    public function budgetBands()
    {
        $bands = Cache::remember('refdata:budget-bands', now()->addHours(24), function () {
            return BudgetBand::all();
        });

        return response()->json(['data' => $bands]);
    }

    #[OA\Get(
        path: "/refdata/counterpart-agencies",
        summary: "Get all counterpart agency types",
        description: "Returns the list of valid counterpart agency types for government agreements.",
        security: [["bearerAuth" => []]],
        tags: ["Reference Data"],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of counterpart agency types",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(type: "string")
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]
    public function counterpartAgencies()
    {
        $agencies = Cache::remember('refdata:counterpart-agencies', now()->addHours(24), function () {
            return [
                'MoEYS national level',
                'Provincial Office of Education',
                'District Office of Education',
                'Teacher Education Institution',
                'specific school or cluster',
                'other government ministry',
            ];
        });

        return response()->json(['data' => $agencies]);
    }
}
