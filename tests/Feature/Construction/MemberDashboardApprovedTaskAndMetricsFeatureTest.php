<?php

namespace Tests\Feature\Construction;

use App\Models\AttendanceRecord;
use App\Models\Client;
use App\Models\Company;
use App\Models\ExecutionTask;
use App\Models\ExecutionTaskAssignee;
use App\Models\Member;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\SurveySubmission;
use App\Models\SurveyVisit;
use App\Models\Task;
use App\Models\TaskAssignment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberDashboardApprovedTaskAndMetricsFeatureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_approved_task_is_excluded_from_todays_tasks(): void
    {
        [$company, $client, $project, $member] = $this->createProjectAndMember();

        // 1. Task that is approved
        $approvedTask = Task::create([
            'project_id' => $project->id,
            'task_code' => 'TSK-' . Str::upper(Str::random(5)),
            'title' => 'Approved Survey Task',
            'status' => 'approved',
            'client_review_status' => 'approved',
            'member_id' => $member->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'is_completed' => true,
            'progress_percent' => 100,
        ]);

        TaskAssignment::create([
            'task_id' => $approvedTask->id,
            'assigned_to' => $member->id,
            'project_id' => $project->id,
            'assignment_role' => 'executor',
            'is_primary' => true,
            'status' => 'active',
            'is_transferred' => false,
            'uuid' => (string) Str::uuid(),
        ]);

        // 2. Task that is active/pending
        $activeTask = Task::create([
            'project_id' => $project->id,
            'task_code' => 'TSK-' . Str::upper(Str::random(5)),
            'title' => 'Active Survey Task',
            'status' => 'in_progress',
            'member_id' => $member->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'is_completed' => false,
            'progress_percent' => 50,
        ]);

        TaskAssignment::create([
            'task_id' => $activeTask->id,
            'assigned_to' => $member->id,
            'project_id' => $project->id,
            'assignment_role' => 'executor',
            'is_primary' => true,
            'status' => 'active',
            'is_transferred' => false,
            'uuid' => (string) Str::uuid(),
        ]);

        Sanctum::actingAs($member, ['*']);

        $response = $this->getJson('/api/member/dashboard');
        $response->assertStatus(200);

        $todaysTasks = $response->json('todays_tasks');
        $titles = collect($todaysTasks)->pluck('title')->all();

        $this->assertContains('Active Survey Task', $titles);
        $this->assertNotContains('Approved Survey Task', $titles, 'Approved task must NOT be in todays_tasks');
    }

    public function test_transferred_task_is_excluded_from_previous_assignee(): void
    {
        [$company, $client, $project, $member] = $this->createProjectAndMember();

        $drafter = Member::create([
            'company_id' => $company->id,
            'created_by' => 1,
            'name' => 'CAD Draft Person',
            'username' => 'cad_' . Str::lower(Str::random(5)),
            'slug' => 'cad-' . Str::lower(Str::random(5)),
            'email' => 'cad-' . Str::lower(Str::random(5)) . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'password' => 'password',
            'status' => 1,
        ]);

        $task = Task::create([
            'project_id' => $project->id,
            'task_code' => 'TSK-' . Str::upper(Str::random(5)),
            'title' => 'Handed Off Task',
            'status' => 'in_progress',
            'member_id' => $drafter->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        // Old assignment revoked & transferred
        TaskAssignment::create([
            'task_id' => $task->id,
            'assigned_to' => $member->id,
            'project_id' => $project->id,
            'assignment_role' => 'executor',
            'is_primary' => false,
            'status' => 'revoked',
            'is_transferred' => true,
            'uuid' => (string) Str::uuid(),
        ]);

        // New assignment to drafter
        TaskAssignment::create([
            'task_id' => $task->id,
            'assigned_to' => $drafter->id,
            'project_id' => $project->id,
            'assignment_role' => 'draft_person',
            'is_primary' => true,
            'status' => 'active',
            'is_transferred' => false,
            'uuid' => (string) Str::uuid(),
        ]);

        Sanctum::actingAs($member, ['*']);

        $response = $this->getJson('/api/member/dashboard');
        $response->assertStatus(200);

        $todaysTasks = $response->json('todays_tasks');
        $titles = collect($todaysTasks)->pluck('title')->all();

        $this->assertNotContains('Handed Off Task', $titles, 'Transferred task must not be shown to previous assignee');
    }

    public function test_dashboard_delivers_dynamic_field_activity_and_performance(): void
    {
        [$company, $client, $project, $member] = $this->createProjectAndMember();

        $today = now()->setTimezone('Asia/Kolkata')->toDateString();

        // Active attendance record with check-in at 2 hours ago
        AttendanceRecord::create([
            'member_id' => $member->id,
            'company_id' => $company->id,
            'project_id' => $project->id,
            'attendance_date' => $today,
            'check_in_at' => now()->subHours(2),
            'check_in_latitude' => 26.9124,
            'check_in_longitude' => 75.7873,
            'notes' => 'Jaipur Metro Site',
            'attendance_type' => 'present',
            'status' => 'approved',
        ]);

        // Survey visit today with distance
        SurveyVisit::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'checked_in_by_member_id' => $member->id,
            'check_in_at' => now()->subHours(2),
            'distance_covered_m' => 2500, // 2.5 km
            'status' => 2,
        ]);

        Sanctum::actingAs($member, ['*']);

        $response = $this->getJson('/api/member/dashboard');
        $response->assertStatus(200);

        $fieldActivity = $response->json('field_activity');
        $performance = $response->json('performance');

        $this->assertNotNull($fieldActivity);
        $this->assertNotNull($performance);

        // Check distance is calculated from survey visit
        $this->assertStringContainsString('2.5', $fieldActivity['distance']);
        $this->assertEquals('Jaipur Metro Site', $fieldActivity['current_location']);

        // Check performance includes numeric representations
        $this->assertArrayHasKey('attendance_rate', $performance);
        $this->assertArrayHasKey('attendance_percentage', $performance);
        $this->assertArrayHasKey('task_completion_percentage', $performance);
    }

    public function test_dashboard_reports_not_checked_in_when_no_attendance_exists(): void
    {
        [$company, $client, $project, $member] = $this->createProjectAndMember();

        Sanctum::actingAs($member, ['*']);

        $response = $this->getJson('/api/member/dashboard');
        $response->assertStatus(200);

        $fieldActivity = $response->json('field_activity');
        $this->assertNotNull($fieldActivity);
        $this->assertEquals('Not Checked In', $fieldActivity['current_location']);
        $this->assertEquals('0.0 km', $fieldActivity['distance']);
        $this->assertEquals('0h 0m', $fieldActivity['work_hours']);
        $this->assertEquals('Pending', $fieldActivity['site_visit_status']);
    }

    private function createProjectAndMember(): array
    {
        $company = Company::create(['name' => 'Act Co ' . Str::random(5), 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'client_code' => 'CL-' . Str::upper(Str::random(5)), 'name' => 'Act Client', 'status' => 'active']);
        $project = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => 'PRJ-' . Str::upper(Str::random(5)),
            'name' => 'Jaipur Metro Project',
            'slug' => 'metro-' . Str::lower(Str::random(5)),
            'status' => 'active',
            'project_address' => 'Jaipur Metro Site',
        ]);

        $member = Member::create([
            'company_id' => $company->id,
            'created_by' => 1,
            'name' => 'Pinku Surveyor',
            'username' => 'pinku_' . Str::lower(Str::random(5)),
            'slug' => 'pinku-' . Str::lower(Str::random(5)),
            'email' => 'pinku-' . Str::lower(Str::random(5)) . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'password' => 'password',
            'status' => 1,
        ]);

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'is_primary' => true,
            'status' => 'active',
            'assignment_scope' => 'survey',
        ]);

        return [$company, $client, $project, $member];
    }
}
