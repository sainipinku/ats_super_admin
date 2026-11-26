<?php

namespace Tests\Unit\Construction;

use App\Models\ExecutionTask;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\TaskChecklistItem;
use App\Services\Construction\TaskMirrorSyncService;
use Illuminate\Support\Collection;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TaskMirrorDeduplicationAndSyncUnitTest extends TestCase
{
    public function test_are_counterparts_matches_task_code_or_fallback_tsk_code_or_same_project_title(): void
    {
        $sync = app(TaskMirrorSyncService::class);

        $legacyByCode = new ExecutionTask([
            'project_id' => 10,
            'task_code' => 'TSK-00016',
            'title' => 'survey site plan',
        ]);
        $legacyByCode->id = 24;

        $unifiedNullCode = new Task([
            'project_id' => 10,
            'task_code' => null,
            'title' => 'Survey Site Plan',
        ]);
        $unifiedNullCode->id = 16;

        $this->assertTrue($sync->areCounterparts($legacyByCode, $unifiedNullCode));

        // Even if title was edited on one side, TSK-00016 still matches Task #16 in the same project
        $legacyRenamed = new ExecutionTask([
            'project_id' => 10,
            'task_code' => 'TSK-00016',
            'title' => 'survey site plan edited',
        ]);
        $legacyRenamed->id = 24;

        $this->assertTrue($sync->areCounterparts($legacyRenamed, $unifiedNullCode));

        // Unrelated tasks with DIFFERENT titles & codes must NOT match even if their auto-increment IDs collide
        $unrelatedLegacy = new ExecutionTask([
            'project_id' => 10,
            'task_code' => 'EXT-00099',
            'title' => 'Other Unrelated Task',
        ]);
        $unrelatedLegacy->id = 16;

        $this->assertFalse($sync->areCounterparts($unrelatedLegacy, $unifiedNullCode));

        // Tasks from DIFFERENT projects must NEVER match
        $otherProjectLegacy = new ExecutionTask([
            'project_id' => 99,
            'task_code' => 'TSK-00016',
            'title' => 'survey site plan',
        ]);
        $otherProjectLegacy->id = 16;

        $this->assertFalse($sync->areCounterparts($otherProjectLegacy, $unifiedNullCode));
    }

    public function test_compute_project_task_and_checklist_counts_deduplicates_paired_tasks(): void
    {
        $sync = app(TaskMirrorSyncService::class);

        $legacyMirror = new ExecutionTask([
            'project_id' => 10,
            'task_code' => 'TSK-00016',
            'title' => 'survey site plan',
            'status' => 'review',
        ]);
        $legacyMirror->id = 24;
        $legacyMirror->setRelation('checklists', new Collection([
            new TaskChecklist(['item_title' => 'Boundary', 'day_number' => 1, 'is_completed' => true]),
            new TaskChecklist(['item_title' => 'Levels', 'day_number' => 1, 'is_completed' => true]),
        ]));

        $unifiedPaired = new Task([
            'project_id' => 10,
            'task_code' => null,
            'title' => 'survey site plan',
            'status' => 'review',
        ]);
        $unifiedPaired->id = 16;
        $unifiedPaired->setRelation('checklistItems', new Collection([
            new TaskChecklistItem(['item_title' => 'Boundary', 'day_number' => 1, 'is_completed' => true]),
            new TaskChecklistItem(['item_title' => 'Levels', 'day_number' => 1, 'is_completed' => true]),
        ]));

        $unifiedSecond = new Task([
            'project_id' => 10,
            'task_code' => null,
            'title' => 'today tast title 26112026',
            'status' => 'in_progress',
        ]);
        $unifiedSecond->id = 17;
        $unifiedSecond->setRelation('checklistItems', new Collection());

        $summary = $sync->computeProjectSummaryCounts(
            new Collection([$legacyMirror]),
            new Collection([$unifiedPaired, $unifiedSecond]),
            new Collection()
        );

        $this->assertSame(2, $summary['task_counts']['total']);
        $this->assertSame(1, $summary['task_counts']['in_progress']);
        $this->assertSame(1, $summary['task_counts']['pending']);
        $this->assertSame(2, $summary['checklist_counts']['total']);
        $this->assertSame(2, $summary['checklist_counts']['completed']);
    }

    public function test_frontend_shared_task_merge_helper_collapses_duplicate_cards(): void
    {
        $helperPath = resource_path('js/lib/taskMerge.js');
        $this->assertFileExists($helperPath, 'Shared resources/js/lib/taskMerge.js helper must exist');

        $script = <<<'JS'
import { mergeProjectExecutionTasks } from './resources/js/lib/taskMerge.js';

const project = {
  id: 10,
  execution_tasks: [
    {
      id: 24,
      project_id: 10,
      task_code: 'TSK-00016',
      status: 'in_progress',
      priority: 'medium',
      title: 'survey site plan',
      description: 'for instructions drating scoo\n\n[Transfer Note: ffsdfdfsfsd]\n\n[Transfer Note: dgfdgfdgfd]',
      supervisor_member_id: 50,
      supervisor: { id: 50, name: 'Pinku' },
      assignees: [{ id: 1, member_id: 50, assignment_role: 'draft_person', status: 'active', member: { id: 50, name: 'Pinku' } }],
      checklists: [
        { id: 101, item_title: 'Site Boundary', day_number: 1, is_completed: true },
        { id: 102, item_title: 'Topography', day_number: 1, is_completed: true },
      ],
      survey_visits: [{ id: 90, execution_task_id: 24, task_id: 16 }],
      updated_at: '2026-11-26T12:00:00Z',
    },
  ],
  tasks: [
    {
      id: 16,
      project_id: 10,
      task_code: null,
      status: 'review',
      priority: 'medium',
      title: 'survey site plan',
      description: 'for instructions drating scoo\n\n[Transfer Note: ffsdfdfsfsd]',
      assigned_supervisor_member_id: 41,
      supervisor: { id: 41, name: 'priynaka saini' },
      assignments: [{ id: 2, assigned_to: 41, assignment_role: 'surveyor', status: 'active', assignee: { id: 41, name: 'priynaka saini' } }],
      checklist_items: [
        { id: 201, item_title: 'Site Boundary', day_number: 1, is_completed: true },
        { id: 202, item_title: 'Topography', day_number: 1, is_completed: true },
      ],
      survey_visits: [{ id: 90, execution_task_id: 24, task_id: 16 }],
      updated_at: '2026-11-26T10:00:00Z',
    },
    {
      id: 17,
      project_id: 10,
      task_code: null,
      status: 'in_progress',
      priority: 'high',
      title: 'today tast title 26112026',
      description: 'for anil yadav surveyor team',
      assigned_supervisor_member_id: 42,
      supervisor: { id: 42, name: 'Anil Yadav' },
      assignments: [{ id: 3, assigned_to: 42, assignment_role: 'surveyor', status: 'active', assignee: { id: 42, name: 'Anil Yadav' } }],
      checklist_items: [],
      survey_visits: [],
      updated_at: '2026-11-26T11:00:00Z',
    },
  ],
};

const result = mergeProjectExecutionTasks(project);
if (result.length !== 2) {
  console.error('Expected 2 merged tasks, got ' + result.length + ': ' + JSON.stringify(result));
  process.exit(1);
}
const survey = result.find((t) => t.title === 'survey site plan');
if (!survey || survey.task_code !== 'TSK-00016' || survey.legacy_task_id !== 24 || survey.unified_task_id !== 16) {
  console.error('Paired task metadata mismatch: ' + JSON.stringify(survey));
  process.exit(1);
}
if (survey.status !== 'in_progress' || survey.checklists.length !== 2 || survey.survey_visits.length !== 1) {
  console.error('Expected freshest status in_progress, 2 checklists, 1 visit: ' + JSON.stringify(survey));
  process.exit(1);
}
console.log('OK');
JS;

        $process = new Process(['node', '--input-type=module', '-e', $script], base_path());
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput() . "\n" . $process->getOutput());
        $this->assertSame('OK', trim($process->getOutput()));
    }
}
