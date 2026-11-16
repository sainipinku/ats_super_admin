<?php

namespace Tests\Unit\Construction;

use App\Http\Controllers\SuperAdmin\Construction\ProjectTaskController;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class TaskUnassignmentUnitTest extends TestCase
{
    public function test_project_task_controller_defines_unassign_task_method(): void
    {
        $ref = new ReflectionClass(ProjectTaskController::class);
        $this->assertTrue(
            $ref->hasMethod('unassignTask'),
            'ProjectTaskController must define unassignTask method'
        );

        $method = $ref->getMethod('unassignTask');
        $this->assertTrue(
            $method->isPublic(),
            'unassignTask method must be public'
        );
    }

    public function test_unassign_route_is_registered_in_router(): void
    {
        $route = Route::getRoutes()->getByName('super.construction.projects.tasks-v2.unassign')
            ?? Route::getRoutes()->getByName('projects.tasks-v2.unassign');

        $this->assertNotNull(
            $route,
            'Route projects.tasks-v2.unassign must be registered'
        );

        $this->assertContains('POST', $route->methods());
    }
}

