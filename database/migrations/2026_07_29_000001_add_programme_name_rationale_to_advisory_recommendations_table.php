<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advisory_recommendations', function (Blueprint $table) {
            $table->string('programme_name')->nullable()->after('organisation_name');
            $table->text('rationale')->nullable()->after('relational');
        });
    }

    public function down(): void
    {
        Schema::table('advisory_recommendations', function (Blueprint $table) {
            $table->dropColumn(['programme_name', 'rationale']);
        });
    }
};
