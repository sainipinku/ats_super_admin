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
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\SuperAdmin;
use App\Models\SurveyVisit;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskChecklistItem;
use App\Services\Construction\SurveyorToDraftsmanAssignmentService;
use Database\Seeders\Construction\FoundationSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SuperAdminPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NoDuplicateTaskOnApiAndDraftsmanFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function bootstrapWorkflow(): array
    {
        $this->seed(FoundationSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(SuperAdminPermissionSeeder::class);

        $company = Company::create([
            'name' => 'Cadmax Infra',
            'status' => 'active',
        ]);

        $admin = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'roles' => json_encode(['super_admin']),
            'name' => 'Panel Admin',
            'email' => 'paneladmin-' . Str::lower(Str::random(5)) . '@example.com',
            'username' => 'paneladmin' . random_int(100, 999),
            'phone' => (string) random_int(7000000000, 9999999999),
            'whatsapp_phone' => (string) random_int(7000000000, 9999999999),
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'status' => 1,
            'is_project_owner' => 1,
        ]);

        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CLI-' . Str::upper(Str::random(5)),
            'name' => 'Test Client',
            'status' => 'active',
        ]);

        $project = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => 'PRJ-' . Str::upper(Str::random(5)),
            'name' => 'Side Map Survey',
            'slug' => 'side-map-' . Str::lower(Str::random(5)),
            'status' => 'active',
        ]);

        $surveyorRole = ConstructionRole::firstOrCreate(
            ['slug' => 'surveyor', 'company_id' => $company->id],
            ['name' => 'Surveyor', 'is_system_role' => true, 'status' => 'active']
        );
        $draftRole = ConstructionRole::firstOrCreate(
            ['slug' => 'draft_person', 'company_id' => $company->id],
            ['name' => 'Draft Person', 'is_system_role' => true, 'status' => 'active']
        );

        $surveyor = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $admin->id,
            'name' => 'Priyanka Surveyor',
            'username' => 'priyanka-' . Str::lower(Str::random(5)),
            'email' => 'priyanka-' . Str::lower(Str::random(5)) . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'password' => Hash::make('password'),
            'slug' => 'priyanka-' . Str::lower(Str::random(5)),
            'status' => 1,
        ]);

        $draftsman = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $admin->id,
            'name' => 'Pinku Draftsman',
            'username' => 'pinku-' . Str::lower(Str::random(5)),
            'email' => 'pinku-' . Str::lower(Str::random(5)) . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'password' => Hash::make('password'),
            'slug' => 'pinku-' . Str::lower(Str::random(5)),
            'status' => 1,
        ]);

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $surveyor->id,
            'role_id' => $surveyorRole->id,
            'status' => 'active',
        ]);

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $draftsman->id,
            'role_id' => $draftRole->id,
            'status' => 'active',
        ]);

        return [$company, $admin, $project, $surveyor, $draftsman];
    }

    public function test_api_surveyor_submit_task_data_does_not_duplicate_task_counts_or_member_tasks(): void
    {
        [, $admin, $project, $surveyor] = $this->bootstrapWorkflow();

        $task = Task::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $project->id,
            'task_code' => null,
            'title' => 'survey site plan',
            'description' => 'for instructions drafting',
            'status' => 'in_progress',
            'priority' => 'medium',
            'assigned_supervisor_member_id' => $surveyor->id,
            'member_id' => $surveyor->id,
            'created_by' => $admin->id,
        ]);

        TaskAssignment::create([
            'uuid' => (string) Str::uuid(),
            'task_id' => $task->id,
            'project_id' => $project->id,
            'assigned_to' => $surveyor->id,
            'assigned_by' => $admin->id,
            'assignment_role' => 'surveyor',
            'status' => 'active',
            'is_primary' => true,
        ]);

        $chk = TaskChecklistItem::create([
            'task_id' => $task->id,
            'project_id' => $project->id,
            'day_number' => 1,
            'item_title' => 'Boundary Points',
            'is_completed' => false,
        ]);

        Sanctum::actingAs($surveyor, ['*']);

        // Surveyor completes / submits the unified task via mobile API twice (idempotent check)
        $this->postJson("/api/tasks/{$task->id}/submit", [
            'work_status' => 'completed',
            'completed_checklist_ids' => [$chk->id],
            'remarks' => 'Survey work done',
        ])->assertOk();

        $this->postJson("/api/tasks/{$task->id}/submit", [
            'work_status' => 'completed',
            'completed_checklist_ids' => [$chk->id],
            'remarks' => 'Survey work resubmitted',
        ])->assertOk();

        // Must create at most 1 mirror ExecutionTask, and sync task_code cleanly back to unified Task
        $this->assertSame(1, ExecutionTask::where('project_id', $project->id)->count());
        $this->assertSame(1, Task::where('project_id', $project->id)->count());

        $unifiedFresh = $task->fresh();
        $legacyMirror = ExecutionTask::where('project_id', $project->id)->firstOrFail();

        $this->assertNotEmpty($unifiedFresh->task_code);
        $this->assertSame($legacyMirror->task_code, $unifiedFresh->task_code);
        $this->assertSame('review', $unifiedFresh->status);
        $this->assertSame('review', $legacyMirror->status);

        // API myTasks and todaysTasks must return ONLY 1 task item (never duplicated)
        $myTasksRes = $this->getJson("/api/member/tasks?project_id={$project->id}")->assertOk();
        $this->assertCount(1, $myTasksRes->json('tasks') ?? $myTasksRes->json('data') ?? []);

        // Web panel SuperAdmin project show workflowSummary must NOT double-count paired task or checklists
        $showRes = $this->actingAs($admin, 'superadmin')
            ->get(route('super.construction.projects.show', $project->id))
            ->assertOk();

        $inertiaProps = $showRes->viewData('page')['props'] ?? [];
        $this->assertSame(1, $inertiaProps['workflowSummary']['task_counts']['total'] ?? null);
        $this->assertSame(1, $inertiaProps['workflowSummary']['checklist_counts']['total'] ?? null);
    }

    public function test_api_submit_task_data_does_not_collide_with_unrelated_task_sharing_numeric_id(): void
    {
        [, $admin, $project, $surveyor] = $this->bootstrapWorkflow();

        $otherProject = Project::create([
            'company_id' => $project->company_id,
            'client_id' => $project->client_id,
            'project_code' => 'PRJ-OTH-' . Str::upper(Str::random(4)),
            'name' => 'Unrelated Other Project',
            'slug' => 'other-' . Str::lower(Str::random(5)),
            'status' => 'active',
        ]);

        // Unrelated ExecutionTask occupies id = 50
        $unrelatedLegacy = new ExecutionTask([
            'project_id' => $otherProject->id,
            'task_code' => 'EXT-00050',
            'title' => 'Unrelated Excavation Task',
            'status' => 'planned',
            'priority' => 'low',
        ]);
        $unrelatedLegacy->id = 50;
        $unrelatedLegacy->save();

        // Unified Task also has id = 50 on our target project
        $targetUnified = new Task([
            'uuid' => (string) Str::uuid(),
            'project_id' => $project->id,
            'task_code' => null,
            'title' => 'Target Survey Task 50',
            'status' => 'in_progress',
            'priority' => 'high',
            'assigned_supervisor_member_id' => $surveyor->id,
            'member_id' => $surveyor->id,
            'created_by' => $admin->id,
        ]);
        $targetUnified->id = 50;
        $targetUnified->save();

        TaskAssignment::create([
            'uuid' => (string) Str::uuid(),
            'task_id' => $targetUnified->id,
            'project_id' => $project->id,
            'assigned_to' => $surveyor->id,
            'assigned_by' => $admin->id,
            'assignment_role' => 'surveyor',
            'status' => 'active',
            'is_primary' => true,
        ]);

        Sanctum::actingAs($surveyor, ['*']);

        $this->postJson("/api/tasks/{$targetUnified->id}/submit", [
            'work_status' => 'completed',
            'remarks' => 'Completed target survey',
        ])->assertOk();

        // Unrelated ExecutionTask #50 must remain completely untouched
        $this->assertSame('planned', $unrelatedLegacy->fresh()->status);
        $this->assertSame('review', $targetUnified->fresh()->status);
        $this->assertSame(1, ExecutionTask::where('project_id', $project->id)->count());
    }

    public function test_manual_assign_to_draftsman_syncs_paired_tasks_and_prevents_duplicates(): void
    {
        [, $admin, $project, $surveyor, $draftsman] = $this->bootstrapWorkflow();

        $unifiedTask = Task::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $project->id,
            'task_code' => null,
            'title' => 'survey site plan',
            'status' => 'in_progress',
            'priority' => 'medium',
            'assigned_supervisor_member_id' => $surveyor->id,
            'member_id' => $surveyor->id,
            'created_by' => $admin->id,
        ]);

        TaskAssignment::create([
            'uuid' => (string) Str::uuid(),
            'task_id' => $unifiedTask->id,
            'project_id' => $project->id,
            'assigned_to' => $surveyor->id,
            'assigned_by' => $admin->id,
            'assignment_role' => 'surveyor',
            'status' => 'active',
            'is_primary' => true,
        ]);

        // Create legacy mirror with a DIFFERENT auto-increment id (reproducing screenshot TSK-00016 vs UNIFIED-16)
        $plan = ExecutionPlan::create([
            'project_id' => $project->id,
            'plan_code' => 'EXP-' . $project->id . '-1',
            'title' => 'Plan',
            'status' => 'active',
        ]);
        ExecutionTask::create([
            'id' => 999, // occupy another id or just insert higher id
            'project_id' => $project->id,
            'execution_plan_id' => $plan->id,
            'task_code' => 'EXT-FILLER',
            'title' => 'Filler Task',
            'status' => 'planned',
            'priority' => 'low',
        ]);

        Sanctum::actingAs($surveyor, ['*']);
        $this->postJson("/api/tasks/{$unifiedTask->id}/submit", [
            'work_status' => 'completed',
            'remarks' => 'Survey submitted',
        ])->assertOk();

        $legacyMirror = ExecutionTask::where('project_id', $project->id)
            ->where('title', 'survey site plan')
            ->firstOrFail();

        // Manually assign to Draft Person using task_source='task'
        $this->actingAs($admin, 'superadmin')
            ->post(route('super.construction.projects.tasks.assign-draftsman', $project->id), [
                'task_source' => 'task',
                'task_id' => $unifiedTask->id,
                'member_id' => $draftsman->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Both Unified Task AND Legacy Mirror must be synced to in_progress & draftsman assignee
        $this->assertSame('in_progress', $unifiedTask->fresh()->status);
        $this->assertSame('in_progress', $legacyMirror->fresh()->status);
        $this->assertDatabaseHas('task_assignments', [
            'task_id' => $unifiedTask->id,
            'assigned_to' => $draftsman->id,
            'assignment_role' => 'draft_person',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('construction_execution_task_assignees', [
            'execution_task_id' => $legacyMirror->id,
            'member_id' => $draftsman->id,
            'assignment_role' => 'draft_person',
            'status' => 'active',
        ]);

        // Only 1 DraftingJob may exist
        $this->assertSame(1, DraftingJob::where('project_id', $project->id)->count());

        // Attempting to assign Draftsman again from the OTHER source ('execution') must be rejected
        try {
            app(SurveyorToDraftsmanAssignmentService::class)->assign(
                $project,
                'execution',
                $legacyMirror->id,
                $draftsman->id,
                null,
                $admin
            );
            $this->fail('Expected duplicate draftsman assignment across counterpart table to be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('member_id', $e->errors());
        }

        $this->assertSame(1, DraftingJob::where('project_id', $project->id)->count());

        // Draftsman API sees only 1 card, not 2
        Sanctum::actingAs($draftsman, ['*']);
        $draftTasks = $this->getJson("/api/member/tasks?project_id={$project->id}")->assertOk();
        $matching = collect($draftTasks->json('tasks') ?? $draftTasks->json('data') ?? [])
            ->where('title', 'survey site plan')
            ->values();
        $this->assertCount(1, $matching);
    }

    public function test_transfer_unassign_and_destroy_keep_paired_tasks_synchronized_even_with_mismatched_ids(): void
    {
        [, $admin, $project, $surveyor, $draftsman] = $this->bootstrapWorkflow();

        $unifiedTask = Task::create([
            'uuid' => (string) Str::uuid(),
            'project_id' => $project->id,
            'task_code' => null,
            'title' => 'survey site plan',
            'description' => 'base instructions',
            'status' => 'review',
            'priority' => 'medium',
            'assigned_supervisor_member_id' => $surveyor->id,
            'member_id' => $surveyor->id,
            'created_by' => $admin->id,
        ]);

        $plan = ExecutionPlan::create([
            'project_id' => $project->id,
            'plan_code' => 'EXP-' . $project->id . '-1',
            'title' => 'Plan',
            'status' => 'active',
        ]);

        // Intentionally give ExecutionTask a completely different primary key than Task
        $legacyMirror = new ExecutionTask([
            'project_id' => $project->id,
            'execution_plan_id' => $plan->id,
            'task_code' => 'TSK-' . str_pad((string) $unifiedTask->id, 5, '0', STR_PAD_LEFT),
            'title' => 'survey site plan',
            'description' => 'base instructions',
            'status' => 'review',
            'priority' => 'medium',
            'supervisor_member_id' => $surveyor->id,
        ]);
        $legacyMirror->id = $unifiedTask->id + 20;
        $legacyMirror->save();

        // Transfer using ONLY the legacy mirror id + note
        $this->actingAs($admin, 'superadmin')
            ->post(route('super.construction.projects.tasks-v2.transfer', [$project->id, $legacyMirror->id]), [
                'member_id' => $draftsman->id,
                'notes' => 'dgfdgfdgfd',
                'due_date' => now()->addDays(2)->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('in_progress', $legacyMirror->fresh()->status);
        $this->assertSame('in_progress', $unifiedTask->fresh()->status);
        $this->assertSame($draftsman->id, (int) $legacyMirror->fresh()->supervisor_member_id);
        $this->assertSame($draftsman->id, (int) $unifiedTask->fresh()->assigned_supervisor_member_id);
        $this->assertStringContainsString('[Transfer Note: dgfdgfdgfd]', (string) $unifiedTask->fresh()->description);

        // Unassign using ONLY the legacy mirror id -> syncs BOTH
        $this->actingAs($admin, 'superadmin')
            ->post(route('super.construction.projects.tasks-v2.unassign', [$project->id, $legacyMirror->id]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull($legacyMirror->fresh()->supervisor_member_id);
        $this->assertNull($unifiedTask->fresh()->assigned_supervisor_member_id);

        // Delete using tasks-v2.destroy -> deletes BOTH unified AND mirror ExecutionTask
        $this->actingAs($admin, 'superadmin')
            ->delete(route('super.construction.projects.tasks-v2.destroy', [$project->id, $unifiedTask->id]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('tasks', ['id' => $unifiedTask->id]);
        $this->assertDatabaseMissing('construction_execution_tasks', ['id' => $legacyMirror->id]);
    }
}
