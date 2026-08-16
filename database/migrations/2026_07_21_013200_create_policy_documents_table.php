<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('authority');
            $table->string('version', 20);
            $table->date('date');
            $table->enum('status', ['active', 'inactive', 'superseded'])->default('active');
            $table->text('file_url')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('policy_documents');
    }
};