<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deal_follow_up_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_follow_up_id')->constrained()->cascadeOnDelete();
            $table->foreignId('follow_up_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_email');
            $table->string('subject');
            $table->text('body');
            $table->dateTime('sent_at');
            $table->timestamps();

            $table->index(['tenant_id', 'deal_id', 'sent_at']);
            $table->index(['deal_follow_up_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_follow_up_emails');
    }
};
