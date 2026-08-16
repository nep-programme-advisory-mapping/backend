<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advisory_notes', function (Blueprint $table) {
            $table->foreignId('programme_entry_id')
                ->nullable()
                ->after('coordinator_id')
                ->constrained('programme_entries')
                ->nullOnDelete();

            $table->index('programme_entry_id');
        });
    }

    public function down(): void
    {
        Schema::table('advisory_notes', function (Blueprint $table) {
            $table->dropForeign(['programme_entry_id']);
            $table->dropColumn('programme_entry_id');
        });
    }
};
