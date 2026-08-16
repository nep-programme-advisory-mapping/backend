<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxonomy_other_queues', function (Blueprint $table) {
            $table->text('other_text')->after('item_id');
        });
    }

    public function down(): void
    {
        Schema::table('taxonomy_other_queues', function (Blueprint $table) {
            $table->dropColumn('other_text');
        });
    }
};
