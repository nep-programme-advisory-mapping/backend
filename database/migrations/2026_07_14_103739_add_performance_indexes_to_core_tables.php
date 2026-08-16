<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programme_entries', function (Blueprint $table) {
            $table->index('budget_band_id');
            $table->index('start_year');
        });

        Schema::table('programme_activities', function (Blueprint $table) {
            $table->index('activity_item_id');
        });

        Schema::table('taxonomy_items', function (Blueprint $table) {
            $table->index('subcategory_id');
            $table->index('is_active');
        });

        Schema::table('taxonomy_subcategories', function (Blueprint $table) {
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('programme_entries', function (Blueprint $table) {
            $table->dropIndex(['budget_band_id']);
            $table->dropIndex(['start_year']);
        });

        Schema::table('programme_activities', function (Blueprint $table) {
            $table->dropIndex(['activity_item_id']);
        });

        Schema::table('taxonomy_items', function (Blueprint $table) {
            $table->dropIndex(['subcategory_id']);
            $table->dropIndex(['is_active']);
        });

        Schema::table('taxonomy_subcategories', function (Blueprint $table) {
            $table->dropIndex(['category_id']);
        });
    }
};