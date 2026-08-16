<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ScopesProgrammeEntryAccess;
use App\Http\Requests\StoreProgrammeActivityRequest;
use App\Models\ActivityItem;
use App\Models\ProgrammeActivity;
use App\Models\ProgrammeActivityLevel;
use App\Models\ProgrammeEntry;
use App\Models\TaxonomyOtherQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class ProgrammeActivityController extends Controller
{
    use ScopesProgrammeEntryAccess;

    #[OA\Schema(
        schema: "ProgrammeActivity",
        type: "object",
        properties: [
            new OA\Property(property: "id", type: "integer", example: 78),
            new OA\Property(property: "programme_entry_id", type: "integer", example: 45),
            new OA\Property(property: "activity_item_id", type: "integer", example: 12),
            new OA\Property(property: "is_primary", type: "boolean", example: true),
            new OA\Property(property: "inclusion_group", type: "string", example: "gender", nullable: true),
            new OA\Property(property: "inclusion_type", type: "string", example: "girls", nullable: true),
            new OA\Property(
                property: "activity_levels",
                type: "array",
                items: new OA\Items(
                    properties: [
                        new OA\Property(property: "id", type: "integer"),
                        new OA\Property(property: "education_level_id", type: "integer"),
                    ]
                )
            ),
            new OA\Property(property: "created_at", type: "string", format: "date-time"),
            new OA\Property(property: "updated_at", type: "string", format: "date-time"),
        ]
    )]

    #[OA\Get(
        path: "/programme-entries/{programmeEntry}/activities",
        summary: "Get Section 2 activities for a programme entry",
        security: [["bearerAuth" => []]],
        tags: ["Programme Activities"],
        parameters: [
            new OA\Parameter(name: "programmeEntry", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(response: 200, description: "List of activities for programme entry"),
            new OA\Response(response: 404, description: "Programme entry not found, or caller not authorized"),
        ]
    )]
    public function index(Request $request, ProgrammeEntry $programmeEntry)
    {
        if (!$this->canView($request, $programmeEntry)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json([
            'data' => $programmeEntry->activities()
                ->with(['activityItem', 'activityLevels.educationLevel'])
                ->get(),
        ]);
    }

    #[OA\Post(
        path: "/programme-entries/{programmeEntry}/activities",
        summary: "Save Section 2 activity selections for a programme entry",
        description: "Accepts one or more activity selections. Rejects inactive or deprecated taxonomy items. When a selected item is flagged \"Other\", the free-text value is captured in the taxonomy other-queue for later admin review.",
        security: [["bearerAuth" => []]],
        tags: ["Programme Activities"],
        parameters: [
            new OA\Parameter(name: "programmeEntry", in: "path", required: true, schema: new OA\Schema(type: "integer")),
        ],
        responses: [
            new OA\Response(response: 201, description: "Activities saved successfully"),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Programme entry not found, or caller not authorized"),
            new OA\Response(response: 422, description: "Validation failed"),
        ]
    )]
    public function store(StoreProgrammeActivityRequest $request, ProgrammeEntry $programmeEntry)
    {
        if (!$this->canWrite($request, $programmeEntry)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $activitiesData = $request->validated('activities') ?? [];
        $itemIds        = collect($activitiesData)->pluck('activity_item_id')->unique();
        $activityItems  = ActivityItem::whereIn('id', $itemIds)->get()->keyBy('id');

        DB::transaction(function () use ($programmeEntry, $activitiesData, $activityItems) {
            $existingIds = ProgrammeActivity::where('programme_entry_id', $programmeEntry->id)->pluck('id');
            if ($existingIds->isNotEmpty()) {
                ProgrammeActivityLevel::whereIn('programme_activity_id', $existingIds)->delete();
                ProgrammeActivity::whereIn('id', $existingIds)->delete();
            }

            foreach ($activitiesData as $data) {
                $item = $activityItems->get($data['activity_item_id']);
                if (!$item) continue;

                $activity = ProgrammeActivity::create([
                    'programme_entry_id' => $programmeEntry->id,
                    'activity_item_id'   => $data['activity_item_id'],
                    'is_primary'         => !empty($data['is_primary']),
                    'inclusion_group'    => $data['inclusion_group'] ?? null,
                    'inclusion_type'     => $data['inclusion_type'] ?? null,
                    'other_text'         => $data['other_text'] ?? null,
                    'taxonomy_version'   => $item->version,
                ]);

                if (!empty($data['education_level_ids']) && is_array($data['education_level_ids'])) {
                    ProgrammeActivityLevel::insert(array_map(fn($lid) => [
                        'programme_activity_id' => $activity->id,
                        'education_level_id'    => $lid,
                        'created_at'            => now(),
                        'updated_at'            => now(),
                    ], $data['education_level_ids']));
                }

                if ($item->is_other && !empty($data['other_text'])) {
                    TaxonomyOtherQueue::create([
                        'programme_entry_id'       => $programmeEntry->id,
                        'item_id'                  => $item->id,
                        'other_text'               => $data['other_text'],
                        'suggested_subcategory_id' => $item->subcategory_id,
                        'frequency'                => 1,
                        'status'                   => 'pending',
                    ]);
                }
            }
        });

        return response()->json([
            'message' => 'Activities saved.',
            'data'    => $programmeEntry->activities()->with(['activityItem', 'activityLevels'])->get(),
        ], 201);
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
