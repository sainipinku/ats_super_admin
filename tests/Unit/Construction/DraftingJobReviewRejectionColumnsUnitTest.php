<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class DraftingJobReviewRejectionColumnsUnitTest extends TestCase
{
    public function test_migration_exists_to_add_rejection_fields_to_construction_drafting_jobs(): void
    {
        $migrationFiles = glob(database_path('migrations/*add_rejection*drafting_jobs*.php'));
        $this->assertNotEmpty(
            $migrationFiles,
            'A migration must exist to add rejection fields (rejection_reason, rejected_at, etc.) to construction_drafting_jobs table'
        );

        $migrationContent = file_get_contents($migrationFiles[0]);
        $this->assertStringContainsString('construction_drafting_jobs', $migrationContent);
        $this->assertStringContainsString('rejection_reason', $migrationContent);
        $this->assertStringContainsString('rejected_by_member_id', $migrationContent);
        $this->assertStringContainsString('rejected_at', $migrationContent);
        $this->assertStringContainsString('rejection_count', $migrationContent);
    }

    public function test_project_task_controller_guards_rejection_fields_with_column_checks(): void
    {
        $controllerPath = app_path('Http/Controllers/SuperAdmin/Construction/ProjectTaskController.php');
        $this->assertFileExists($controllerPath);
        $content = file_get_contents($controllerPath);

        // Controller should check Schema::hasColumn or dynamically filter job updates before updating draftingJob
        $this->assertMatchesRegularExpression(
            '/Schema::hasColumn\(\s*["\']construction_drafting_jobs["\']\s*,\s*["\']rejection_reason["\']\s*\)/',
            $content,
            'ProjectTaskController must guard rejection_reason updates using Schema::hasColumn or dynamic column listing'
        );
    }
}
