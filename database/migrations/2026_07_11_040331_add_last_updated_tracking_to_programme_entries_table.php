<?php
// database/migrations/2026_07_11_000000_add_last_updated_tracking_to_programme_entries_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programme_entries', function (Blueprint $table) {
            $table->timestamp('last_updated_at')->nullable()->after('verified_date');
            $table->foreignId('last_updated_by')
                ->nullable()
                ->after('last_updated_at')
                ->constrained('users')
                ->nullOnDelete();
        });

        DB::table('programme_entries')->update([
            'last_updated_at' => DB::raw('created_at'),
        ]);
    }
    public function down(): void
    {
        Schema::table('programme_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_updated_by');
            $table->dropColumn('last_updated_at');
        });
    }
};