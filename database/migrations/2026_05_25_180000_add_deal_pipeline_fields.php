<?php

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * @var array<int, array{name: string, slug: string, color: string, position: int, is_won: bool, is_lost: bool}>
     */
    private array $defaultStages = [
        ['name' => 'Lead', 'slug' => 'lead', 'color' => '#0ea5e9', 'position' => 1, 'is_won' => false, 'is_lost' => false],
        ['name' => 'Proposta', 'slug' => 'proposal', 'color' => '#8b5cf6', 'position' => 2, 'is_won' => false, 'is_lost' => false],
        ['name' => 'Negociação', 'slug' => 'negotiation', 'color' => '#f59e0b', 'position' => 3, 'is_won' => false, 'is_lost' => false],
        ['name' => 'Follow Up', 'slug' => 'follow-up', 'color' => '#14b8a6', 'position' => 4, 'is_won' => false, 'is_lost' => false],
        ['name' => 'Ganho', 'slug' => 'won', 'color' => '#22c55e', 'position' => 5, 'is_won' => true, 'is_lost' => false],
        ['name' => 'Perdido', 'slug' => 'lost', 'color' => '#ef4444', 'position' => 6, 'is_won' => false, 'is_lost' => true],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('deal_stages')) {
            Schema::create('deal_stages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('slug');
                $table->string('color')->nullable();
                $table->unsignedInteger('position')->default(1);
                $table->boolean('is_won')->default(false);
                $table->boolean('is_lost')->default(false);
                $table->timestamps();

                $table->unique(['tenant_id', 'slug']);
                $table->index(['tenant_id', 'position']);
            });
        }

        Schema::table('deals', function (Blueprint $table) {
            if (! Schema::hasColumn('deals', 'owner_id')) {
                $table->foreignId('owner_id')->nullable()->after('person_id')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('deals', 'deal_stage_id')) {
                $table->foreignId('deal_stage_id')->nullable()->after('owner_id')->constrained('deal_stages')->nullOnDelete();
            }

            if (! Schema::hasColumn('deals', 'probability')) {
                $table->unsignedTinyInteger('probability')->default(0)->after('value');
            }

            if (! Schema::hasColumn('deals', 'priority')) {
                $table->string('priority')->nullable()->after('expected_close_date');
            }

            if (! Schema::hasColumn('deals', 'description')) {
                $table->text('description')->nullable()->after('priority');
            }

            if (! Schema::hasColumn('deals', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable()->after('description');
            }

            if (! Schema::hasColumn('deals', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        $this->seedDefaultStagesForExistingTenants();
        $this->backfillExistingDeals();
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            if (Schema::hasColumn('deals', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            foreach (['last_activity_at', 'description', 'priority', 'probability'] as $column) {
                if (Schema::hasColumn('deals', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('deals', 'deal_stage_id')) {
                $table->dropConstrainedForeignId('deal_stage_id');
            }

            if (Schema::hasColumn('deals', 'owner_id')) {
                $table->dropConstrainedForeignId('owner_id');
            }
        });

        Schema::dropIfExists('deal_stages');
    }

    private function seedDefaultStagesForExistingTenants(): void
    {
        Tenant::query()
            ->withoutGlobalScopes()
            ->get(['id'])
            ->each(function (Tenant $tenant) {
                foreach ($this->defaultStages as $stage) {
                    DB::table('deal_stages')->updateOrInsert(
                        [
                            'tenant_id' => $tenant->id,
                            'slug' => $stage['slug'],
                        ],
                        [
                            'name' => $stage['name'],
                            'color' => $stage['color'],
                            'position' => $stage['position'],
                            'is_won' => $stage['is_won'],
                            'is_lost' => $stage['is_lost'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                }
            });
    }

    private function backfillExistingDeals(): void
    {
        DB::table('deals')
            ->whereNull('deal_stage_id')
            ->orderBy('id')
            ->get(['id', 'tenant_id', 'stage'])
            ->each(function (object $deal) {
                $slug = $this->legacyStageSlug((string) $deal->stage);
                $stageId = DB::table('deal_stages')
                    ->where('tenant_id', $deal->tenant_id)
                    ->where('slug', $slug)
                    ->value('id');

                $stageId ??= DB::table('deal_stages')
                    ->where('tenant_id', $deal->tenant_id)
                    ->where('slug', 'lead')
                    ->value('id');

                DB::table('deals')
                    ->where('id', $deal->id)
                    ->update([
                        'deal_stage_id' => $stageId,
                        'stage' => $slug,
                        'updated_at' => now(),
                    ]);
            });
    }

    private function legacyStageSlug(string $stage): string
    {
        return match (Str::slug($stage)) {
            'proposal', 'proposta' => 'proposal',
            'negotiation', 'negociacao' => 'negotiation',
            'follow-up', 'followup' => 'follow-up',
            'won', 'ganho' => 'won',
            'lost', 'perdido' => 'lost',
            default => 'lead',
        };
    }
};
