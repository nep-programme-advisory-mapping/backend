<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('programme_geography', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_entry_id')->constrained('programme_entries')->onDelete('cascade');
            $table->foreignId('province_id')->nullable()->constrained('provinces')->onDelete('cascade');
            $table->foreignId('district_id')->nullable()->constrained('districts')->onDelete('cascade');
            $table->string('country')->nullable(); // "Other countries" free-text field
            $table->timestamps();
        });
    }

    
    public function down(): void
    {
        Schema::dropIfExists('programme_geography');
    }
};
