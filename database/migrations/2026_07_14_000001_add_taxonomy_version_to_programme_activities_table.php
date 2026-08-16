<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('programme_activities', function (Blueprint $table) {
            $table->string('taxonomy_version')->nullable()->after('activity_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programme_activities', function (Blueprint $table) {
            $table->dropColumn('taxonomy_version');
        });
    }
};