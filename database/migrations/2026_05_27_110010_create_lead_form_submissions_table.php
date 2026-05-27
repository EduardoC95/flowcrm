<?php

use App\Models\Deal;
use App\Models\Person;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignId('lead_form_id')->constrained('lead_forms')->cascadeOnDelete();
            $table->json('payload');
            $table->string('source_url')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->foreignIdFor(Person::class, 'created_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignIdFor(Deal::class, 'created_deal_id')->nullable()->constrained('deals')->nullOnDelete();
            $table->boolean('captcha_passed')->default(false);
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['tenant_id', 'lead_form_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_form_submissions');
    }
};
