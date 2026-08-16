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
        Schema::create('advisory_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assign_to_staff_user_id')->nullable()->constrained('staff_users')->onDelete('set null');
            $table->string('submitting_party');
            $table->string('document_name');
            $table->string('analysis_scope');
            $table->text('analysis_scope_detail')->nullable();
            $table->string('status');
            $table->text('section_profile')->nullable();
            $table->text('section_gaps')->nullable();
            $table->text('section_coordinators_notes')->nullable();
            $table->string('final_note_file')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            
            // Performance indexes for common queries
            $table->index('status');
            $table->index('submitted_at');
            $table->index('analysis_scope');
            $table->index(['status', 'submitted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advisory_notes');
    }
};
