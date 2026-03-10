<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Department;
use App\Models\Member;
use App\Models\Task;
use App\Models\TaskInstance;
use App\Models\TaskActivityLog;
use App\Models\TaskAssignment;
use App\Models\Designation;
use App\Models\TaskComment;
use App\Models\WhatsappLog;
use App\Services\InteraktServices;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Carbon\Carbon;

use function App\createMessagePayload;

class AdminMemberController extends Controller
{

    public function dashboard(Request $request)
    {
        $authUser = Auth::guard('admin')->user();
        $requiredDepartments = $authUser->departments ?? [];
        $query = Member::where('id', '!=', $authUser->id)
            ->where(function ($query) use ($requiredDepartments) {
                foreach ($requiredDepartments as $deptId) {
                    $query->orWhereJsonContains('departments', (string)$deptId);
                }
            })->whereJsonDoesntContain('roles', '2');
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($createdBy = $request->input('created_by')) {
            $query->where('created_by', $createdBy);
        }
        $perPage = $request->input('per_page', 10);
        $members = $query->paginate($perPage)->withQueryString();
        $members->getCollection()->transform(function ($member) {
            $member->departments_data = Department::whereIn('id', $member->departments ?? [])->get();
            $member->designations_data = Designation::whereIn('id', $member->designation ?? [])->get();
            return $member;
        });
        return Inertia::render('Admin/Member/List', [
            'filters' => $request->only(['search', 'status', 'created_by', 'per_page']),
            'members' => $members,
        ]);
    }

    public function updateStatus(Member $member, Request $request)
    {
        $request->validate([
            'status' => 'required|boolean',
        ]);
        try {
            $member->update(['status' => $request->status]);

            $phoneNumber = $member->phone;
            if ($phoneNumber) {
                $templateName = $member->status == 1 ? 'member_account_reactivated_message' : 'member_account_deactivated_message';
                $languageCode = "en";
                $bodyParameters = [
                    $member->name ?? '--'
                ];

                if ($member->status == 0) {
                    $payload = createMessagePayload($phoneNumber, $templateName, $languageCode, null, $bodyParameters);
                } else {
                    $buttonParameters = ["1" => ["/member/login"]];
                    $payload = createMessagePayload($phoneNumber, $templateName, $languageCode, null, $bodyParameters, $buttonParameters);
                }

                $int = new InteraktServices();
                $resp = $int->sendMessage($payload);

                if ($resp['status'] == true) {
                    $status = 'success';
                } else {
                    $status = 'failed';
                }
                WhatsappLog::create([
                    'member_id' => $member->id,
                    'phone' => $phoneNumber,
                    'error' => $resp,
                    'error_message' => $resp['result']['message'],
                    'status' => $status
                ]);
            }
            
            return redirect()->back()->with('success', 'Member account status updated successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update member status!');
        }
    }

    public function memberDetails(Request $request, $uuid)
    {
        $member = Member::where('uuid', $uuid)->firstOrFail();
        $tasks = $this->getMemberTasks($member, $request);
        $taskStats = $this->getTaskStats($member);
        $taskInstanceStats = $this->getTaskInstanceStats($member);
        $this->formatInstanceDates($tasks);
        return Inertia::render('Admin/Member/MemberDetails', [
            'member' => $member,
            'tasks' => array_merge($tasks->toArray(), $taskStats),
            'task_instances' => $taskInstanceStats,
            'filters' => $request->only(['search', 'status', 'per_page']),
        ]);
    }
    protected function getMemberTasks(Member $member, Request $request)
    {
        return Task::with([
            'creator',
            'assignedMembers' => function ($query) use ($member) {
                $query->withPivot(['uuid as task_assignment_uuid', 'assigned_by', 'start_date', 'end_date']);
            },
            'instances' => function ($query) use ($member) {
                $query->where('assigned_to', $member->id)
                    ->select(['uuid', 'task_id', 'due_date', 'status', 'completed_at']);
            }
        ])
            ->whereHas('assignedMembers', fn($q) => $q->where('assigned_to', $member->id))
            ->when($request->search, fn($q, $search) => $q->where('title', 'like', "%{$search}%"))
            ->when($request->status !== null, fn($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate($request->per_page ?? 10)
            ->withQueryString();
    }
    protected function getTaskStats(Member $member): array
    {
        return [
            'total' => Task::whereHas('assignedMembers', fn($q) => $q->where('assigned_to', $member->id))->count(),
            'closed' => Task::whereHas('assignedMembers', fn($q) => $q->where('assigned_to', $member->id))
                ->where('status', 'closed')->count(),
            'running' => Task::whereHas('assignedMembers', fn($q) => $q->where('assigned_to', $member->id))
                ->where('status', 'running')->count(),
            'pending' => Task::whereHas('assignedMembers', fn($q) => $q->where('assigned_to', $member->id))
                ->where('status', 'pending')->count(),
            'completed' => Task::whereHas('assignedMembers', fn($q) => $q->where('assigned_to', $member->id))
                ->where('status', 'completed')->count(),
        ];
    }
    protected function getTaskInstanceStats(Member $member): array
    {
        $baseQuery = TaskInstance::where('assigned_to', $member->id);
        return [
            'total_instances' => $baseQuery->count(),
            'pending_instances' => $baseQuery->clone()->where('status', 'pending')->count(),
            'in_progress_instances' => $baseQuery->clone()->where('status', 'in_progress')->count(),
            'completed_instances' => $baseQuery->clone()->where('status', 'completed')->count(),
            'overdue_instances' => $baseQuery->clone()
                ->where('due_date', '<', now())
                ->where('status', '!=', 'completed')
                ->count(),
        ];
    }
    protected function formatInstanceDates($tasks)
    {
        $tasks->getCollection()->transform(function ($task) {
            if ($task->instances) {
                $task->instances->transform(function ($instance) {
                    $instance->due_date = $instance->due_date
                        ? Carbon::parse($instance->due_date)->format('Y-m-d H:i')
                        : null;
                    $instance->completed_at = $instance->completed_at
                        ? Carbon::parse($instance->completed_at)->format('Y-m-d H:i')
                        : null;
                    return $instance;
                });
            }
            return $task;
        });
    }
}
