<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_chat_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role');
            $table->text('content');
            $table->string('intent')->nullable();
            $table->json('metadata')->nullable();
            $table->json('created_records')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'ai_chat_conversation_id', 'created_at'], 'ai_msg_tenant_conversation_created_idx');
            $table->index(['tenant_id', 'intent'], 'ai_msg_tenant_intent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
    }
};
