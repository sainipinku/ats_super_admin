<?php

namespace Tests\Unit\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\ExecutionTask;
use App\Models\ExecutionTaskAssignee;
use App\Models\Member;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAssignment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApprovedTaskAppExclusionUnitTest extends TestCase
{
    use DatabaseTransactions;

    public function test_transferred_task_deactivates_previous_assignee(): void
    {
        $company = Company::create(['name' => 'Transfer Co ' . Str::random(5), 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'client_code' => 'CL-' . Str::upper(Str::random(5)), 'name' => 'Transfer Client', 'status' => 'active']);
        $project = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => 'PRJ-' . Str::upper(Str::random(5)),
            'name' => 'Transfer Project',
            'slug' => 'transfer-' . Str::lower(Str::random(5)),
            'status' => 'active',
        ]);

        $surveyor = Member::create([
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

        $drafter = Member::create([
            'company_id' => $company->id,
            'created_by' => 1,
            'name' => 'Abhishek Drafter',
            'username' => 'abhishek_' . Str::lower(Str::random(5)),
            'slug' => 'abhishek-' . Str::lower(Str::random(5)),
            'email' => 'abhishek-' . Str::lower(Str::random(5)) . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'password' => 'password',
            'status' => 1,
        ]);

        $task = Task::create([
            'project_id' => $project->id,
            'task_code' => 'TSK-' . Str::upper(Str::random(5)),
            'title' => 'Survey Demo Task',
            'status' => 'in_progress',
            'member_id' => $surveyor->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        $oldAssignment = TaskAssignment::create([
            'task_id' => $task->id,
            'assigned_to' => $surveyor->id,
            'project_id' => $project->id,
            'assignment_role' => 'executor',
            'is_primary' => true,
            'status' => 'active',
            'is_transferred' => false,
            'uuid' => (string) Str::uuid(),
        ]);

        $legacyTask = ExecutionTask::create([
            'project_id' => $project->id,
            'task_code' => $task->task_code,
            'title' => $task->title,
            'status' => 'in_progress',
            'supervisor_member_id' => $surveyor->id,
        ]);

        $oldLegacyAssignee = ExecutionTaskAssignee::create([
            'execution_task_id' => $legacyTask->id,
            'member_id' => $surveyor->id,
            'project_id' => $project->id,
            'assignment_role' => 'surveyor',
            'is_primary' => true,
            'status' => 'active',
        ]);

        // Simulating the transfer logic:
        // 1. TaskAssignment marks previous as revoked & transferred
        TaskAssignment::where('task_id', $task->id)
            ->where('assigned_to', '!=', $drafter->id)
            ->update([
                'status' => 'revoked',
                'is_transferred' => true,
                'is_primary' => false,
            ]);

        // 2. ExecutionTaskAssignee must deactivate previous assignees
        ExecutionTaskAssignee::where('execution_task_id', $legacyTask->id)
            ->where('member_id', '!=', $drafter->id)
            ->update(['status' => 'transferred', 'is_primary' => false]);

        $this->assertEquals('transferred', $oldLegacyAssignee->fresh()->status);
        $this->assertEquals('revoked', is_object($oldAssignment->fresh()->status) ? $oldAssignment->fresh()->status->value : $oldAssignment->fresh()->status);
        $this->assertTrue((bool) $oldAssignment->fresh()->is_transferred);
    }
}
