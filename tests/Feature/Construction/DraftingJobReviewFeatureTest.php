<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\DraftingJob;
use App\Models\DrawingRevision;
use App\Models\Member;
use App\Models\Project;
use App\Models\SuperAdmin;
use App\Models\SurveyPlan;
use App\Models\SurveySubmission;
use App\Models\SurveyVisit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class DraftingJobReviewFeatureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_superadmin_can_review_and_approve_drafting_job_without_missing_column_error(): void
    {
        [$company, $superAdmin, $project, $draftingJob] = $this->createProjectAndDraftingJob();

        $response = $this->actingAs($superAdmin, 'superadmin')
            ->from(route('super.construction.projects.show', $project->id))
            ->post(route('super.construction.projects.drafting-jobs.review', [$project->id, $draftingJob->id]), [
                'decision' => 'approved',
                'remarks' => 'Looks good to proceed',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $draftingJob->refresh();
        $this->assertSame('approved', $draftingJob->status);
        $this->assertNull($draftingJob->rejection_reason);
    }

    public function test_superadmin_can_review_and_request_revision_without_missing_column_error(): void
    {
        [$company, $superAdmin, $project, $draftingJob] = $this->createProjectAndDraftingJob();

        $response = $this->actingAs($superAdmin, 'superadmin')
            ->from(route('super.construction.projects.show', $project->id))
            ->post(route('super.construction.projects.drafting-jobs.review', [$project->id, $draftingJob->id]), [
                'decision' => 'revision_requested',
                'remarks' => 'Please adjust north boundary line',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $draftingJob->refresh();
        $this->assertSame('revision_requested', $draftingJob->status);
        $this->assertSame('Please adjust north boundary line', $draftingJob->rejection_reason);
        $this->assertEquals(1, $draftingJob->rejection_count);
        $this->assertNotNull($draftingJob->rejected_at);
    }

    private function createProjectAndDraftingJob(): array
    {
        $company = Company::create([
            'name' => 'Review Co ' . Str::random(5),
            'status' => 'active',
        ]);

        $superAdmin = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'roles' => json_encode(['super_admin']),
            'name' => 'Super Admin Reviewer',
            'email' => 'sa-rev-' . Str::lower(Str::random(6)) . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'whatsapp_phone' => (string) random_int(7000000000, 9999999999),
            'password' => 'password',
        ]);

        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-' . Str::upper(Str::random(6)),
            'name' => 'Review Client',
            'status' => 'active',
        ]);

        $project = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => 'PRJ-' . Str::upper(Str::random(6)),
            'name' => 'Review Project',
            'slug' => 'rev-proj-' . Str::lower(Str::random(6)),
            'status' => 'active',
            'current_stage' => 'drawing_approval_pending',
        ]);

        $uniqueSuffix = Str::lower(Str::random(6));
        $member = Member::create([
            'company_id' => $company->id,
            'created_by' => $superAdmin->id,
            'name' => 'Drafter ' . Str::random(4),
            'username' => 'drafter_' . $uniqueSuffix,
            'slug' => 'drafter-' . $uniqueSuffix,
            'email' => 'drafter-' . $uniqueSuffix . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'status' => 1,
            'password' => bcrypt('password'),
        ]);

        $plan = SurveyPlan::create([
            'project_id' => $project->id,
            'survey_code' => 'SP-' . Str::upper(Str::random(6)),
            'title' => 'Initial Plan',
            'status' => 1,
        ]);

        $visit = SurveyVisit::create([
            'project_id' => $project->id,
            'survey_plan_id' => $plan->id,
            'member_id' => $member->id,
            'checked_in_by_member_id' => $member->id,
            'status' => 1,
        ]);

        $submission = SurveySubmission::create([
            'project_id' => $project->id,
            'survey_visit_id' => $visit->id,
            'submitted_by_member_id' => $member->id,
            'status' => 2,
            'submitted_at' => now(),
        ]);

        $draftingJob = DraftingJob::create([
            'project_id' => $project->id,
            'survey_submission_id' => $submission->id,
            'assigned_to_member_id' => $member->id,
            'status' => 'submitted',
        ]);

        DrawingRevision::create([
            'project_id' => $project->id,
            'drafting_job_id' => $draftingJob->id,
            'revision_no' => 1,
            'status' => 'submitted',
            'uploaded_by_member_id' => $member->id,
            'uploaded_at' => now(),
        ]);

        return [$company, $superAdmin, $project, $draftingJob];
    }
}
