<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // users: filtered by role, status, organisation_id across UserManagement,
        // notifications dispatch, and programme entry store
        Schema::table('users', function (Blueprint $table) {
            $table->index('role', 'users_role');
            $table->index('status', 'users_status');
            $table->index(['organisation_id', 'status'], 'users_org_status');
        });

        // programme_geography: province_id / district_id / commune_id used in
        // BuildsMapQuery whereHas('locations') and MapGeoJsonController counts
        Schema::table('programme_geography', function (Blueprint $table) {
            $table->index('province_id', 'pg_province');
            $table->index('district_id', 'pg_district');
            $table->index('commune_id', 'pg_commune');
            // composite for geojson COUNT(DISTINCT programme_entry_id) GROUP BY province_id
            $table->index(['province_id', 'programme_entry_id'], 'pg_province_entry');
        });

        // programme_activities: BuildsMapQuery whereHas('activities') filters on
        // inclusion_group, inclusion_type; whereExists on activity_levels
        Schema::table('programme_activities', function (Blueprint $table) {
            $table->index('inclusion_group', 'pa_inclusion_group');
            $table->index('inclusion_type', 'pa_inclusion_type');
            // composite covers the common (entry + item) join pattern
            $table->index(['programme_entry_id', 'activity_item_id'], 'pa_entry_item');
        });

        // programme_activity_levels: whereExists filters on education_level_id
        // joined back to programme_activities.id
        Schema::table('programme_activity_levels', function (Blueprint $table) {
            $table->index(['programme_activity_id', 'education_level_id'], 'pal_activity_level');
        });

        // advisory_notes: coordinator_id used in DashboardController recentActivity
        // and AdviserSubmissionController listing
        Schema::table('advisory_notes', function (Blueprint $table) {
            $table->index('coordinator_id', 'an_coordinator');
        });

        // notifications: read_at used in unreadNotifications() scope and
        // NotificationController markAllRead bulk update
        Schema::table('notifications', function (Blueprint $table) {
            $table->index('read_at', 'notif_read_at');
        });

        // entry_keywords: programme_entry_id used in eager loads and whereHas keyword filter
        Schema::table('entry_keywords', function (Blueprint $table) {
            $table->index('programme_entry_id', 'ek_entry');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_role');
            $table->dropIndex('users_status');
            $table->dropIndex('users_org_status');
        });

        Schema::table('programme_geography', function (Blueprint $table) {
            $table->dropIndex('pg_province');
            $table->dropIndex('pg_district');
            $table->dropIndex('pg_commune');
            $table->dropIndex('pg_province_entry');
        });

        Schema::table('programme_activities', function (Blueprint $table) {
            $table->dropIndex('pa_inclusion_group');
            $table->dropIndex('pa_inclusion_type');
            $table->dropIndex('pa_entry_item');
        });

        Schema::table('programme_activity_levels', function (Blueprint $table) {
            $table->dropIndex('pal_activity_level');
        });

        Schema::table('advisory_notes', function (Blueprint $table) {
            $table->dropIndex('an_coordinator');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notif_read_at');
        });

        Schema::table('entry_keywords', function (Blueprint $table) {
            $table->dropIndex('ek_entry');
        });
    }
};
