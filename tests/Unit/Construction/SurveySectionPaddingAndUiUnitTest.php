<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class SurveySectionPaddingAndUiUnitTest extends TestCase
{
    public function test_superadmin_project_show_has_no_invalid_tailwind_v3_padding_classes(): void
    {
        $path = resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx');
        $this->assertFileExists($path);

        $code = file_get_contents($path);

        $this->assertStringNotContainsString('p-4.5', $code, 'Tailwind v3 does not support p-4.5 (renders as 0px padding)');
        $this->assertStringNotContainsString('py-0.2', $code, 'Tailwind v3 does not support py-0.2 (renders as 0px padding)');
    }

    public function test_admin_project_show_has_no_invalid_tailwind_v3_padding_classes(): void
    {
        $path = resource_path('js/Pages/Admin/Construction/Projects/Show.jsx');
        $this->assertFileExists($path);

        $code = file_get_contents($path);

        $this->assertStringNotContainsString('p-4.5', $code, 'Tailwind v3 does not support p-4.5 (renders as 0px padding)');
        $this->assertStringNotContainsString('py-0.2', $code, 'Tailwind v3 does not support py-0.2 (renders as 0px padding)');
    }

    public function test_survey_plan_card_uses_valid_padding_and_clean_layout_spacing(): void
    {
        $superCode = file_get_contents(resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx'));
        $adminCode = file_get_contents(resource_path('js/Pages/Admin/Construction/Projects/Show.jsx'));

        foreach ([$superCode, $adminCode] as $code) {
            $pos = strpos($code, 'function SurveyPlanCard');
            $this->assertNotFalse($pos, 'SurveyPlanCard function must exist');

            $slice = substr($code, $pos, 6500);
            $this->assertStringContainsString('p-5', $slice, 'SurveyPlanCard must apply valid p-5 padding');
            $this->assertStringContainsString('pt-3.5', $slice, 'SurveyPlanCard footer must have balanced top padding');
            $this->assertStringContainsString('px-4 py-2', $slice, 'SurveyPlanCard View More Details button must have proper padding');
        }
    }
}
