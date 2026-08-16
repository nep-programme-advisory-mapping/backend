<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces the hardcoded `$user->role === 'nep_admin'` bypass in
     * User::hasPermission() with a data-driven flag: any role (built-in or
     * admin-created) flagged is_super_admin automatically has every
     * permission, and automatically receives every newly-created permission.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('is_system');
        });

        // Backfill: the pre-existing nep_admin role becomes the super admin.
        Role::query()->where('name', 'nep_admin')->update(['is_super_admin' => true]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });
    }
};
