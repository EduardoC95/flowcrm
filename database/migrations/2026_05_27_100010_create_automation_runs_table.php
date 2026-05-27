<?php

use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignId('automation_rule_id')->constrained('automation_rules')->cascadeOnDelete();
            $table->foreignIdFor(Deal::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(CalendarEvent::class)->nullable()->constrained()->nullOnDelete();
            $table->string('status');
            $table->text('result')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('ran_at');
            $table->timestamps();

            $table->index(['tenant_id', 'automation_rule_id', 'status']);
            $table->index(['automation_rule_id', 'deal_id', 'ran_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
    }
};
