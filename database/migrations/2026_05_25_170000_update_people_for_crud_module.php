<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            if (! Schema::hasColumn('people', 'position')) {
                $table->string('position')->nullable()->after('phone');
            }

            if (! Schema::hasColumn('people', 'status')) {
                $table->string('status')->default('active')->after('position');
            }

            if (! Schema::hasColumn('people', 'notes')) {
                $table->text('notes')->nullable()->after('status');
            }

            if (! Schema::hasColumn('people', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if (Schema::hasColumn('people', 'job_title') && Schema::hasColumn('people', 'position')) {
            DB::table('people')
                ->whereNull('position')
                ->whereNotNull('job_title')
                ->update(['position' => DB::raw('job_title')]);
        }

        Schema::table('deals', function (Blueprint $table) {
            if (! Schema::hasColumn('deals', 'person_id')) {
                $table->foreignId('person_id')
                    ->nullable()
                    ->after('entity_id')
                    ->constrained('people')
                    ->nullOnDelete();
            }
        });

        Schema::table('calendar_events', function (Blueprint $table) {
            if (! Schema::hasColumn('calendar_events', 'person_id')) {
                $table->foreignId('person_id')
                    ->nullable()
                    ->after('entity_id')
                    ->constrained('people')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            if (Schema::hasColumn('calendar_events', 'person_id')) {
                $table->dropConstrainedForeignId('person_id');
            }
        });

        Schema::table('deals', function (Blueprint $table) {
            if (Schema::hasColumn('deals', 'person_id')) {
                $table->dropConstrainedForeignId('person_id');
            }
        });

        Schema::table('people', function (Blueprint $table) {
            if (Schema::hasColumn('people', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            foreach (['notes', 'status', 'position'] as $column) {
                if (Schema::hasColumn('people', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
