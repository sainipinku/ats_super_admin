<?php

namespace Tests\Unit\Construction;

use App\Enums\TaskAssignmentRole;
use App\Models\TaskAssignment;
use Tests\TestCase;

class TaskAssignmentRoleEnumStringFixUnitTest extends TestCase
{
    public function test_project_task_controller_does_not_blindly_cast_assignment_role_to_string(): void
    {
        $controllerContent = file_get_contents(base_path('app/Http/Controllers/SuperAdmin/Construction/ProjectTaskController.php'));

        // The broken pattern: (string) ($asg->assignment_role ?? ...
        $this->assertStringNotContainsString(
            '(string) ($asg->assignment_role',
            $controllerContent,
            'ProjectTaskController must not blindly cast $asg->assignment_role to string as it may be an enum instance'
        );
    }

    public function test_task_assignment_role_enum_cannot_be_cast_to_string_directly_and_requires_backed_value(): void
    {
        $enum = TaskAssignmentRole::EXECUTOR;

        // Verify that direct PHP string casting throws Error
        $threw = false;
        try {
            $val = (string) $enum;
        } catch (\Error $e) {
            $threw = true;
            $this->assertStringContainsString('could not be converted to string', $e->getMessage());
        }
        $this->assertTrue($threw, 'Direct (string) $enum cast must throw Error in PHP');

        // Verify that value extraction produces 'executor'
        $this->assertSame('executor', $enum->value);
    }
}
