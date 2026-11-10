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
        Schema::create('entry_collaborations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_entry_id')->constrained('programme_entries')->onDelete('cascade');
            // Free text by design: these are private organisations or NGOs
            // that may not exist in the organisations table.
            $table->string('organisation_name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entry_collaborations');
    }
};
