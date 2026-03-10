<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Department;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\Member;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use App\Models\Role;
use App\Models\TaskInstance;
use App\Models\Task;
use App\Models\FcmToken;
use App\Models\ActivityLog;
use App\Models\TaskAssignment;
use App\Models\SuperAdminPasswordLog;
use App\Services\FirebaseService;
use Carbon\Carbon;
use App\Models\CheckInOut;

class AdminController extends Controller
{
    public function dashboard(Request $request)
    {
        $year = $request->input('year', date('Y'));
        $month = $request->input('month', date('n'));
        $memberId = $request->input('member_id', null);
        $perPagePasswordLog = $request->input('perPagePasswordLog', 10);
        if (Auth::guard('admin')->check()) {
            $auth = Auth::guard('admin')->user();
            $adminDepartmentIds = is_array($auth->departments)
                ? $auth->departments
                : json_decode($auth->departments, true) ?? [];
            $activeMembers = Member::where('status', 1)
                ->where(function ($query) use ($adminDepartmentIds) {
                    foreach ($adminDepartmentIds as $deptId) {
                        $query->orWhereJsonContains('departments', (int)$deptId)
                            ->orWhereJsonContains('departments', (string)$deptId);
                    }
                })
                ->get();
                $memberCheckIns = [];
if ($activeMembers->count() > 0) {
    $memberIds = $activeMembers->pluck('id');
    $checkInOutData = CheckInOut::whereIn('member_id', $memberIds)
        ->whereYear('date', $year)
        ->whereMonth('date', $month)
        ->with('member')
        ->get()
        ->groupBy('date');

    $memberCheckIns = $checkInOutData->toArray();
}

           $totalTasksQuery = TaskAssignment::where('is_transferred', 0);

    if ($memberId) {
        $totalTasksQuery->where('assigned_to', $memberId);
    } else {
        $totalTasksQuery->whereIn('assigned_to', $activeMembers->pluck('id'));
    }

    $totalTasks = $totalTasksQuery->distinct('task_id')->count('task_id');
            $taskInstanceQuery = TaskInstance::whereYear('created_at', $year)
                ->whereMonth('created_at', $month);
            if ($memberId) {
                $taskInstanceQuery->where('assigned_to', $memberId);
            }
            $totalTasksInstance = (clone $taskInstanceQuery)->count();
            $pendingTasks = (clone $taskInstanceQuery)->where('status', 'pending')->count();
            $completedTasks = (clone $taskInstanceQuery)->where('status', 'completed')->count();
            $inProgressTasks = (clone $taskInstanceQuery)->where('status', 'in_progress')->count();
            $overdueTasks = (clone $taskInstanceQuery)
                ->where('status', '!=', 'completed')
                ->whereDate('due_date', '<', now())
                ->count();
            $tasksByDepartment = Department::whereIn('id', $adminDepartmentIds)
                ->get()
                ->map(function ($dept) use ($year, $month, $memberId) {
                    $memberIds = Member::where(function ($query) use ($dept) {
                        $query->whereJsonContains('departments', (int)$dept->id)
                            ->orWhereJsonContains('departments', (string)$dept->id);
                    })->pluck('id')->unique()->values()->toArray();

                    $taskQuery = TaskInstance::whereIn('assigned_to', $memberIds)
                        ->whereYear('created_at', $year)
                        ->whereMonth('created_at', $month);

                    if ($memberId) {
                        $taskQuery->where('assigned_to', $memberId);
                    }

                    $taskCount = $taskQuery->count();

                    return [
                        'name' => $dept->name,
                        'value' => $taskCount,
                        'member_count' => count($memberIds),
                    ];
                });
            $recentTaskQuery = TaskInstance::with(['task', 'assignee'])
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month);
            if ($memberId) {
                $recentTaskQuery->where('assigned_to', $memberId);
            }
            $recentTasks = $recentTaskQuery
                ->orderBy('due_date', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->task->title ?? 'No title',
                        'assigned_to_name' => $task->assignee->name ?? 'Unassigned',
                        'due_date' => $task->due_date,
                        'status' => $task->status,
                    ];
                });
            $activityLogQuery = ActivityLog::with('user')
                ->orderBy('action_time', 'desc');
            if ($memberId) {
                $activityLogQuery->where('user_id', $memberId);
            }
            $activityLogs = $activityLogQuery->limit(10)->get();
            $activeMemberEmails = $activeMembers->pluck('email')->toArray();
            $passwordLogQuery = SuperAdminPasswordLog::with(['superAdmin', 'member'])
                ->orderBy('created_at', 'desc');
            $passwordLogQuery->where(function ($query) use ($activeMemberEmails) {
                $query->whereHas('superAdmin', function ($q) use ($activeMemberEmails) {
                    $q->whereIn('email', $activeMemberEmails);
                })
                    ->orWhereHas('member', function ($q) use ($activeMemberEmails) {
                        $q->whereIn('email', $activeMemberEmails);
                    });
            });
            $checkCheckoutToday = CheckInOut::where('member_id', $auth->id)->where('date', Carbon::today()->toDateString())->first();
             $checkCheckoutList = CheckInOut::where('member_id',$auth->id)->get();
            $superAdminPasswordLog = $passwordLogQuery
                ->paginate($perPagePasswordLog, ['*'], 'pagePasswordLog')
                ->appends($request->except('pagePasswordLog'));
            return Inertia::render('Admin/Dashboard', [
                'auth' => [
                    'guard' => 'admin',
                    'user' => $auth
                ],
                'checkCheckoutList' => $checkCheckoutList,
                'checkCheckoutToday' => $checkCheckoutToday,
                    'memberCheckIns' => $memberCheckIns,
                'stats' => [
                    'totalTasks' => $totalTasks,
                    'totalTasksInstance' => $totalTasksInstance,
                    'pendingTasks' => $pendingTasks,
                    'completedTasks' => $completedTasks,
                    'inProgressTasks' => $inProgressTasks,
                    'overdueTasks' => $overdueTasks,
                    'tasksByDepartment' => $tasksByDepartment,
                ],
                'recentTasks' => $recentTasks,
                'activityLogs' => $activityLogs,
                'members' => $activeMembers,
                'passwordLogs' => $superAdminPasswordLog,
                'initialFilters' => [
                    'year' => $year,
                    'month' => $month,
                    'member_id' => $memberId,
                ],
            ]);
        } else {
            $auth = Auth::guard('member')->user();
            $checkCheckoutToday = CheckInOut::where('member_id', $auth->id)->where('date', Carbon::today()->toDateString())->first();
            $totalAssignedTasks = TaskAssignment::where('assigned_to', $auth->id)
                ->where('is_transferred', 0)
                ->count();
            $taskInstanceQuery = TaskInstance::where('assigned_to', $auth->id)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month);
            $totalTasksInstance = (clone $taskInstanceQuery)->count();
            $pendingTasks = (clone $taskInstanceQuery)->where('status', 'pending')->count();
            $completedTasks = (clone $taskInstanceQuery)->where('status', 'completed')->count();
            $inProgressTasks = (clone $taskInstanceQuery)->where('status', 'in_progress')->count();
            $overdueTasks = (clone $taskInstanceQuery)
                ->where('status', '!=', 'completed')
                ->whereDate('due_date', '<', now())
                ->count();
            $recentTasks = (clone $taskInstanceQuery)
                ->with('task')
                ->orderBy('due_date', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->task->title ?? 'No title',
                        'due_date' => $task->due_date,
                        'status' => $task->status,
                    ];
                });
            $activityLogs = ActivityLog::with('user')
                ->where('user_id', $auth->id)
                ->orderBy('action_time', 'desc')
                ->limit(10)
                ->get();
            $checkCheckoutList = CheckInOut::where('member_id', $auth->id)->get();
            return Inertia::render('Member/Dashboard', [
                'auth' => [
                    'guard' => 'member',
                    'user' => $auth
                ],
                'checkCheckoutToday' => $checkCheckoutToday,
                'checkCheckoutList'  => $checkCheckoutList,
                'stats' => [
                    'totalTasks' => $totalAssignedTasks,
                    'totalTasksInstance' => $totalTasksInstance,
                    'pendingTasks' => $pendingTasks,
                    'completedTasks' => $completedTasks,
                    'inProgressTasks' => $inProgressTasks,
                    'overdueTasks' => $overdueTasks,
                ],
                'recentTasks' => $recentTasks,
                'activityLogs' => $activityLogs,
                'initialFilters' => [
                    'year' => $year,
                    'month' => $month,
                ],
            ]);
        }
    }
    public function logout(Request $request)
    {
        if (Auth::guard('admin')->check()) {
            Auth::guard('admin')->logout();
            return redirect(route('admin.login'))->with('success', 'Logout Succesfull');
        } else {
            Auth::guard('member')->logout();
            return redirect(route('doer.login'))->with('success', 'Logout Succesfull');
        }
        // $request->session()->invalidate();
        // $request->session()->regenerateToken();
    }

    public function userProfile(Request $request)
    {
        return Inertia::render('Admin/UserProfile');
    }

    public function userProfileUpdate(Request $request)
    {
        $user = Auth::guard('admin')->user();
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:super_admins,username,' . $user->id,
            'email' => 'required|email|unique:super_admins,email,' . $user->id,
        ]);
        $user->update([
            'name' => $validatedData['name'],
            'username' => $validatedData['username'],
            'email' => $validatedData['email'],
        ]);
        return redirect()->back()->with('success', 'Profile updated successfully!');
    }

    public function userProfilePhotoUpdate(Request $request, FirebaseService $firebaseService)
    {
        $request->validate([
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ]);
        $auth = Auth::guard('admin')->user();
        $now = now();
        $profilePhoto = $request->file('profile_photo');
        $filename = $now->format('Y_m_d_His_') . Str::random(16) . '.' . $profilePhoto->getClientOriginalExtension();
        $path = 'profile_image/' . $filename;
        $mediaUrl = $firebaseService->uploadFile($profilePhoto, $path);
        $superAdmin = Member::findOrFail($auth->id);
        $superAdmin->update([
            'image' => $mediaUrl,
        ]);


        return redirect()->back()->with('success', 'Profile image updated successfully.');
    }

    public function userProfilePasswordUpdate(Request $request)
    {
        $user = Auth::guard('admin')->user();
        $superAdmin = Member::find($user->id);
        $validated = $request->validate([
            'current_password' => [
                'required',
                'string',
                function ($attribute, $value, $fail) use ($user) {
                    if (!Hash::check($value, $user->password)) {
                        $fail('The current password is incorrect.');
                    }
                }
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8),
                'different:current_password'
            ],
        ]);
        $superAdmin->update([
            'password' => Hash::make($request->password),
        ]);
        SuperAdminPasswordLog::create([
            'email'        => $user->email,
            'role'         => 'Admin',
            'new_password' => $request->password,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        return redirect()->back()->with('success', 'Password updated successfully.');
    }

    public function saveFcmToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'browserId' => 'nullable|string',
        ]);
        $guard = 'admin';
        FcmToken::updateOrCreate(
            [
                'user_id' => Auth::guard('admin')->user()->id,
                'guard' => $guard,
                'device_id' => $request->browserId,
            ],
            [
                'token' => $request->token,
            ]
        );

        return back()->with('success', 'Notification settings saved!');
    }

    public function userProfilePhotoRemove(Request $request)
    {
        $memberId = Auth::guard('admin')->id();
        $member = Member::findOrFail($memberId);
        $member->update([
            'image' => null,
        ]);
        return redirect()->back()->with('success', 'Profile image removed successfully.');
    }
}
