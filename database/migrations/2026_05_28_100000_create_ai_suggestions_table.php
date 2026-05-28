<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('person_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('entity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('calendar_event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('reason');
            $table->string('suggested_action');
            $table->dateTime('suggested_due_at')->nullable();
            $table->string('priority')->default('medium');
            $table->string('status')->default('pending');
            $table->string('source')->nullable();
            $table->integer('score')->default(0);
            $table->json('metadata')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('postponed_until')->nullable();
            $table->dateTime('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('ignored_at')->nullable();
            $table->foreignId('ignored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('converted_calendar_event_id')->nullable()->constrained('calendar_events')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'user_id', 'status', 'score']);
            $table->index(['tenant_id', 'deal_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_suggestions');
    }
};
