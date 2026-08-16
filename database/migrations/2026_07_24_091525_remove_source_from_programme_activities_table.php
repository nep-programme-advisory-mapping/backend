<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programme_activities', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }

    public function down(): void
    {
        Schema::table('programme_activities', function (Blueprint $table) {
            $table->enum('source', ['ai_confirmed', 'ai_modified', 'human_entered'])->default('human_entered');
        });
    }
};
