<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\ProgrammeEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

trait BuildsMapQuery
{
    private function buildMapQuery(Request $request, $user): Builder
    {
        $request->validate([
            'entry_ids'                  => 'sometimes',
            'category_id'                => 'sometimes|integer',
            'subcategory_id'             => 'sometimes|integer',
            'item_id'                    => 'sometimes|integer',
            'education_level_id'         => 'sometimes|integer',
            'inclusion_group'            => 'sometimes|string',
            'inclusion_type'             => 'sometimes|string',
            'province_id'                => 'sometimes|integer|exists:provinces,id',
            'district_id'                => 'sometimes|integer|exists:districts,id',
            'commune_id'                 => 'sometimes|integer|exists:communes,id',
            'agreement_counterpart_type' => 'sometimes|string',
            'agreement_status'           => 'sometimes|string',
            'keyword'                    => 'sometimes|string',
            'organisation_name'          => 'sometimes|string',
            'budget_band_id'             => 'sometimes|integer|exists:budget_bands,id',
            'min_staff'                  => 'sometimes|numeric',
            'max_staff'                  => 'sometimes|numeric',
            'min_beneficiaries'          => 'sometimes|integer',
            'max_beneficiaries'          => 'sometimes|integer',
        ]);

        $query = ProgrammeEntry::query()->where('is_submitted', true)->whereNotNull('organisation_id')->distinct();

        if ($request->filled('entry_ids')) {
            $raw = $request->input('entry_ids');
            $ids = is_array($raw) ? $raw : explode(',', (string) $raw);
            $validIds = array_filter(array_map('intval', $ids));
            if (!empty($validIds)) {
                $query->whereIn('id', $validIds);
            }
        }

        if (! $user->hasOrganisationWideAccess()) {
            $query->where('organisation_id', $user->organisation_id);
        }

        if ($request->filled('province_id') || $request->filled('district_id') || $request->filled('commune_id')) {
            $query->whereHas('locations', function ($q) use ($request) {
                if ($request->filled('province_id')) {
                    $provinceId = (int) $request->input('province_id');
                    $q->where(function ($sub) use ($provinceId) {
                        $sub->where('province_id', $provinceId)
                            ->orWhereHas('district', fn($d) => $d->where('province_id', $provinceId));
                    });
                }
                if ($request->filled('district_id')) {
                    $q->where('district_id', (int) $request->input('district_id'));
                }
                if ($request->filled('commune_id')) {
                    $q->where('commune_id', (int) $request->input('commune_id'));
                }
            });
        }

        if ($request->filled('agreement_counterpart_type') || $request->filled('agreement_status')) {
            $query->whereHas('governmentAgreements', function ($q) use ($request) {
                if ($request->filled('agreement_counterpart_type')) {
                    $q->where('counterpart_agency', $request->input('agreement_counterpart_type'));
                }
                if ($request->filled('agreement_status')) {
                    $q->where('status', $request->input('agreement_status'));
                }
            });
        }

        $hasActivityFilter = collect($request->only([
            'category_id', 'subcategory_id', 'item_id',
            'education_level_id', 'inclusion_group', 'inclusion_type',
        ]))->filter(fn($v) => filled($v))->isNotEmpty();

        if ($hasActivityFilter) {
            $query->whereHas('activities', function ($q) use ($request) {
                if ($request->filled('item_id')) {
                    $q->where('activity_item_id', (int) $request->input('item_id'));
                }
                if ($request->filled('subcategory_id') || $request->filled('category_id')) {
                    $q->whereHas('activityItem.subcategory', function ($sub) use ($request) {
                        if ($request->filled('subcategory_id')) {
                            $sub->where('id', (int) $request->input('subcategory_id'));
                        }
                        if ($request->filled('category_id')) {
                            $sub->where('category_id', (int) $request->input('category_id'));
                        }
                    });
                }
                if ($request->filled('education_level_id')) {
                    $levelId = (int) $request->input('education_level_id');
                    $q->whereExists(fn($sub) => $sub->select(DB::raw(1))
                        ->from('programme_activity_levels')
                        ->whereColumn('programme_activity_levels.programme_activity_id', 'programme_activities.id')
                        ->where('programme_activity_levels.education_level_id', $levelId)
                    );
                }
                if ($request->filled('inclusion_group')) {
                    $q->where('inclusion_group', $request->input('inclusion_group'));
                }
                if ($request->filled('inclusion_type')) {
                    $q->where('inclusion_type', $request->input('inclusion_type'));
                }
            });
        }

        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');
            $query->whereHas('keywords', fn($q) => $q->where('keyword', 'LIKE', '%' . $keyword . '%'));
        }

        if ($request->filled('organisation_name')) {
            $orgName = $request->input('organisation_name');
            $query->whereHas('organisation', fn($q) => $q->where('name', 'LIKE', '%' . $orgName . '%'));
        }

        if ($request->filled('budget_band_id')) {
            $query->where('budget_band_id', (int) $request->input('budget_band_id'));
        }
        if ($request->filled('min_staff')) {
            $query->where('fte_staff', '>=', $request->input('min_staff'));
        }
        if ($request->filled('max_staff')) {
            $query->where('fte_staff', '<=', $request->input('max_staff'));
        }
        if ($request->filled('min_beneficiaries')) {
            $query->where('direct_beneficiaries', '>=', (int) $request->input('min_beneficiaries'));
        }
        if ($request->filled('max_beneficiaries')) {
            $query->where('direct_beneficiaries', '<=', (int) $request->input('max_beneficiaries'));
        }

        return $query;
    }
}
