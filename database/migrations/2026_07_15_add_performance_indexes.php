<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programme_entries', function (Blueprint $table) {
            $table->index('fte_staff');
            $table->index('direct_beneficiaries');
            $table->index('is_unverified');
        });

        Schema::table('entry_keywords', function (Blueprint $table) {
            $table->index('keyword');
        });

        Schema::table('programme_activities', function (Blueprint $table) {
            $table->index('programme_entry_id');
        });

        Schema::table('government_agreements', function (Blueprint $table) {
            $table->index('programme_entry_id');
        });
    }

    public function down(): void
    {
        Schema::table('programme_entries', function (Blueprint $table) {
            $table->dropIndex(['fte_staff']);
            $table->dropIndex(['direct_beneficiaries']);
            $table->dropIndex(['is_unverified']);
        });

        Schema::table('entry_keywords', function (Blueprint $table) {
            $table->dropIndex(['keyword']);
        });

        Schema::table('programme_activities', function (Blueprint $table) {
            $table->dropIndex(['programme_entry_id']);
        });

        Schema::table('government_agreements', function (Blueprint $table) {
            $table->dropIndex(['programme_entry_id']);
        });
    }
};