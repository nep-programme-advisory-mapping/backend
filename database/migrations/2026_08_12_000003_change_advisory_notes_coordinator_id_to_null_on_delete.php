<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * advisory_notes.coordinator_id used ON DELETE CASCADE — deleting a
     * coordinator's user account silently deleted every advisory note ever
     * assigned to them, including delivered ones. Every other "who touched
     * this record" FK in the schema (programme_entries.created_by,
     * .last_updated_by, users.deactivated_by) uses ON DELETE SET NULL; this
     * brings coordinator_id in line with that pattern so deleting a user
     * detaches their advisory notes instead of destroying them.
     */
    public function up(): void
    {
        Schema::table('advisory_notes', function (Blueprint $table) {
            $table->dropForeign(['coordinator_id']);
        });

        Schema::table('advisory_notes', function (Blueprint $table) {
            $table->foreign('coordinator_id')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('advisory_notes', function (Blueprint $table) {
            $table->dropForeign(['coordinator_id']);
        });

        Schema::table('advisory_notes', function (Blueprint $table) {
            $table->foreign('coordinator_id')
                ->references('id')->on('users')
                ->cascadeOnDelete();
        });
    }
};
