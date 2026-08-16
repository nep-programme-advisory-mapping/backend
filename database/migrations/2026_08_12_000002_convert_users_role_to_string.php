<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The users.role column was a MySQL ENUM hard-limited to the three
     * original roles ('nep_admin', 'nep_coordinator', 'member_org') — a
     * schema-level hardcode that silently truncated/rejected any other
     * value no matter what the application layer validated. A dynamically
     * created role could never actually become a user's primary role.
     * Converting it to a plain string makes it match the `roles` table:
     * any role name that exists there is a valid value here.
     *
     * Raw SQL (rather than Schema::table()->change()) avoids requiring
     * doctrine/dbal, which this project doesn't have installed.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY role VARCHAR(255) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN role TYPE VARCHAR(255)');
        }
        // sqlite has no real column typing/enum enforcement to relax.
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('nep_admin','nep_coordinator','member_org') NOT NULL");
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN role TYPE VARCHAR(255)');
        }
    }
};
