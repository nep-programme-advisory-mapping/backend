<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A draft created via autosave can now exist before the user has typed a
     * programme name or picked a start year (see StoreProgrammeEntryRequest /
     * UpdateProgrammeEntryRequest, which only require these once the entry is
     * actually being published) — the column-level NOT NULL constraint
     * needs to allow that too, or the very first empty-draft insert 500s.
     */
    public function up(): void
    {
        Schema::table('programme_entries', function (Blueprint $table) {
            $table->string('programme_name')->nullable()->change();
            $table->integer('start_year')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Backfill any nulls left by drafts before reinstating NOT NULL, so
        // the rollback itself doesn't fail on existing empty-draft rows.
        DB::table('programme_entries')->whereNull('programme_name')->update(['programme_name' => '']);
        DB::table('programme_entries')->whereNull('start_year')->update(['start_year' => 0]);

        Schema::table('programme_entries', function (Blueprint $table) {
            $table->string('programme_name')->nullable(false)->change();
            $table->integer('start_year')->nullable(false)->change();
        });
    }
};
