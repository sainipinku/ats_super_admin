<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class SurveyorToDraftsmanManualAssignmentUnitTest extends TestCase
{
    public function test_assign_to_draftsman_button_supports_review_approved_completed_and_default_project_draftsman(): void
    {
        $path = resource_path('js/Pages/Construction/Components/AssignToDraftsmanButton.jsx');
        $this->assertFileExists($path);

        $code = file_get_contents($path);

        $this->assertStringContainsString('defaultDraftsmanId', $code, 'AssignToDraftsmanButton must accept defaultDraftsmanId');
        $this->assertStringContainsString('survey_approved', $code, 'Button must show for survey_approved surveyor tasks');
        $this->assertStringContainsString('review', $code, 'Button must show for submitted review surveyor tasks');
        $this->assertStringContainsString('task.supervisor', $code, 'Surveyor detection must inspect task.supervisor in addition to assignees');
        $this->assertStringContainsString('is_project_member', $code, 'Modal must distinguish project team draftsmen vs company employee draftsmen');
    }

    public function test_superadmin_project_show_passes_all_company_draftsmen_and_removes_random_job_fallback(): void
    {
        $path = resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx');
        $code = file_get_contents($path);

        $this->assertStringNotContainsString(
            'return matchingJob || jobs[0];',
            $code,
            'Show.jsx must not fall back to a random/first project drafting job'
        );
        $this->assertStringContainsString('defaultDraftsmanId', $code, 'Show.jsx must pass defaultDraftsmanId to AssignToDraftsmanButton');
        $this->assertStringContainsString('allCompanyDraftsmen', $code, 'Show.jsx must build list of all company draftsmen from employee members');
    }

    public function test_admin_project_show_defines_can_assign_draftsman_and_passes_company_draftsmen(): void
    {
        $path = resource_path('js/Pages/Admin/Construction/Projects/Show.jsx');
        $code = file_get_contents($path);

        $this->assertStringContainsString('const canAssignDraftsman', $code, 'Admin Show.jsx must define canAssignDraftsman');
        $this->assertStringContainsString('defaultDraftsmanId', $code, 'Admin Show.jsx must pass defaultDraftsmanId');
    }
}
