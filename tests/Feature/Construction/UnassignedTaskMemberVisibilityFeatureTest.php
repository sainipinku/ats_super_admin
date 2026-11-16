<?php

namespace Tests\Feature\Construction;

use App\Http\Controllers\Api\MemberDashboardController;
use App\Http\Controllers\Api\Member\TaskController as ApiMemberTaskController;
use App\Http\Controllers\Member\ConstructionController as MemberConstructionController;
use App\Models\ExecutionTask;
use App\Models\Member;
use App\Models\Project;
use App\Models\Task;
use App\Services\Construction\ConstructionAuthorizationService;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class UnassignedTaskMemberVisibilityFeatureTest extends TestCase
{
    public function test_member_dashboard_controller_does_not_contain_or_where_admin_project_leak_in_my_tasks(): void
    {
        $code = file_get_contents(app_path('Http/Controllers/Api/MemberDashboardController.php'));

        // The query should not simply orWhere project_id in adminProjectIds for personal member tasks
        // because that leaks all unassigned project tasks into the member's personal task list.
        $this->assertDoesNotMatchRegularExpression(
            '/myTasks[\s\S]*?\$legacyQuery\s*=\s*ExecutionTask[\s\S]*?\$adminProjectIds\s*!==\s*\[\]\s*\)\s*\{\s*\$q->orWhere\(\s*function\s*\(Builder\s*\$admin\)\s*use\s*\(\$adminProjectIds\)\s*\{\s*\$admin->whereIn\(\'project_id\',\s*\$adminProjectIds\);/',
            $code,
            'myTasks legacyQuery must not leak unassigned project tasks via orWhere adminProjectIds'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/myTasks[\s\S]*?\$unifiedQuery\s*=\s*Task[\s\S]*?\$adminProjectIds\s*!==\s*\[\]\s*\)\s*\{\s*\$q->orWhere\(\s*function\s*\(Builder\s*\$admin\)\s*use\s*\(\$adminProjectIds\)\s*\{\s*\$admin->whereIn\(\'project_id\',\s*\$adminProjectIds\);/',
            $code,
            'myTasks unifiedQuery must not leak unassigned project tasks via orWhere adminProjectIds'
        );
    }

    public function test_member_dashboard_controller_todays_tasks_does_not_leak_unassigned_project_tasks(): void
    {
        $code = file_get_contents(app_path('Http/Controllers/Api/MemberDashboardController.php'));

        $this->assertDoesNotMatchRegularExpression(
            '/todaysTasks[\s\S]*?\$legacyQuery\s*=\s*ExecutionTask[\s\S]*?\$adminProjectIds\s*!==\s*\[\]\s*\)\s*\{\s*\$q->orWhere\(\s*function\s*\(Builder\s*\$admin\)\s*use\s*\(\$adminProjectIds\)\s*\{\s*\$admin->whereIn\(\'project_id\',\s*\$adminProjectIds\);/',
            $code,
            'todaysTasks legacyQuery must not leak unassigned project tasks via orWhere adminProjectIds'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/todaysTasks[\s\S]*?\$unifiedQuery\s*=\s*Task[\s\S]*?\$adminProjectIds\s*!==\s*\[\]\s*\)\s*\{\s*\$q->orWhere\(\s*function\s*\(Builder\s*\$admin\)\s*use\s*\(\$adminProjectIds\)\s*\{\s*\$admin->whereIn\(\'project_id\',\s*\$adminProjectIds\);/',
            $code,
            'todaysTasks unifiedQuery must not leak unassigned project tasks via orWhere adminProjectIds'
        );
    }

    public function test_api_member_task_controller_index_does_not_leak_unassigned_project_tasks(): void
    {
        $code = file_get_contents(app_path('Http/Controllers/Api/Member/TaskController.php'));

        $this->assertDoesNotMatchRegularExpression(
            '/\$adminProjectIds\s*!==\s*\[\]\s*\)\s*\{\s*\$q->orWhere\(\s*function\s*\(Builder\s*\$admin\)\s*use\s*\(\$adminProjectIds\)\s*\{\s*\$admin->whereIn\(\'project_id\',\s*\$adminProjectIds\);/',
            $code,
            'Api/Member/TaskController index must not leak unassigned tasks via orWhere adminProjectIds'
        );
    }

    public function test_member_construction_controller_scope_execution_tasks_for_role_safely_scopes_all_roles(): void
    {
        $refClass = new ReflectionClass(MemberConstructionController::class);
        $method = $refClass->getMethod('scopeExecutionTasksForRole');
        $method->setAccessible(true);

        $controller = app(MemberConstructionController::class);
        $member = new Member(['id' => 123]);

        // Mock query builder for ExecutionTask
        $query = ExecutionTask::query();

        // 1. For role 'driver'
        $scopedDriver = $method->invoke($controller, clone $query, $member, 'driver');
        $sqlDriver = $scopedDriver->toSql();
        $this->assertStringContainsString('construction_execution_task_assignees', $sqlDriver);

        // 2. For role 'vehicle_driver'
        $scopedVehDriver = $method->invoke($controller, clone $query, $member, 'vehicle_driver');
        $sqlVehDriver = $scopedVehDriver->toSql();
        $this->assertStringContainsString('construction_execution_task_assignees', $sqlVehDriver);

        // 3. For role 'survey_man'
        $scopedSurveyMan = $method->invoke($controller, clone $query, $member, 'survey_man');
        $sqlSurveyMan = $scopedSurveyMan->toSql();
        $this->assertStringContainsString('construction_execution_task_assignees', $sqlSurveyMan);

        // 4. For unknown / default role (e.g. 'worker' or 'technician' or 'unassigned')
        $scopedDefault = $method->invoke($controller, clone $query, $member, 'other_role');
        $sqlDefault = $scopedDefault->toSql();
        // It must NOT be a blank query without WHERE conditions; it must require assignment or supervisor
        $this->assertStringContainsString('construction_execution_task_assignees', $sqlDefault);
        $this->assertStringContainsString('supervisor_member_id', $sqlDefault);
    }
}

