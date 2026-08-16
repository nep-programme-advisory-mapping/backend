<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\BuildsMapQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class MapEntryController extends Controller
{
    use BuildsMapQuery;

    private const INDEX_WITH = [
        'organisation:id,name',
        'budgetBand:id,label',
        'keywords:id,programme_entry_id,keyword',
        'locations.province:id,province_name',
        'locations.district:id,name',
        'locations.commune:id,name',
        'locations.village:id,name',
        'activities.activityItem.subcategory.category',
        'activities.activityLevels.educationLevel',
        'governmentAgreements',
    ];

    #[OA\Get(
        path: "/map/entries",
        summary: "Query programme entries for map display",
        description: "Returns entries matching the given filters. All filters combine with AND. Respects BE-010/BE-025 visibility rules: nep_admin and nep_coordinator see all entries, member_org sees only their own organisation's entries.",
        security: [["bearerAuth" => []]],
        tags: ["Map Query & Export"],
        parameters: [
            new OA\Parameter(name: "category_id", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "subcategory_id", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "item_id", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "education_level_id", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "inclusion_group", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "inclusion_type", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "keyword", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "organisation_name", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "budget_band_id", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "min_staff", in: "query", required: false, schema: new OA\Schema(type: "number")),
            new OA\Parameter(name: "max_staff", in: "query", required: false, schema: new OA\Schema(type: "number")),
            new OA\Parameter(name: "min_beneficiaries", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "max_beneficiaries", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "province_id", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "district_id", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "commune_id", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "agreement_counterpart_type", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "agreement_status", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "per_page", in: "query", required: false, description: "Number per page, or omit for cached full result", schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(response: 200, description: "Filtered entries retrieved successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]
    public function index(Request $request)
    {
        $user    = $request->user();
        $perPage = $request->integer('per_page', 0);

        if ($perPage > 0) {
            return response()->json(
                $this->buildMapQuery($request, $user)->with(self::INDEX_WITH)->paginate($perPage)
            );
        }

        // Partition the cache by org for anyone confined to one (not just
        // member_org — any custom org-bound role) so results never leak
        // across organisations that happen to share the "not staff" bucket.
        $cacheKey = 'map:entries:'
            . ($user->hasOrganisationWideAccess() ? 'staff' : 'org_' . $user->organisation_id)
            . ':' . md5(json_encode($request->query()));

        return response()->json(
            Cache::remember($cacheKey, now()->addMinutes(5), function () use ($request, $user) {
                $all = $this->buildMapQuery($request, $user)->with(self::INDEX_WITH)->get();
                return ['data' => $all, 'total' => $all->count()];
            })
        );
    }
}
