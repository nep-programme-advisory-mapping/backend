<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdviserProgrammeEntryRequest;
use App\Models\AdvisoryNote;
use App\Models\EntryKeyword;
use App\Models\ProgrammeActivity;
use App\Models\ProgrammeActivityLevel;
use App\Models\ProgrammeLocation;
use App\Models\User;
use App\Notifications\ProgrammeEntryCreatedForOrg;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdviserProgrammeEntryController extends Controller
{
    public function createProgrammeEntry(int $id, StoreAdviserProgrammeEntryRequest $request): JsonResponse
    {
        AdvisoryNote::findOrFail($id);

        $entry = DB::transaction(function () use ($request) {
            $entry = \App\Models\ProgrammeEntry::create([
                'organisation_id'        => $request->input('organisation_id'),
                'programme_name'         => $request->input('programme_name'),
                'start_year'             => $request->input('start_year'),
                'end_year'               => $request->boolean('ongoing') ? null : $request->input('end_year'),
                'ongoing'                => $request->boolean('ongoing', false),
                'fte_staff'              => $request->input('fte_staff'),
                'direct_beneficiaries'   => $request->input('direct_beneficiaries'),
                'indirect_beneficiaries' => $request->input('indirect_beneficiaries'),
                'method'                 => $request->input('method'),
                'budget_band_id'         => $request->input('budget_band_id'),
                'is_submitted'           => false,
            ]);

            // Activities
            $itemIds   = collect($request->input('activities', []))->pluck('activity_item_id')->unique();
            $itemsById = \App\Models\ActivityItem::whereIn('id', $itemIds)->get()->keyBy('id');

            foreach ($request->input('activities', []) as $actData) {
                $activityItem = $itemsById->get($actData['activity_item_id']);
                if (!$activityItem) continue;

                $activity = ProgrammeActivity::create([
                    'programme_entry_id' => $entry->id,
                    'activity_item_id'   => $actData['activity_item_id'],
                    'is_primary'         => $actData['is_primary'] ?? false,
                    'inclusion_group'    => $actData['inclusion_group'] ?? null,
                    'inclusion_type'     => $actData['inclusion_type'] ?? null,
                    'source'             => 'ai_confirmed',
                    'taxonomy_version'   => $activityItem->version,
                ]);

                ProgrammeActivityLevel::insert(array_map(fn($lid) => [
                    'programme_activity_id' => $activity->id,
                    'education_level_id'    => $lid,
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ], $actData['education_level_ids'] ?? [1]));
            }

            // Geography
            $locations = [];
            foreach ($request->input('geography.province_ids', []) as $provinceId) {
                $locations[] = [
                    'programme_entry_id' => $entry->id,
                    'province_id'        => $provinceId,
                    'district_id'        => null,
                    'commune_id'         => null,
                    'village_id'         => null,
                    'country'            => null,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ];
            }
            foreach ($request->input('geography.other_countries', []) as $country) {
                $locations[] = [
                    'programme_entry_id' => $entry->id,
                    'province_id'        => null,
                    'district_id'        => null,
                    'commune_id'         => null,
                    'village_id'         => null,
                    'country'            => $country,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ];
            }
            if (!empty($locations)) {
                ProgrammeLocation::insert($locations);
            }

            // Keywords
            $keywords = array_filter(array_map('trim', $request->input('keywords', [])));
            if (!empty($keywords)) {
                EntryKeyword::insert(array_map(fn($kw) => [
                    'programme_entry_id' => $entry->id,
                    'keyword'            => $kw,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ], $keywords));
            }

            return $entry;
        });

        // Notify all active member_org users of the target organisation
        User::where('organisation_id', $entry->organisation_id)
            ->where('role', 'member_org')
            ->where('status', 'active')
            ->get()
            ->each(fn(User $user) => $user->notify(new ProgrammeEntryCreatedForOrg($entry)));

        return response()->json([
            'message' => 'Programme entry draft created from AI analysis.',
            'data'    => ['id' => $entry->id],
        ], 201);
    }
}
