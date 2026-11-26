<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('construction_drawing_approvals')) {
            return;
        }

        Schema::table('construction_drawing_approvals', function (Blueprint $table) {
            if (! Schema::hasColumn('construction_drawing_approvals', 'assigned_to_type')) {
                $table->string('assigned_to_type')->nullable()->after('requested_at');
            }
            if (! Schema::hasColumn('construction_drawing_approvals', 'assigned_to_id')) {
                $table->unsignedBigInteger('assigned_to_id')->nullable()->after('assigned_to_type');
            }
            if (! Schema::hasColumn('construction_drawing_approvals', 'assigned_by_type')) {
                $table->string('assigned_by_type')->nullable()->after('assigned_to_id');
            }
            if (! Schema::hasColumn('construction_drawing_approvals', 'assigned_by_id')) {
                $table->unsignedBigInteger('assigned_by_id')->nullable()->after('assigned_by_type');
            }
            if (! Schema::hasColumn('construction_drawing_approvals', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('assigned_by_id');
            }
        });

        try {
            Schema::table('construction_drawing_approvals', function (Blueprint $table) {
                $table->index(['assigned_to_type', 'assigned_to_id'], 'cda_assigned_to_idx');
            });
        } catch (\Throwable $e) {
            // Index already exists on target schema.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('construction_drawing_approvals')) {
            return;
        }

        try {
            Schema::table('construction_drawing_approvals', function (Blueprint $table) {
                $table->dropIndex('cda_assigned_to_idx');
            });
        } catch (\Throwable $e) {
            // Index does not exist.
        }

        Schema::table('construction_drawing_approvals', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('construction_drawing_approvals', 'assigned_at') ? 'assigned_at' : null,
                Schema::hasColumn('construction_drawing_approvals', 'assigned_by_id') ? 'assigned_by_id' : null,
                Schema::hasColumn('construction_drawing_approvals', 'assigned_by_type') ? 'assigned_by_type' : null,
                Schema::hasColumn('construction_drawing_approvals', 'assigned_to_id') ? 'assigned_to_id' : null,
                Schema::hasColumn('construction_drawing_approvals', 'assigned_to_type') ? 'assigned_to_type' : null,
            ]));

            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
