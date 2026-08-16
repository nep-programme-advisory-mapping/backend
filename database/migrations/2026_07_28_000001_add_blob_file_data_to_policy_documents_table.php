<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('policy_documents', function (Blueprint $table) {
            $table->string('file_name')->nullable()->after('file_url');
            $table->string('mime_type', 100)->nullable()->after('file_name');
            $table->unsignedBigInteger('file_size')->nullable()->after('mime_type');
        });

        // Add LONGBLOB column for storing binary file data directly in database BLOB
        DB::statement('ALTER TABLE policy_documents ADD COLUMN file_data LONGBLOB NULL AFTER file_size');
    }

    public function down(): void
    {
        Schema::table('policy_documents', function (Blueprint $table) {
            $table->dropColumn(['file_name', 'mime_type', 'file_size', 'file_data']);
        });
    }
};
