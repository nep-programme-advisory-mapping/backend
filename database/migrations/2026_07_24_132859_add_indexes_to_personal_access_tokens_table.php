<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            // Speeds up login: DELETE WHERE tokenable_id/type AND name = 'api-token'
            $table->index(['tokenable_type', 'tokenable_id'], 'pat_tokenable');

            // Speeds up every authenticated request: Sanctum resolves token by hash
            // (already has unique index on `token` column, so no change needed there)

            // Speeds up token cleanup queries on last_used_at / expires_at
            $table->index('last_used_at', 'pat_last_used_at');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex('pat_tokenable_name');
            $table->dropIndex('pat_last_used_at');
        });
    }
};
