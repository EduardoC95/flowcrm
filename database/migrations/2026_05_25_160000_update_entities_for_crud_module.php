<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (! Schema::hasColumn('entities', 'vat')) {
                $table->string('vat', 50)->nullable()->after('name');
            }

            if (! Schema::hasColumn('entities', 'address')) {
                $table->text('address')->nullable()->after('phone');
            }

            if (! Schema::hasColumn('entities', 'status')) {
                $table->string('status')->default('active')->after('address');
            }

            if (! Schema::hasColumn('entities', 'notes')) {
                $table->text('notes')->nullable()->after('status');
            }

            if (! Schema::hasColumn('entities', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (Schema::hasColumn('entities', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            foreach (['notes', 'status', 'address', 'vat'] as $column) {
                if (Schema::hasColumn('entities', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
