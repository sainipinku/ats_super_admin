<?php

namespace Tests\Feature\Construction;

use Tests\TestCase;

class SurveySectionPaddingAndUiFeatureTest extends TestCase
{
    public function test_survey_reference_section_has_aligned_cards_and_count_badges(): void
    {
        $superCode = file_get_contents(resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx'));

        $this->assertStringContainsString('id="survey-reference-section"', $superCode);
        $this->assertStringContainsString('items-stretch', $superCode);
        $this->assertStringContainsString('title="Survey Plans"', $superCode);
        $this->assertStringContainsString('title="Survey Submission Queue"', $superCode);
        $this->assertStringContainsString('View More Details', $superCode);
    }

    public function test_admin_survey_section_renders_clean_plan_and_submission_cards(): void
    {
        $adminCode = file_get_contents(resource_path('js/Pages/Admin/Construction/Projects/Show.jsx'));

        $this->assertStringContainsString('title="Survey Execution"', $adminCode);
        $this->assertStringContainsString('title="Survey Submission Queue"', $adminCode);
        $this->assertStringContainsString('View More Details', $adminCode);
        $this->assertStringNotContainsString('p-4.5', $adminCode);
    }
}
