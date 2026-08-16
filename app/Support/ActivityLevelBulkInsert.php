<?php

namespace App\Support;

class ActivityLevelBulkInsert
{
    public static function prepare(array $activityData): array
    {
        return array_map(
            fn ($levelId) => ['education_level_id' => $levelId],
            $activityData['education_level_ids']
        );
    }
}