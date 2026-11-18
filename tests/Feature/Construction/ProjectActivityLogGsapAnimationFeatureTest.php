<?php

namespace Tests\Feature\Construction;

use Tests\TestCase;

class ProjectActivityLogGsapAnimationFeatureTest extends TestCase
{
    public function test_project_controllers_load_activity_log_with_actor_relation(): void
    {
        $superController = file_get_contents(app_path('Http/Controllers/SuperAdmin/Construction/ProjectController.php'));
        $adminController = file_get_contents(app_path('Http/Controllers/Admin/Construction/ProjectController.php'));

        $this->assertStringContainsString("'activityLog'", $superController);
        $this->assertStringContainsString("ConstructionActivityLog::with('actor')", $superController);

        $this->assertStringContainsString("'activityLog'", $adminController);
    }

    public function test_activity_log_component_integrates_gsap_and_enhanced_ui_elements(): void
    {
        $componentPath = resource_path('js/Pages/Construction/Components/ProjectActivityLog.jsx');
        $code = file_get_contents($componentPath);

        // GSAP usage
        $this->assertStringContainsString('gsap', $code);
        $this->assertStringContainsString('activity-log-card', $code, 'Activity cards must have class for GSAP targeting');

        // Blinking indicator
        $this->assertStringContainsString('animate-ping', $code);
        $this->assertStringContainsString('LIVE', $code);

        // Metadata modal / detail inspector
        $this->assertStringContainsString('selectedActivity', $code);

        // View icon button with modal trigger
        $this->assertStringContainsString('Eye', $code);
        $this->assertMatchesRegularExpression(
            '/setSelectedActivity\s*\(\s*(?:item|activity)\s*\)/',
            $code,
            'Clicking view button or card must trigger setSelectedActivity'
        );
    }
}
