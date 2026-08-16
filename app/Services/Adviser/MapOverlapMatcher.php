<?php

namespace App\Services\Adviser;

use App\Models\ProgrammeEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;


class MapOverlapMatcher
{
    /**
     * @param array $programmeProfile extracted programme profile from SRS 6.2 step 3
     * Expected keys (all optional):
     * - activities: array of taxonomy ids/names/keys
     *   - category_ids[] / subcategory_ids[] / item_ids[]
     *   - education_level_ids[]
     *   - inclusion_groups[]
     *   - inclusion_types[]
     * - geography: array
     *   - province_ids[]
     *   - district_ids[]
     *   - commune_ids[]
     *   - village_ids[]
     * - audiences: array
     *   - inclusion_groups[] / inclusion_types[] (mapped to programme_activity.inclusion_group/type)
     * - scope: string (optional) one of: full map | geographic subset | thematic subset
     * - scope_detail: string (optional)
     *
     * @param string $analysisScope one of: full map, geographic subset, thematic subset
     */
    public function match(array $programmeProfile, string $analysisScope = 'full map'): Builder
    {
        // Only match against submitted entries that have an organisation
        $q = ProgrammeEntry::query()
            ->distinct()
            ->where('is_submitted', true)
            ->whereNotNull('organisation_id');

        $analysisScope = $analysisScope ?: 'full map';

        $hasActivitySignals = $this->hasAnyActivitySignals($programmeProfile);
        $hasGeographySignals = $this->hasAnyGeographySignals($programmeProfile);

        $activityPredicate  = $this->buildActivityOverlapPredicate($programmeProfile);
        $geographyPredicate = $this->buildGeographyOverlapPredicate($programmeProfile);

        // Only flag a true duplicate when BOTH geography AND activity overlap.
        // A programme in the same place but doing something different is not a duplicate.
        // A programme doing the same thing but in a different place is not a duplicate.
        if ($hasGeographySignals && $hasActivitySignals) {
            $q->where($geographyPredicate)->where($activityPredicate);
            return $q;
        }

        // If only one dimension is available, fall back to that single dimension.
        if ($hasGeographySignals) {
            $q->where($geographyPredicate);
            return $q;
        }

        if ($hasActivitySignals) {
            $q->where($activityPredicate);
            return $q;
        }

        // No signals at all — return nothing.
        $q->whereRaw('1=0');
        return $q;
    }

    private function hasAnyActivitySignals(array $profile): bool
    {
        $activities = $profile['activities'] ?? [];
        $categoryIds = $activities['category_ids'] ?? [];
        $subcategoryIds = $activities['subcategory_ids'] ?? [];
        $itemIds = $activities['item_ids'] ?? [];
        $educationLevelIds = $activities['education_level_ids'] ?? [];
        $inclusionGroups = $activities['inclusion_groups'] ?? [];
        $inclusionTypes = $activities['inclusion_types'] ?? [];

        return !empty($categoryIds)
            || !empty($subcategoryIds)
            || !empty($itemIds)
            || !empty($educationLevelIds)
            || !empty($inclusionGroups)
            || !empty($inclusionTypes);
    }

    private function hasAnyGeographySignals(array $profile): bool
    {
        $geo = $profile['geography'] ?? [];
        return !empty($geo['province_ids'] ?? [])
            || !empty($geo['district_ids'] ?? [])
            || !empty($geo['commune_ids'] ?? [])
            || !empty($geo['village_ids'] ?? []);
    }

    private function hasAnyAudienceSignals(array $profile): bool
    {
        $aud = $profile['audiences'] ?? [];
        $groups = $aud['inclusion_groups'] ?? [];
        $types = $aud['inclusion_types'] ?? [];
        return !empty($groups) || !empty($types);
    }

