<?php

namespace Tests\Unit\Construction;

use App\Models\Member;
use App\Models\Project;
use App\Models\SuperAdmin;
use App\Services\Construction\ConstructionAuthorizationService;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class UnassignedTaskMemberVisibilityUnitTest extends TestCase
{
    private ConstructionAuthorizationService $authz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authz = app(ConstructionAuthorizationService::class);
    }

    /**
     * Helper to mock permissionsFor method on ConstructionAuthorizationService.
     */
    private function createAuthzWithPermissions(array $returnedPermissions): ConstructionAuthorizationService
    {
        $mock = $this->getMockBuilder(ConstructionAuthorizationService::class)
            ->onlyMethods(['permissionsFor'])
            ->getMock();

        $mock->method('permissionsFor')
            ->willReturn($returnedPermissions);

        return $mock;
    }

    public function test_permission_aliases_dictionary_does_not_grant_manage_from_view(): void
    {
        $refClass = new ReflectionClass(ConstructionAuthorizationService::class);
        $method = $refClass->getMethod('hasAnyPermission');
        $method->setAccessible(true);

        // We can inspect the method source or behavior:
        // A member who ONLY has 'execution.view' or 'execution.task.view'
        $authz = $this->createAuthzWithPermissions(['execution.view', 'execution.task.view']);

        $member = new Member(['id' => 999]);

        $this->assertFalse(
            $authz->hasAnyPermission($member, ['execution_task.manage'], 1),
            'A member with only view permissions must NOT be granted execution_task.manage'
        );

        $this->assertFalse(
            $authz->hasAnyPermission($member, ['execution.manage'], 1),
            'A member with only view permissions must NOT be granted execution.manage'
        );

        $this->assertFalse(
            $authz->hasAnyPermission($member, ['task.manage'], 1),
            'A member with only view permissions must NOT be granted task.manage'
        );
    }

    public function test_view_permissions_do_not_grant_fleet_or_equipment_manage(): void
    {
        $authzVehicleView = $this->createAuthzWithPermissions(['vehicle_tracking.view', 'vehicle.view']);
        $member = new Member(['id' => 999]);

        $this->assertFalse(
            $authzVehicleView->hasAnyPermission($member, ['vehicle_tracking.manage'], 1),
            'A member with only vehicle view permissions must NOT be granted vehicle_tracking.manage'
        );
        $this->assertFalse(
            $authzVehicleView->hasAnyPermission($member, ['vehicle.manage'], 1),
            'A member with only vehicle view permissions must NOT be granted vehicle.manage'
        );

        $authzEquipmentView = $this->createAuthzWithPermissions(['equipment_tracking.view', 'equipment.view']);
        $this->assertFalse(
            $authzEquipmentView->hasAnyPermission($member, ['equipment.manage'], 1),
            'A member with only equipment view permissions must NOT be granted equipment.manage'
        );
        $this->assertFalse(
            $authzEquipmentView->hasAnyPermission($member, ['equipment_tracking.manage'], 1),
            'A member with only equipment view permissions must NOT be granted equipment_tracking.manage'
        );
    }

    public function test_view_permissions_do_not_grant_survey_or_drawing_manage(): void
    {
        $authzSurveyView = $this->createAuthzWithPermissions(['survey.view', 'survey_plan.view']);
        $member = new Member(['id' => 999]);

        $this->assertFalse(
            $authzSurveyView->hasAnyPermission($member, ['survey.manage'], 1),
            'A member with only survey view must NOT be granted survey.manage'
        );
        $this->assertFalse(
            $authzSurveyView->hasAnyPermission($member, ['survey_plan.manage'], 1),
            'A member with only survey view must NOT be granted survey_plan.manage'
        );

        $authzDraftView = $this->createAuthzWithPermissions(['drafting.view', 'drawing_approval.view']);
        $this->assertFalse(
            $authzDraftView->hasAnyPermission($member, ['drafting.manage'], 1),
            'A member with only drafting view must NOT be granted drafting.manage'
        );
        $this->assertFalse(
            $authzDraftView->hasAnyPermission($member, ['drawing_approval.manage'], 1),
            'A member with only drawing view must NOT be granted drawing_approval.manage'
        );
    }

    public function test_manage_permission_properly_implies_view(): void
    {
        $authzManage = $this->createAuthzWithPermissions(['execution.manage']);
        $member = new Member(['id' => 999]);

        $this->assertTrue(
            $authzManage->hasAnyPermission($member, ['execution.view'], 1),
            'A manager with execution.manage MUST be allowed execution.view'
        );
    }

    public function test_permission_aliases_array_structure_has_no_view_in_manage_targets(): void
    {
        // Read file contents of ConstructionAuthorizationService to inspect $permissionAliases
        $code = file_get_contents(app_path('Services/Construction/ConstructionAuthorizationService.php'));

        $this->assertDoesNotMatchRegularExpression(
            "/'execution_task\.manage'\s*=>\s*\[[^\]]*'execution\.view'/",
            $code,
            'execution_task.manage aliases must not contain execution.view'
        );
        $this->assertDoesNotMatchRegularExpression(
            "/'execution\.manage'\s*=>\s*\[[^\]]*'execution\.view'/",
            $code,
            'execution.manage aliases must not contain execution.view'
        );
        $this->assertDoesNotMatchRegularExpression(
            "/'vehicle_tracking\.manage'\s*=>\s*\[[^\]]*'vehicle_tracking\.view'/",
            $code,
            'vehicle_tracking.manage aliases must not contain vehicle_tracking.view'
        );
    }
}
