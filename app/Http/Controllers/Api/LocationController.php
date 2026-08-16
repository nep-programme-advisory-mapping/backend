<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Commune;
use App\Models\District;
use App\Models\Province;
use App\Models\Village;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "Province",
    properties: [
        new OA\Property(property: "id", type: "integer"),
        new OA\Property(property: "province_name", type: "string"),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ],
    type: "object"
)]
#[OA\Schema(
    schema: "District",
    properties: [
        new OA\Property(property: "id", type: "integer"),
        new OA\Property(property: "province_id", type: "integer"),
        new OA\Property(property: "name", type: "string"),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ],
    type: "object"
)]
#[OA\Schema(
    schema: "Commune",
    properties: [
        new OA\Property(property: "id", type: "integer"),
        new OA\Property(property: "district_id", type: "integer"),
        new OA\Property(property: "name", type: "string"),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ],
    type: "object"
)]
#[OA\Schema(
    schema: "Village",
    properties: [
        new OA\Property(property: "id", type: "integer"),
        new OA\Property(property: "commune_id", type: "integer"),
        new OA\Property(property: "name", type: "string"),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    ],
    type: "object"
)]
class LocationController extends Controller
{
    #[OA\Get(
        path: "/provinces",
        summary: "Get all provinces",
        description: "Returns a list of all provinces for populating frontend dropdowns.",
        security: [["bearerAuth" => []]],
        tags: ["Locations"],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of provinces",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/Province")
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
        ]
    )]
    public function index()
    {
        $provinces = Cache::remember('provinces:all', now()->addHours(24), function () {
            return Province::all();
        });
        return response()->json(['data' => $provinces]);
    }

    #[OA\Get(
        path: "/provinces/{province}/districts",
        summary: "Get districts by province",
        description: "Returns districts belonging to the specified province.",
        security: [["bearerAuth" => []]],
        tags: ["Locations"],
        parameters: [
            new OA\Parameter(
                name: "province",
                in: "path",
                required: true,
                description: "Province ID",
                schema: new OA\Schema(type: "integer")
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of districts for the province",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/District")
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Province not found"),
        ]
    )]
    public function districts(Province $province)
    {
        $districts = Cache::remember("districts:province:{$province->id}", now()->addHours(24), function () use ($province) {
            $result = $province->districts()->get();
            if ($result->isEmpty()) {
                // Don't cache empty results — data may not be seeded yet
                return null;
            }
            return $result;
        });

        if ($districts === null) {
            Cache::forget("districts:province:{$province->id}");
            $districts = $province->districts()->get();
        }

        return response()->json(['data' => $districts]);
    }

    #[OA\Get(
        path: "/districts/{district}/communes",
        summary: "Get communes by district",
        description: "Returns communes belonging to the specified district.",
        security: [["bearerAuth" => []]],
        tags: ["Locations"],
        parameters: [
            new OA\Parameter(
                name: "district",
                in: "path",
                required: true,
                description: "District ID",
                schema: new OA\Schema(type: "integer")
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of communes for the district",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/Commune")
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "District not found"),
        ]
    )]
    public function communes(District $district)
    {
        $communes = Cache::remember("communes:district:{$district->id}", now()->addHours(24), function () use ($district) {
            $result = $district->communes()->get();
            if ($result->isEmpty()) return null;
            return $result;
        });

        if ($communes === null) {
            Cache::forget("communes:district:{$district->id}");
            $communes = $district->communes()->get();
        }

        return response()->json(['data' => $communes]);
    }

    #[OA\Get(
        path: "/communes/{commune}/villages",
        summary: "Get villages by commune",
        description: "Returns villages belonging to the specified commune.",
        security: [["bearerAuth" => []]],
        tags: ["Locations"],
        parameters: [
            new OA\Parameter(
                name: "commune",
                in: "path",
                required: true,
                description: "Commune ID",
                schema: new OA\Schema(type: "integer")
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of villages for the commune",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/Village")
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Commune not found"),
        ]
    )]
    public function villages(Commune $commune)
    {
        $villages = Cache::remember("villages:commune:{$commune->id}", now()->addHours(24), function () use ($commune) {
            $result = $commune->villages()->get();
            if ($result->isEmpty()) return null;
            return $result;
        });

        if ($villages === null) {
            Cache::forget("villages:commune:{$commune->id}");
            $villages = $commune->villages()->get();
        }

        return response()->json(['data' => $villages]);
    }

    #[OA\Get(
        path: "/provinces/counts",
        summary: "Get programme counts per province",
        description: "Returns each province with the count of distinct programme entries linked to it. Ordered by count descending.",
        security: [["bearerAuth" => []]],
        tags: ["Locations"],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of provinces with programme counts",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: "id", type: "integer", example: 1),
                            new OA\Property(property: "province_name", type: "string", example: "Phnom Penh"),
                            new OA\Property(property: "programme_count", type: "integer", example: 42),
                        ]
                    )
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 403, description: "Forbidden"),
        ]
    )]
    public function provinceProgrammeCounts()
    {
        $counts = Cache::remember('provinces:programme_counts', 300, function () {
            return DB::table('provinces as p')
                ->leftJoin('programme_geography as pg', 'pg.province_id', '=', 'p.id')
                ->select('p.id', 'p.province_name',
                    DB::raw('COUNT(DISTINCT pg.programme_entry_id) as programme_count'))
                ->groupBy('p.id', 'p.province_name')
                ->orderByDesc('programme_count')
                ->get();
        });

        return response()->json($counts);
    }
}