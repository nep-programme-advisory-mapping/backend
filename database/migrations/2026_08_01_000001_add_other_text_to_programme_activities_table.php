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
            if (!Schema::hasColumn('programme_activities', 'other_text')) {
                $table->text('other_text')->nullable()->after('inclusion_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programme_activities', function (Blueprint $table) {
            if (Schema::hasColumn('programme_activities', 'other_text')) {
                $table->dropColumn('other_text');
            }
        });
    }
};
