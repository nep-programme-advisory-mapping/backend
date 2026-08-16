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
        Schema::create('programme_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations')->onDelete('cascade');
            $table->foreignId('budget_band_id')->nullable()->constrained('budget_bands')->onDelete('set null');
            $table->string('programme_name');
            $table->integer('start_year');
            $table->integer('end_year')->nullable();
            $table->boolean('ongoing')->default(false);
            $table->decimal('fte_staff', 8, 2)->default(0.00);
            $table->integer('indirect_beneficiaries')->default(0);
            $table->integer('direct_beneficiaries')->default(0);
            $table->text('method')->nullable();
            $table->date('verified_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('programme_entries');
    }
};