    /**
     * Returns a Closure-style predicate (Builder callback) that can be used inside where().
     */
    private function buildActivityOverlapPredicate(array $profile): callable
    {
        return function (Builder $q) use ($profile) {
            $activities = $profile['activities'] ?? [];

            $categoryIds = $activities['category_ids'] ?? [];
            $subcategoryIds = $activities['subcategory_ids'] ?? [];
            $itemIds = $activities['item_ids'] ?? [];
            $educationLevelIds = $activities['education_level_ids'] ?? [];
            $inclusionGroups = $activities['inclusion_groups'] ?? [];
            $inclusionTypes = $activities['inclusion_types'] ?? [];

            // If no activity signals are provided, force a false predicate so OR doesn't match by accident.
            if (empty($categoryIds) && empty($subcategoryIds) && empty($itemIds) && empty($educationLevelIds) && empty($inclusionGroups) && empty($inclusionTypes)) {
                $q->whereRaw('1=0');
                return;
            }

            $q->whereHas('activities', function (Builder $aq) use (
                $categoryIds,
                $subcategoryIds,
                $itemIds,
                $educationLevelIds,
                $inclusionGroups,
                $inclusionTypes
            ) {
                // item_ids, subcategory_ids, category_ids are OR — any matching activity item qualifies
                $aq->where(function (Builder $taxQ) use ($itemIds, $subcategoryIds, $categoryIds) {
                    if (!empty($itemIds)) {
                        $taxQ->orWhereIn('activity_item_id', $itemIds);
                    }
                    if (!empty($subcategoryIds) || !empty($categoryIds)) {
                        $taxQ->orWhereHas('activityItem.subcategory', function (Builder $subQ) use ($subcategoryIds, $categoryIds) {
                            if (!empty($subcategoryIds)) {
                                $subQ->whereIn('id', $subcategoryIds);
                            }
                            if (!empty($categoryIds)) {
                                $subQ->whereIn('category_id', $categoryIds);
                            }
                        });
                    }
                });

                if (!empty($educationLevelIds)) {
                    $aq->whereExists(function ($sub) use ($educationLevelIds) {
                        $sub->select(DB::raw(1))
                            ->from('programme_activity_levels')
                            ->whereColumn('programme_activity_levels.programme_activity_id', 'programme_activities.id')
                            ->whereIn('programme_activity_levels.education_level_id', $educationLevelIds);
                    });
                }

                if (!empty($inclusionGroups)) {
                    $aq->whereIn('inclusion_group', $inclusionGroups);
                }

                if (!empty($inclusionTypes)) {
                    $aq->whereIn('inclusion_type', $inclusionTypes);
                }
            });
        };
    }

    private function buildAudienceOverlapPredicate(array $profile): callable
    {
        return function (Builder $q) use ($profile) {
            $aud = $profile['audiences'] ?? [];

            $groups = $aud['inclusion_groups'] ?? [];
            $types = $aud['inclusion_types'] ?? [];

            if (empty($groups) && empty($types)) {
                $q->whereRaw('1=0');
                return;
            }

            $q->whereHas('activities', function (Builder $aq) use ($groups, $types) {
                if (!empty($groups)) {
                    $aq->whereIn('inclusion_group', $groups);
                }
                if (!empty($types)) {
                    $aq->whereIn('inclusion_type', $types);
                }
            });
        };
    }

    private function buildGeographyOverlapPredicate(array $profile): callable
    {
        return function (Builder $q) use ($profile) {
            $geo = $profile['geography'] ?? [];

            $provinceIds = $geo['province_ids'] ?? [];
            $districtIds = $geo['district_ids'] ?? [];
            $communeIds  = $geo['commune_ids']  ?? [];
            $villageIds  = $geo['village_ids']  ?? [];

            if (empty($provinceIds) && empty($districtIds) && empty($communeIds) && empty($villageIds)) {
                $q->whereRaw('1=0');
                return;
            }

            $q->where(function (Builder $outer) use ($provinceIds, $districtIds, $communeIds, $villageIds) {
                // Match on province_id directly stored on the location row
                if (!empty($provinceIds)) {
                    $outer->orWhereHas('locations', function (Builder $lq) use ($provinceIds) {
                        $lq->whereIn('province_id', $provinceIds);
                    });
                    // Also match entries whose district belongs to one of these provinces
                    $outer->orWhereHas('locations', function (Builder $lq) use ($provinceIds) {
                        $lq->whereNotNull('district_id')
                           ->whereHas('district', function (Builder $dq) use ($provinceIds) {
                               $dq->whereIn('province_id', $provinceIds);
                           });
                    });
                }

                if (!empty($districtIds)) {
                    $outer->orWhereHas('locations', function (Builder $lq) use ($districtIds) {
                        $lq->whereIn('district_id', $districtIds);
                    });
                }

                if (!empty($communeIds)) {
                    $outer->orWhereHas('locations', function (Builder $lq) use ($communeIds) {
                        $lq->whereIn('commune_id', $communeIds);
                    });
                }

                if (!empty($villageIds)) {
                    $outer->orWhereHas('locations', function (Builder $lq) use ($villageIds) {
                        $lq->whereIn('village_id', $villageIds);
                    });
                }
            });
        };
    }
}

