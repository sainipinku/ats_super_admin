<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class TaskChecklistItemSortOrderRangeUnitTest extends TestCase
{
    public function test_task_management_service_does_not_assign_negative_sort_order_to_checklist_item(): void
    {
        $servicePath = app_path('Services/Construction/TaskManagementService.php');
        $this->assertFileExists($servicePath);

        $code = file_get_contents($servicePath);

        // Must NOT assign negative value to unsigned column sort_order
        $this->assertDoesNotMatchRegularExpression(
            '/->sort_order\s*=\s*-\s*1/i',
            $code,
            'TaskManagementService must not assign negative values like -1 to unsigned column sort_order'
        );
    }
}
