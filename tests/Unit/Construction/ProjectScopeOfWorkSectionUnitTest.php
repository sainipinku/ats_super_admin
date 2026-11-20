<?php

namespace Tests\Unit\Construction;

use PHPUnit\Framework\TestCase;

class ProjectScopeOfWorkSectionUnitTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 3);
    }

    public function test_superadmin_project_show_renames_section_to_scope_of_work_with_edit_modal(): void
    {
        $code = file_get_contents($this->root . '/resources/js/Pages/SuperAdmin/Construction/Projects/Show.jsx');

        $this->assertStringContainsString('title="Scope of Work"', $code);
        $this->assertStringNotContainsString('title="Survey Plans"', $code);
        $this->assertStringContainsString('project.scope_of_work', $code);
        $this->assertStringContainsString('showScopeModal', $code);
        $this->assertStringContainsString('Edit Scope of Work', $code);
        $this->assertStringContainsString('super.construction.projects.scope-of-work.update', $code);
    }

    public function test_admin_project_show_renames_section_to_scope_of_work_with_edit_modal(): void
    {
        $code = file_get_contents($this->root . '/resources/js/Pages/Admin/Construction/Projects/Show.jsx');

        $this->assertStringContainsString('title="Scope of Work"', $code);
        $this->assertStringNotContainsString('title="Survey Execution"', $code);
        $this->assertStringContainsString('project.scope_of_work', $code);
        $this->assertStringContainsString('showScopeModal', $code);
        $this->assertStringContainsString('Edit Scope of Work', $code);
        $this->assertStringContainsString('admin.construction.projects.scope-of-work.update', $code);
    }

    public function test_project_add_form_includes_scope_of_work_field(): void
    {
        $code = file_get_contents($this->root . '/resources/js/Pages/SuperAdmin/Construction/Projects/Index.jsx');

        $this->assertStringContainsString('name="scope_of_work"', $code);
        $this->assertStringContainsString('label="Scope of Work"', $code);
    }
}
