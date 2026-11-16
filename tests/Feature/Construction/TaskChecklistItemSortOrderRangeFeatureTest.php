<?php

namespace Tests\Feature\Construction;

use Tests\TestCase;

class TaskChecklistItemSortOrderRangeFeatureTest extends TestCase
{
    public function test_task_management_service_soft_deletes_removed_items_cleanly(): void
    {
        $servicePath = app_path('Services/Construction/TaskManagementService.php');
        $this->assertFileExists($servicePath);

        $code = file_get_contents($servicePath);

        // Verify remove loop simply deletes without updating sort_order to negative
        $this->assertMatchesRegularExpression(
            "/\\\$remove->each\s*\(\s*function\s*\(\s*TaskChecklistItem\s+\\\$item\s*\)[\s\S]*?\\\$item->delete\(\);/i",
            $code,
            'TaskManagementService must soft-delete removed checklist items directly'
        );

        $this->assertStringNotContainsString(
            '$item->sort_order = -1;',
            $code,
            'TaskManagementService must not assign -1 to sort_order'
        );
    }

    public function test_sort_order_normalization_enforces_non_negative_values(): void
    {
        $servicePath = app_path('Services/Construction/TaskManagementService.php');
        $this->assertFileExists($servicePath);

        $code = file_get_contents($servicePath);

        $this->assertMatchesRegularExpression(
            "/normalizeSortOrder\s*\([\s\S]*?return\s+\\\$int\s*>=\s*0\s*\?\s*\\\$int\s*:\s*0;/i",
            $code,
            'TaskManagementService::normalizeSortOrder must ensure non-negative integer'
        );
    }
}
