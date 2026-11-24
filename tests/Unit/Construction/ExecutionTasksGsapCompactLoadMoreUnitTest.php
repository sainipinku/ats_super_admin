<?php

namespace Tests\Unit\Construction;

use PHPUnit\Framework\TestCase;

class ExecutionTasksGsapCompactLoadMoreUnitTest extends TestCase
{
    private string $superShowPath;
    private string $adminShowPath;

    protected function setUp(): void
    {
        parent::setUp();

        $root = dirname(__DIR__, 3);
        $this->superShowPath = $root . '/resources/js/Pages/SuperAdmin/Construction/Projects/Show.jsx';
        $this->adminShowPath = $root . '/resources/js/Pages/Admin/Construction/Projects/Show.jsx';
    }

    public function test_superadmin_execution_tasks_implements_gsap_text_zoom_out_and_inner_cascade(): void
    {
        $source = file_get_contents($this->superShowPath);

        $this->assertStringContainsString('import gsap from "gsap";', $source);
        $this->assertStringContainsString('et-stage', $source);
        $this->assertStringContainsString('et-header-card', $source);
        $this->assertStringContainsString('et-title-word', $source);
        $this->assertStringContainsString('et-task-card', $source);
        $this->assertStringContainsString('et-task-title', $source);
        $this->assertStringContainsString('et-task-progress-fill', $source);
        $this->assertStringContainsString('IntersectionObserver', $source);
        $this->assertMatchesRegularExpression('/scale:\s*1\.0[3-6]/', $source);
    }

    public function test_superadmin_execution_tasks_implements_hover_pop_and_compact_two_per_row_ten_per_page(): void
    {
        $source = file_get_contents($this->superShowPath);

        $this->assertStringContainsString('const TASKS_PER_PAGE = 10;', $source);
        $this->assertStringContainsString('grid gap-3 md:grid-cols-2', $source);
        $this->assertStringContainsString('visibleTaskCount', $source);
        $this->assertStringContainsString('hasMoreTasks', $source);
        $this->assertStringContainsString('handleLoadMoreTasks', $source);
        $this->assertStringContainsString('handleTaskPopEnter', $source);
        $this->assertStringContainsString('handleTaskPopLeave', $source);
        $this->assertStringContainsString('back.out(', $source);
    }

    public function test_superadmin_execution_tasks_implements_gsap_load_more_loading_animation(): void
    {
        $source = file_get_contents($this->superShowPath);

        $this->assertStringContainsString('isLoadingMoreTasks', $source);
        $this->assertStringContainsString('et-load-more-btn', $source);
        $this->assertStringContainsString('et-load-more-loader', $source);
        $this->assertStringContainsString('et-loader-ring', $source);
        $this->assertStringContainsString('et-loader-dot', $source);
        $this->assertStringContainsString('data-et-index', $source);
        $this->assertStringContainsString('repeat: -1', $source);
        $this->assertStringContainsString('Show Less', $source);
    }

    public function test_admin_execution_tasks_implements_compact_two_per_row_ten_per_page_and_gsap_effects(): void
    {
        $source = file_get_contents($this->adminShowPath);

        $this->assertStringContainsString('const TASKS_PER_PAGE = 10;', $source);
        $this->assertStringContainsString('grid gap-3 md:grid-cols-2', $source);
        $this->assertStringContainsString('et-stage', $source);
        $this->assertStringContainsString('et-title-word', $source);
        $this->assertStringContainsString('et-task-card', $source);
        $this->assertStringContainsString('et-load-more-btn', $source);
        $this->assertStringContainsString('et-load-more-loader', $source);
        $this->assertStringContainsString('et-loader-ring', $source);
        $this->assertStringContainsString('et-loader-dot', $source);
        $this->assertStringContainsString('back.out(', $source);
    }

    public function test_execution_tasks_pages_use_valid_tailwind_v3_padding_only(): void
    {
        $superSource = file_get_contents($this->superShowPath);
        $adminSource = file_get_contents($this->adminShowPath);

        $this->assertDoesNotMatchRegularExpression('/\bp-4\.5\b/', $superSource);
        $this->assertDoesNotMatchRegularExpression('/\bpy-0\.2\b/', $superSource);
        $this->assertDoesNotMatchRegularExpression('/\bp-4\.5\b/', $adminSource);
        $this->assertDoesNotMatchRegularExpression('/\bpy-0\.2\b/', $adminSource);
    }
}
