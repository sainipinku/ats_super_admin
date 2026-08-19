<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_survey_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('construction_projects')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('team_number')
                ->comment('Team number from 1 to 10');

            $table->string('team_name', 100);
            $table->text('description')->nullable();

            $table->foreignId('supervisor_member_id')
                ->nullable()
                ->constrained('members')
                ->nullOnDelete();

            $table->string('status')->default('active');
            $table->nullableMorphs('created_by', 'cst_created_by_idx');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'team_number'], 'cst_project_team_unique');
            $table->index(['project_id', 'status'], 'cst_project_status_idx');
        });

        Schema::create('construction_survey_team_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_assignment_id')
                ->constrained('construction_vehicle_assignments')
                ->cascadeOnDelete();

            $table->foreignId('driver_member_id')
                ->nullable()
                ->constrained('members')
                ->nullOnDelete();

            $table->date('checkpoint_date');
            $table->timestamp('logged_in_at')->nullable();
            $table->timestamp('logged_out_at')->nullable();

            $table->decimal('login_lat', 10, 7)->nullable();
            $table->decimal('login_lng', 10, 7)->nullable();
            $table->decimal('logout_lat', 10, 7)->nullable();
            $table->decimal('logout_lng', 10, 7)->nullable();
            $table->decimal('gps_distance_meters', 10, 2)->nullable();
            $table->boolean('gps_verified')->default(false);

            $table->decimal('odometer_start_km', 12, 2)->nullable();
            $table->decimal('odometer_end_km', 12, 2)->nullable();
            $table->text('checkpoint_notes')->nullable();

            $table->timestamps();

            $table->index(['vehicle_assignment_id', 'checkpoint_date'], 'cstc_assignment_date_idx');
            $table->unique(['vehicle_assignment_id', 'checkpoint_date'], 'cstc_assignment_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_survey_team_checkpoints');
        Schema::dropIfExists('construction_survey_teams');
    }
};
