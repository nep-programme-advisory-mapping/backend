<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Backfill taxonomy_version for existing programme_activities
        DB::table('programme_activities')
            ->whereNull('taxonomy_version')
            ->join('taxonomy_items', 'programme_activities.activity_item_id', '=', 'taxonomy_items.id')
            ->update([
                'programme_activities.taxonomy_version' => DB::raw('taxonomy_items.version'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Set taxonomy_version back to NULL for rows that were backfilled
        DB::table('programme_activities')
            ->whereNotNull('taxonomy_version')
            ->update([
                'programme_activities.taxonomy_version' => null,
            ]);
    }
};