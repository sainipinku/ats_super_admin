<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionRole;
use App\Models\DraftingJob;
use App\Models\ExecutionPlan;
use App\Models\ExecutionTask;
use App\Models\ExecutionTaskAssignee;
use App\Models\Member;
use App\Models\MemberRoleAssignment;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\SuperAdmin;
use App\Services\Construction\SurveyorToDraftsmanAssignmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SurveyorToDraftsmanCompanyListFeatureTest extends TestCase
{
    use DatabaseTransactions;

    private int $creatorId;

    public function test_surveyor_task_in_review_status_can_be_assigned_to_company_employee_draftsman_not_yet_on_project(): void
    {
        [$company, $project, $surveyor, $actor, $task] = $this->createBaseWorkflow('review', 100);

        // Company-wide Draft Person NOT yet added to project_team_members
        $companyDraftsman = $this->createMember('Company Draft Person', $company->name);
        $draftRole = ConstructionRole::firstOrCreate(
            ['slug' => 'draft_person', 'company_id' => $company->id],
            ['name' => 'Draft Person', 'status' => 'active']
        );
        MemberRoleAssignment::create([
            'member_id' => $companyDraftsman->id,
            'role_id' => $draftRole->id,
            'project_id' => null,
            'status' => 1,
        ]);

        // Ensure no auto-created DraftingJob exists before manual assignment
        $this->assertSame(0, DraftingJob::where('project_id', $project->id)->count());

        $service = app(SurveyorToDraftsmanAssignmentService::class);
        $service->assign($project, 'execution', $task->id, $companyDraftsman->id, null, $actor);

        $this->assertSame('in_progress', $task->fresh()->status);

        // Company Draft Person is assigned to task AND enrolled in project_team_members
        $this->assertDatabaseHas('construction_execution_task_assignees', [
            'execution_task_id' => $task->id,
            'member_id' => $companyDraftsman->id,
            'assignment_role' => 'draft_person',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('construction_project_team_members', [
            'project_id' => $project->id,
            'member_id' => $companyDraftsman->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('construction_drafting_jobs', [
            'project_id' => $project->id,
            'assigned_to_member_id' => $companyDraftsman->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_survey_approved_task_can_be_manually_assigned_to_draftsman(): void
    {
        [$company, $project, $surveyor, $actor, $task] = $this->createBaseWorkflow('survey_approved', 100);

        $draftsman = $this->createMember('Approved Stage Draftsman', $company->name);
        $draftRole = ConstructionRole::firstOrCreate(
            ['slug' => 'draft_person', 'company_id' => $company->id],
            ['name' => 'Draft Person', 'status' => 'active']
        );
        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $draftsman->id,
            'role_id' => $draftRole->id,
            'status' => 'active',
        ]);

        $service = app(SurveyorToDraftsmanAssignmentService::class);
        $service->assign($project, 'execution', $task->id, $draftsman->id, null, $actor);

        $this->assertSame('in_progress', $task->fresh()->status);
        $this->assertDatabaseHas('construction_drafting_jobs', [
            'project_id' => $project->id,
            'assigned_to_member_id' => $draftsman->id,
        ]);
    }

    public function test_draftsman_from_different_company_is_rejected(): void
    {
        [$company, $project, $surveyor, $actor, $task] = $this->createBaseWorkflow('completed', 100);

        $otherCompany = Company::create([
            'name' => 'Other Company ' . Str::random(5),
            'status' => 'active',
        ]);
        $otherActor = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $otherCompany->id,
            'roles' => json_encode(['super_admin']),
            'name' => 'Other Super Admin',
            'email' => 'other-sa-' . Str::lower(Str::random(6)) . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'whatsapp_phone' => (string) random_int(7000000000, 9999999999),
            'password' => 'password',
        ]);

        $otherDraftsman = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $otherActor->id,
            'company_name' => $otherCompany->name,
            'name' => 'Other Company Draftsman',
            'username' => 'other-draft-' . Str::lower(Str::random(6)),
            'email' => 'other-draft-' . Str::lower(Str::random(6)) . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'password' => 'password',
            'slug' => 'other-draft-' . Str::lower(Str::random(6)),
            'status' => 1,
        ]);
        $otherRole = ConstructionRole::firstOrCreate(
            ['slug' => 'draft_person', 'company_id' => $otherCompany->id],
            ['name' => 'Draft Person', 'status' => 'active']
        );
        MemberRoleAssignment::create([
            'member_id' => $otherDraftsman->id,
            'role_id' => $otherRole->id,
            'project_id' => null,
            'status' => 1,
        ]);

        $this->expectException(ValidationException::class);

        app(SurveyorToDraftsmanAssignmentService::class)
            ->assign($project, 'execution', $task->id, $otherDraftsman->id, null, $actor);
    }

    private function createBaseWorkflow(string $taskStatus, int $progressPercent): array
    {
        $company = Company::create([
            'name' => 'Draft Workflow Co ' . Str::random(5),
            'status' => 'active',
        ]);
        $actor = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'roles' => json_encode(['super_admin']),
            'name' => 'Company Super Admin',
            'email' => 'sa-' . Str::lower(Str::random(6)) . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'whatsapp_phone' => (string) random_int(7000000000, 9999999999),
            'password' => 'password',
        ]);
        $this->creatorId = $actor->id;

        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-' . Str::upper(Str::random(6)),
            'name' => 'Test Client',
            'status' => 'active',
        ]);
        $project = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => 'PRJ-' . Str::upper(Str::random(6)),
            'name' => 'Survey To Draft Project',
            'slug' => 'survey-draft-' . Str::lower(Str::random(6)),
            'status' => 'active',
        ]);

        $surveyor = $this->createMember('Field Surveyor', $company->name);
        $surveyorRole = ConstructionRole::firstOrCreate(
            ['slug' => 'surveyor', 'company_id' => $company->id],
            ['name' => 'Surveyor', 'status' => 'active']
        );
        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $surveyor->id,
            'role_id' => $surveyorRole->id,
            'status' => 'active',
        ]);

        $plan = ExecutionPlan::create([
            'project_id' => $project->id,
            'plan_code' => 'EXP-' . Str::upper(Str::random(6)),
            'title' => 'Master Plan',
            'status' => 'planned',
        ]);
        $task = ExecutionTask::create([
            'project_id' => $project->id,
            'execution_plan_id' => $plan->id,
            'task_code' => 'EXT-' . Str::upper(Str::random(6)),
            'title' => 'Completed Survey Task',
            'priority' => 'high',
            'supervisor_member_id' => $surveyor->id,
            'progress_percent' => $progressPercent,
            'status' => $taskStatus,
        ]);
        ExecutionTaskAssignee::create([
            'project_id' => $project->id,
            'execution_task_id' => $task->id,
            'member_id' => $surveyor->id,
            'assignment_role' => 'surveyor',
            'assigned_by_type' => $actor::class,
            'assigned_by_id' => $actor->id,
            'status' => 'active',
        ]);

        return [$company, $project, $surveyor, $actor, $task];
    }

    private function createMember(string $name, string $companyName): Member
    {
        $slug = Str::slug($name) . '-' . Str::lower(Str::random(5));

        return Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $this->creatorId,
            'company_name' => $companyName,
            'name' => $name,
            'username' => $slug,
            'email' => $slug . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'password' => 'password',
            'slug' => $slug,
            'status' => 1,
        ]);
    }
}
