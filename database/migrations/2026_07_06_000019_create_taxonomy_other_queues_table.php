<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('taxonomy_other_queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_entry_id')->constrained('programme_entries')->onDelete('cascade');
            $table->foreignId('item_id')->constrained('taxonomy_items')->onDelete('cascade');
            $table->foreignId('suggested_subcategory_id')->constrained('taxonomy_subcategories')->onDelete('cascade');
            $table->foreignId('promoted_item_id')->nullable()->constrained('taxonomy_items')->onDelete('set null');
            $table->integer('frequency')->default(1);
            $table->string('status');
            $table->timestamps();
        });
    }

    
    public function down(): void
    {
        Schema::dropIfExists('taxonomy_other_queues');
    }
};
