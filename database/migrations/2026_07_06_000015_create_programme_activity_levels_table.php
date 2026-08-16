<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('programme_activity_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_activity_id')->constrained('programme_activities')->onDelete('cascade');
            $table->foreignId('education_level_id')->constrained('education_levels')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('programme_activity_levels');
    }
};
