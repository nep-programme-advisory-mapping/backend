<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // Nullable + set-null-on-delete: the log must survive the user
            // who performed the action being deleted later.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_label')->nullable(); // name/email snapshot, kept even if user_id goes null
            $table->string('action'); // e.g. 'deleted'
            $table->string('auditable_type'); // e.g. 'User', 'Role', 'Permission', 'PolicyDocument'
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // snapshot of the record at the time of the action
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
