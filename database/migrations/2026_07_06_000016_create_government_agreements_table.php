<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('government_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_entry_id')->constrained('programme_entries')->onDelete('cascade');
            $table->enum('counterpart_agency', [
                'MoEYS national level',
                'Provincial Office of Education',
                'District Office of Education',
                'Teacher Education Institution',
                'specific school or cluster',
                'other government ministry'
            ]);
            $table->enum('status', [
                'active',
                'expired',
                'under renewal',
                'under negotiation'
            ]);
            $table->string('institution_name');
            $table->enum('nature', [
                'MoU',
                'Letter of Understanding',
                'official approval letter',
                'informal working arrangement'
            ]);
            $table->timestamps();
        });
    }

    
    public function down(): void
    {
        Schema::dropIfExists('government_agreements');
    }
};
