<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advisory_notes', function (Blueprint $table) {
            $table->longText('document_text')->nullable()->after('document_name');
        });
    }

    public function down(): void
    {
        Schema::table('advisory_notes', function (Blueprint $table) {
            $table->dropColumn('document_text');
        });
    }
};
