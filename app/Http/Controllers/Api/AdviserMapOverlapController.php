<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Adviser\AdviserMapEntryResource;
use App\Services\Adviser\MapOverlapMatcher;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Adviser')]
class AdviserMapOverlapController extends Controller
{
    #[OA\Post(
        path: '/adviser/map/overlap-query',
        summary: 'Query map entries by overlap with an extracted programme profile',
        description: 'Returns ProgrammeEntry records that overlap with the provided programme profile on at least one of: activity, geography, audience. Respects analysis_scope (full map / geographic subset / thematic subset).',
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["analysis_scope", "programme_profile"],
                properties: [
                    new OA\Property(
                        property: 'analysis_scope',
                        type: 'string',
                        example: 'full map',
                        enum: ['full map', 'geographic subset', 'thematic subset']
                    ),
                    new OA\Property(
                        property: 'programme_profile',
                        type: 'object',
                        example: [
                            'activities' => [
                                'category_ids' => [1],
                                'education_level_ids' => [2],
                                'inclusion_groups' => ['boys'],
                            ],
                            'audiences' => [
                                'inclusion_types' => ['target']
                            ],
                            'geography' => [
                                'province_ids' => [3]
                            ],
                        ]
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Matching entries retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/ProgrammeEntry')
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
    public function match(Request $request, MapOverlapMatcher $matcher)
    {
        $request->validate([
            'analysis_scope' => 'required|string|in:full map,geographic subset,thematic subset',
            'programme_profile' => 'required|array',
        ]);

        $analysisScope = $request->input('analysis_scope');
        $programmeProfile = $request->input('programme_profile');

        $entries = $matcher
            ->match($programmeProfile, $analysisScope)
            ->with([
                'organisation',
                'budgetBand',
                'keywords',
                'locations.province',
                'locations.district',
                'locations.commune',
                'locations.village',
                'activities.activityItem.subcategory.category',
                'activities.activityLevels.educationLevel',
            ])
            ->get();

        return response()->json([
            'data' => AdviserMapEntryResource::collection($entries),
        ]);
    }
}