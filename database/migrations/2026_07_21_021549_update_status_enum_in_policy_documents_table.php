<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE policy_documents MODIFY COLUMN status ENUM('active', 'inactive', 'superseded') NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE policy_documents MODIFY COLUMN status ENUM('active', 'superseded') NOT NULL DEFAULT 'active'");
    }
};