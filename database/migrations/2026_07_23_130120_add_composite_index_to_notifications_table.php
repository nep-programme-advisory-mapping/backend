<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Covers: WHERE notifiable_type = ? AND notifiable_id = ? ORDER BY created_at DESC LIMIT 30
            $table->index(['notifiable_type', 'notifiable_id', 'created_at'], 'notif_notifiable_created');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notif_notifiable_created');
        });
    }
};
