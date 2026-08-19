<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_vehicle_assignments', function (Blueprint $table) {
            $table->enum('assignment_type', ['point_to_point', 'material_handling', 'multi_day'])
                ->default('point_to_point')
                ->after('driver_member_id');

            $table->string('from_location')->nullable()->after('assignment_type');
            $table->decimal('from_lat', 10, 7)->nullable()->after('from_location');
            $table->decimal('from_lng', 10, 7)->nullable()->after('from_lat');

            $table->string('to_location')->nullable()->after('from_lng');
            $table->decimal('to_lat', 10, 7)->nullable()->after('to_location');
            $table->decimal('to_lng', 10, 7)->nullable()->after('to_lat');

            $table->json('material_list')->nullable()->after('to_lng');
            $table->boolean('daily_checkpoint_required')->default(false)->after('material_list');
            $table->text('notes')->nullable()->after('daily_checkpoint_required');

            $table->index(['assignment_type', 'status'], 'cva_type_status_idx');
            $table->index(['project_id', 'assigned_from', 'assigned_to'], 'cva_project_date_range_idx');
        });
    }

    public function down(): void
    {
        Schema::table('construction_vehicle_assignments', function (Blueprint $table) {
            $table->dropIndex('cva_type_status_idx');
            $table->dropIndex('cva_project_date_range_idx');

            $table->dropColumn([
                'assignment_type',
                'from_location',
                'from_lat',
                'from_lng',
                'to_location',
                'to_lat',
                'to_lng',
                'material_list',
                'daily_checkpoint_required',
                'notes',
            ]);
        });
    }
};
