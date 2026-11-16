<?php

namespace Tests\Feature\Construction;

use Tests\TestCase;

class MemberRoleBadgeDeduplicationFeatureTest extends TestCase
{
    public function test_project_controller_get_project_members_does_not_append_designation_or_alias_roles(): void
    {
        $controllerPath = app_path('Http/Controllers/SuperAdmin/Construction/ProjectController.php');
        $this->assertFileExists($controllerPath);

        $code = file_get_contents($controllerPath);

        // ProjectController::getProjectMembers must NOT force vehicle_driver to Driver alias
        $this->assertDoesNotMatchRegularExpression(
            "/['\"]vehicle_driver['\"]\s*,\s*['\"]driver['\"]\s*=>\s*['\"]Driver['\"]/",
            $code,
            'ProjectController::getProjectMembers must not map vehicle_driver to Driver'
        );

        // Must NOT append designation parts to badges
        $this->assertStringNotContainsString(
            '$badges[] = $trimmed;',
            $code,
            'ProjectController::getProjectMembers must not append designation strings into role badges'
        );
    }

    public function test_show_component_renders_clean_member_option_labels(): void
    {
        $showPath = resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx');
        $this->assertFileExists($showPath);

        $code = file_get_contents($showPath);

        // In Show.jsx transfer modal, the option should cleanly render {tm.name}{badgeStr}
        $this->assertMatchesRegularExpression(
            '/\{tm\.name\}\s*\{badgeStr\}/',
            $code,
            'Show.jsx must render member name with badgeStr without extra emoji suffixes'
        );
    }
}
