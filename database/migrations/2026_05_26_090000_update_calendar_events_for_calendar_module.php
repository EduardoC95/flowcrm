<?php

use App\Models\Deal;
use App\Models\Entity;
use App\Models\Person;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            if (! Schema::hasColumn('calendar_events', 'eventable_type')) {
                $table->nullableMorphs('eventable');
            }

            if (! Schema::hasColumn('calendar_events', 'description')) {
                $table->text('description')->nullable()->after('title');
            }

            if (! Schema::hasColumn('calendar_events', 'type')) {
                $table->string('type')->default('task')->after('description');
            }

            if (! Schema::hasColumn('calendar_events', 'start_at')) {
                $table->dateTime('start_at')->nullable()->after('type');
            }

            if (! Schema::hasColumn('calendar_events', 'end_at')) {
                $table->dateTime('end_at')->nullable()->after('start_at');
            }

            if (! Schema::hasColumn('calendar_events', 'owner_id')) {
                $table->foreignId('owner_id')->nullable()->after('location')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('calendar_events', 'priority')) {
                $table->string('priority')->nullable()->after('owner_id');
            }

            if (! Schema::hasColumn('calendar_events', 'status')) {
                $table->string('status')->default('pending')->after('priority');
            }

            if (! Schema::hasColumn('calendar_events', 'reminder_at')) {
                $table->dateTime('reminder_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('calendar_events', 'reminder_sent_at')) {
                $table->dateTime('reminder_sent_at')->nullable()->after('reminder_at');
            }

            if (! Schema::hasColumn('calendar_events', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        DB::table('calendar_events')
            ->whereNull('start_at')
            ->update([
                'start_at' => DB::raw('starts_at'),
                'end_at' => DB::raw('ends_at'),
                'description' => DB::raw('notes'),
            ]);

        DB::table('calendar_events')
            ->whereNull('eventable_type')
            ->whereNotNull('deal_id')
            ->update([
                'eventable_type' => Deal::class,
                'eventable_id' => DB::raw('deal_id'),
            ]);

        DB::table('calendar_events')
            ->whereNull('eventable_type')
            ->whereNotNull('person_id')
            ->update([
                'eventable_type' => Person::class,
                'eventable_id' => DB::raw('person_id'),
            ]);

        DB::table('calendar_events')
            ->whereNull('eventable_type')
            ->whereNotNull('entity_id')
            ->update([
                'eventable_type' => Entity::class,
                'eventable_id' => DB::raw('entity_id'),
            ]);

        Schema::table('calendar_events', function (Blueprint $table) {
            if (Schema::hasColumn('calendar_events', 'start_at')) {
                $table->dateTime('start_at')->nullable(false)->change();
            }
        });

        if (! Schema::hasTable('calendar_event_attendees')) {
            Schema::create('calendar_event_attendees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('calendar_event_id')->constrained()->cascadeOnDelete();
                $table->string('attendee_type');
                $table->unsignedBigInteger('attendee_id');
                $table->timestamps();

                $table->index(['attendee_type', 'attendee_id']);
                $table->unique(['calendar_event_id', 'attendee_type', 'attendee_id'], 'calendar_event_attendee_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_attendees');

        Schema::table('calendar_events', function (Blueprint $table) {
            if (Schema::hasColumn('calendar_events', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            foreach (['reminder_sent_at', 'reminder_at', 'status', 'priority'] as $column) {
                if (Schema::hasColumn('calendar_events', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('calendar_events', 'owner_id')) {
                $table->dropConstrainedForeignId('owner_id');
            }

            foreach (['end_at', 'start_at', 'type', 'description'] as $column) {
                if (Schema::hasColumn('calendar_events', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('calendar_events', 'eventable_type')) {
                $table->dropMorphs('eventable');
            }
        });
    }
};
