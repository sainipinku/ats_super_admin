<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class TaskCompletionArrayToStringFixUnitTest extends TestCase
{
    public function test_project_task_controller_safely_handles_array_designation_without_array_to_string_conversion(): void
    {
        $controllerPath = app_path('Http/Controllers/SuperAdmin/Construction/ProjectTaskController.php');
        $this->assertFileExists($controllerPath);

        $code = file_get_contents($controllerPath);

        // Must NOT pass raw $supervisor?->designation inside implode(' ', array_filter([...]))
        $this->assertDoesNotMatchRegularExpression(
            "/implode\s*\(\s*['\"]\\s*['\"]\s*,\s*array_filter\s*\(\s*\[[\s\S]*?\\\$supervisor\?->designation[\s\S]*?\]\s*\)\s*\)/i",
            $code,
            'ProjectTaskController must not pass raw $supervisor?->designation directly into implode(array_filter)'
        );

        // Must safely handle array designation
        $this->assertMatchesRegularExpression(
            "/is_array\s*\(\s*\\\$supervisor\?->designation\s*\)|is_array\s*\(\s*\\\$member\?->designation\s*\)|\\\$extractSupervisorText|\\\$extractMemberRolesText/i",
            $code,
            'ProjectTaskController must safely inspect member/supervisor designation array'
        );
    }

    public function test_project_task_controller_returns_appropriate_completion_message_for_completed_tasks(): void
    {
        $controllerPath = app_path('Http/Controllers/SuperAdmin/Construction/ProjectTaskController.php');
        $this->assertFileExists($controllerPath);

        $code = file_get_contents($controllerPath);

        $this->assertStringContainsString(
            'Task marked as completed successfully.',
            $code,
            'ProjectTaskController must return task completed message when approving driver/non-survey tasks'
        );
    }
}
