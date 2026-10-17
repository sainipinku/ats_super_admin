<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_survey_visits', function (Blueprint $table) {
            if (! Schema::hasColumn('construction_survey_visits', 'member_id')) {
                $table->foreignId('member_id')->nullable()->after('checked_in_by_member_id')
                    ->constrained('members')->nullOnDelete();
            }
            if (! Schema::hasColumn('construction_survey_visits', 'work_status')) {
                $table->string('work_status', 30)->nullable();
            }
            if (! Schema::hasColumn('construction_survey_visits', 'has_obstruction')) {
                $table->boolean('has_obstruction')->default(false);
            }
            if (! Schema::hasColumn('construction_survey_visits', 'obstruction_details')) {
                $table->text('obstruction_details')->nullable();
            }
            if (! Schema::hasColumn('construction_survey_visits', 'pending_work')) {
                $table->text('pending_work')->nullable();
            }
            if (! Schema::hasColumn('construction_survey_visits', 'start_time')) {
                $table->string('start_time', 50)->nullable();
            }
            if (! Schema::hasColumn('construction_survey_visits', 'end_time')) {
                $table->string('end_time', 50)->nullable();
            }
            if (! Schema::hasColumn('construction_survey_visits', 'total_hours')) {
                $table->string('total_hours', 50)->nullable();
            }
            if (! Schema::hasColumn('construction_survey_visits', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('construction_survey_visits', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('construction_survey_visits', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable();
            }
        });

        if (! Schema::hasTable('construction_survey_visit_checklists')) {
            Schema::create('construction_survey_visit_checklists', function (Blueprint $table) {
                $table->id();
                $table->foreignId('survey_visit_id')->constrained('construction_survey_visits')->cascadeOnDelete();
                $table->foreignId('task_id')->constrained('construction_execution_tasks')->cascadeOnDelete();
                $table->foreignId('checklist_id')->constrained('construction_task_checklists')->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
                $table->boolean('is_completed')->default(true);
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->unique(['survey_visit_id', 'checklist_id'], 'survey_visit_checklist_unique');
            });
        }

        if (! Schema::hasTable('construction_survey_visit_files')) {
            Schema::create('construction_survey_visit_files', function (Blueprint $table) {
                $table->id();
                $table->foreignId('survey_visit_id')->constrained('construction_survey_visits')->cascadeOnDelete();
                $table->foreignId('task_id')->constrained('construction_execution_tasks')->cascadeOnDelete();
                $table->string('category', 40);
                $table->string('file_name');
                $table->string('file_path', 500);
                $table->string('file_type', 150)->nullable();
                $table->unsignedBigInteger('file_size');
                $table->foreignId('uploaded_by')->constrained('members')->cascadeOnDelete();
                $table->timestamp('uploaded_at');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_survey_visit_files');
        Schema::dropIfExists('construction_survey_visit_checklists');

        Schema::table('construction_survey_visits', function (Blueprint $table) {
            $columns = [
                'member_id',
                'work_status',
                'has_obstruction',
                'obstruction_details',
                'pending_work',
                'start_time',
                'end_time',
                'total_hours',
                'latitude',
                'longitude',
                'submitted_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('construction_survey_visits', $column)) {
                    if ($column === 'member_id') {
                        $table->dropConstrainedForeignId('member_id');
                    } else {
                        $table->dropColumn($column);
                    }
                }
            }
        });
    }
};
