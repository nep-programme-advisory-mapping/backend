<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\BuildsMapQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class MapGeoJsonController extends Controller
{
    use BuildsMapQuery;

    private const CENTROIDS = [
        'Banteay Meanchey' => [102.9722, 13.6672],
        'Battambang'       => [103.1932, 13.0957],
        'Kampong Cham'     => [105.4623, 12.0000],
        'Kampong Chhnang'  => [104.6667, 12.1667],
        'Kampong Speu'     => [104.5000, 11.5000],
        'Kampong Thom'     => [104.9810, 12.7111],
        'Kampot'           => [104.1833, 10.6000],
        'Kandal'           => [104.9500, 11.4500],
        'Kep'              => [104.3167, 10.4833],
        'Koh Kong'         => [103.5000, 11.5000],
        'Kratie'           => [106.0167, 12.5000],
        'Mondulkiri'       => [107.2000, 12.4500],
        'Oddar Meanchey'   => [103.5000, 14.1667],
        'Pailin'           => [102.6000, 12.8500],
        'Phnom Penh'       => [104.9174, 11.5564],
        'Preah Sihanouk'   => [103.5000, 10.6667],
        'Preah Vihear'     => [104.9810, 13.8000],
        'Prey Veng'        => [105.3250, 11.4833],
        'Pursat'           => [103.8333, 12.5000],
        'Ratanakiri'       => [107.0000, 13.7500],
        'Siem Reap'        => [103.8550, 13.3671],
        'Stung Treng'      => [105.9667, 13.5167],
        'Svay Rieng'       => [105.8000, 11.0833],
        'Takéo'            => [104.7842, 10.9908],
        'Tboung Khmum'     => [105.8750, 11.9500],
    ];

    #[OA\Get(
        path: "/map/entries/geojson",
        summary: "Get province-level GeoJSON with entry counts for map visualization",
        description: "Returns a GeoJSON FeatureCollection of provinces, each containing the count of matching programme entries. Only accessible by nep_admin and nep_coordinator.",
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
            new OA\Parameter(name: "agreement_counterpart_type", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "agreement_status", in: "query", required: false, schema: new OA\Schema(type: "string")),
        ],
        responses: [
            new OA\Response(response: 200, description: "GeoJSON FeatureCollection retrieved successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden"),
        ]
    )]
    public function geojson(Request $request)
    {
        // Authorization is enforced by the route's permission:dashboard.view
        // middleware — this used to also hardcode role !== nep_admin/
        // nep_coordinator here, which silently overrode any grant of that
        // permission to a different role.
        $user = $request->user();

        $entryIds = $this->buildMapQuery($request, $user)->pluck('id');

        $counts = DB::table('programme_geography')
            ->whereIn('programme_entry_id', $entryIds)
            ->whereNotNull('province_id')
            ->selectRaw('province_id, COUNT(DISTINCT programme_entry_id) as count')
            ->groupBy('province_id')
            ->pluck('count', 'province_id')
            ->toArray();

        $provinces = Cache::remember('provinces:all_models', now()->addHours(24),
            fn() => \App\Models\Province::select('id', 'province_name')->get()
        );

        $features = $provinces->map(function ($province) use ($counts) {
            return [
                'type'       => 'Feature',
                'id'         => $province->id,
                'properties' => [
                    'name'        => $province->province_name,
                    'entry_count' => (int) ($counts[$province->id] ?? 0),
                ],
                'geometry' => [
                    'type'        => 'Point',
                    'coordinates' => self::CENTROIDS[$province->province_name] ?? [104.9, 12.5],
                ],
            ];
        })->values()->all();

        return response()->json(['type' => 'FeatureCollection', 'features' => $features]);
    }
}
