<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('programme_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_entry_id')->constrained('programme_entries')->onDelete('cascade');
            $table->foreignId('activity_item_id')->constrained('taxonomy_items')->onDelete('cascade');
            $table->boolean('is_primary')->default(false);
            $table->string('inclusion_group')->nullable();
            $table->string('inclusion_type')->nullable();
            // Education levels are stored in a separate join/pivot table (programme_activity_levels) to support multi-select.
            $table->enum('source', ['ai_confirmed', 'ai_modified', 'human_entered'])->default('human_entered');
            $table->timestamps();
        });
    }

    
    public function down(): void
    {
        Schema::dropIfExists('programme_activities');
    }
};
