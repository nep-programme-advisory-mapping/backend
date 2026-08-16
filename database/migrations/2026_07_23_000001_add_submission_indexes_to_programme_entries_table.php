<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programme_entries', function (Blueprint $table) {
            // submitted/draft list: WHERE is_submitted = ? ORDER BY id DESC
            $table->index(['is_submitted', 'id'], 'pe_submitted_id');

            // member_org scoped list: WHERE is_submitted = ? AND organisation_id = ? ORDER BY id DESC
            $table->index(['organisation_id', 'is_submitted', 'id'], 'pe_org_submitted_id');

            // my-drafts: WHERE is_submitted = 0 AND created_by = ? ORDER BY updated_at DESC
            $table->index(['created_by', 'is_submitted'], 'pe_created_by_submitted');
        });
    }

    public function down(): void
    {
        Schema::table('programme_entries', function (Blueprint $table) {
            $table->dropIndex('pe_submitted_id');
            $table->dropIndex('pe_org_submitted_id');
            $table->dropIndex('pe_created_by_submitted');
        });
    }
};
