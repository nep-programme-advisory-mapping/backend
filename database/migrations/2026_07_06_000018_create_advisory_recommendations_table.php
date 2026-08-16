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
        Schema::create('advisory_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advisory_note_id')->constrained('advisory_notes')->onDelete('cascade');
            $table->foreignId('programme_entry_id')->nullable()->constrained('programme_entries')->onDelete('cascade');
            $table->string('organisation_name')->nullable();
            $table->string('type');
            $table->text('relational');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advisory_recommendations');
    }
};
