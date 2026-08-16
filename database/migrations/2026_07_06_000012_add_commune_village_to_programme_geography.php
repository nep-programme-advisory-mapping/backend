<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programme_geography', function (Blueprint $table) {
            $table->foreignId('commune_id')->nullable()->after('district_id')
                ->constrained('communes')->onDelete('cascade');
            $table->foreignId('village_id')->nullable()->after('commune_id')
                ->constrained('villages')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('programme_geography', function (Blueprint $table) {
            $table->dropForeign(['commune_id']);
            $table->dropForeign(['village_id']);
            $table->dropColumn(['commune_id', 'village_id']);
        });
    }
};
