<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Role;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\Member;
use App\Models\Department;
use App\Models\SuperAdmin;
use Carbon\Carbon;
use App\Models\TaskInstance;
use App\Models\Task;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Hash;
use App\Models\ActivityLog;
use App\Models\FcmToken;
use App\Models\SuperAdminPasswordLog;
use App\Models\ImageActionLog;
use Illuminate\Support\Facades\Validator;
use App\Services\FirebaseService;
use App\Models\Holiday;
class AdminDashboardController extends Controller
{
    /**
     * Display Dashboard
     * @return mixed
     */
    public function index(Request $request)
    {
        $auth = Auth::guard('superadmin')->user();
        $year = $request->input('year', date('Y'));
        $month = $request->input('month', date('n'));
        $chartType = $request->input('chartType', 'overview');
        $memberId = $request->input('member_id', null);
        $activeStaff = Member::where('status', 1)->get();
        $staffCount = $activeStaff->count();
        $activeDepartments = Department::where('status', 1)->get();
        $departmentCount = $activeDepartments->count();
        $taskData = [
            'total' => 0,
            'completed' => 0,
            'pending' => 0,
            'overdue' => 0,
            'types' => [],
            'statuses' => [],
            'trend' => [],
            'filter' => [
                'year' => $year,
                'month' => $month,
                'chartType' => $chartType,
                'member_id' => $memberId
            ]
        ];
        $taskQuery = Task::query()
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month);
        $taskInstanceQuery = TaskInstance::query()
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month);
        if ($memberId) {
            $taskQuery->whereHas('assignedMembers', function ($q) use ($memberId) {
                $q->where('assigned_to', $memberId);
            });
            $taskInstanceQuery->where('assigned_to', $memberId);
        }
        $taskData['total'] = $taskQuery->count();
        $taskData['completed'] = (clone $taskInstanceQuery)
            ->where('status', 'completed')
            ->count();
        $taskData['pending'] = (clone $taskInstanceQuery)
            ->where('status', 'pending')
            ->count();
        $taskData['overdue'] = (clone $taskInstanceQuery)
            ->where('due_date', '<', now())
            ->where('status', '!=', 'completed')
                        ->where('status', '!=', 'overdue')
            ->count();
        $taskTypesQuery = Task::selectRaw('task_type, count(*) as count')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month);
        if ($memberId) {
            $taskTypesQuery->whereHas('assignedMembers', function ($q) use ($memberId) {
                $q->where('assigned_to', $memberId);
            });
        }
        $taskData['types'] = $taskTypesQuery
            ->groupBy('task_type')
            ->get()
            ->mapWithKeys(fn($item) => [$item->task_type->value => $item->count])
            ->toArray();
        $taskStatusesQuery = TaskInstance::selectRaw('status, count(*) as count')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month);
        if ($memberId) {
            $taskStatusesQuery->where('assigned_to', $memberId);
        }
        $taskData['statuses'] = $taskStatusesQuery
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();
        $taskTrendQuery = TaskInstance::selectRaw(
            "DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed"
        )
            ->whereYear('created_at', $year);
        if ($memberId) {
            $taskTrendQuery->where('assigned_to', $memberId);
        }
        $taskData['trend'] = $taskTrendQuery
            ->groupBy('month')
            ->orderBy('month')
            ->get();
        $perPage = $request->input('perPage', 10);
        $activityLogs = ActivityLog::with('user')
            ->orderBy('action_time', 'desc')
            ->paginate($perPage)
            ->appends($request->except('page'));
        $activityLogs->getCollection()->transform(function ($log) {
            $log->action_time = Carbon::parse($log->action_time)
                ->setTimezone('Asia/Kolkata')
                ->toDateTimeString();
            return $log;
        });
        $perPagePasswordLog = $request->input('perPagePasswordLog', 10);
        $passwordLogQuery = SuperAdminPasswordLog::with(['superAdmin', 'member'])
            ->orderBy('created_at', 'desc');
        $passwordLogQuery->where(function ($query) {
            $query->whereHas('superAdmin')
                ->orWhereHas('member');
        });
        $superAdminPasswordLog = $passwordLogQuery
            ->paginate($perPagePasswordLog, ['*'], 'pagePasswordLog')
            ->appends($request->except('pagePasswordLog'));
        $perPageImageLog = $request->input('perPageImageLog', 10);
        $imageLogQuery = ImageActionLog::with('superAdmin')
            ->orderBy('created_at', 'desc');
        $imageActionLogs = $imageLogQuery
            ->paginate($perPageImageLog, ['*'], 'pageImageLog')
            ->appends($request->except('pageImageLog'));
        $holidays = Holiday::where('status',1)->get();
        return Inertia::render('SuperAdmin/Dashboard', [
            'auth' => $auth,
            'activityLogs' => $activityLogs,
            'passwordLogs' => $superAdminPasswordLog,
            'imageActionLogs' => $imageActionLogs,
            'members' => $activeStaff,
            'stats' => [
                'staff' => [
                    'count' => $staffCount,
                    'list' => $activeStaff,
                    'chartData' => $this->getMonthlyData(Member::class, 1, $year)
                ],
                'departments' => [
                    'count' => $departmentCount,
                    'list' => $activeDepartments,
                    'chartData' => $this->getMonthlyData(Department::class, 1, $year)
                ],
                'tasks' => $taskData,
                'holidays' => $holidays, 
            ]
        ]);
    }

    protected function getMonthlyData($model, $status = 1, $year = null)
    {
        $data = [];
        $now = Carbon::now();
        $year = $year ?? date('Y');

        for ($i = 11; $i >= 0; $i--) {
            $date = $now->copy()->subMonths($i);
            $month = $date->format('M');
            $year = $date->year;

            $count = $model::where('status', $status)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $date->month)
                ->count();

            $data[] = [
                'month' => $month,
                'count' => $count,
                'year' => $year
            ];
        }

        return $data;
    }
    /**
     * Logout User
     * @return mixed
     */
    public function logout(Request $request)
    {
        Auth::guard('superadmin')->logout();
        // $request->session()->invalidate();
        // $request->session()->regenerateToken();
        return redirect(route('super.login'))->with('success', 'Logout Succesfull');
    }

    public function userProfile(Request $request)
    {
        return Inertia::render('SuperAdmin/UserProfile');
    }

    public function userProfileUpdate(Request $request)
    {
        $user = Auth::guard('superadmin')->user();
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
    $superAdminId = Auth::guard('superadmin')->id();
    $now = now();
    $request->validate([
        'profile_photo' => 'required|file|max:2048|mimes:jpg,jpeg,png',
    ]);
    try {
        $profilePhoto = $request->file('profile_photo');
        $filename = $now->format('Y_m_d_His_') . Str::random(16) . '.' . $profilePhoto->getClientOriginalExtension();
        $path = 'profile_image/' . $filename;
        $mediaUrl = $firebaseService->uploadFile($profilePhoto, $path);
        $superAdmin = SuperAdmin::findOrFail($superAdminId);
        $superAdmin->update([
            'profile_image' => $mediaUrl,
        ]);
        return redirect()->back()->with('success', 'Profile image updated successfully.');
    } catch (\Exception $e) {
        return redirect()->back()->with('error', 'Failed to update profile image. ' . $e->getMessage());
    }
}
public function userProfilePhotoRemove(Request $request){
        $superAdminId = Auth::guard('superadmin')->id();
        $superAdmin = SuperAdmin::findOrFail($superAdminId);
          $superAdmin->update([
            'profile_image' => null,
        ]);
                return redirect()->back()->with('success', 'Profile image removed successfully.');

}

// public function userProfilePhotoUpdate(Request $request)
// {
//     $request->validate([
//         'profile_photo' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
//     ]);

//     $auth = Auth::guard('superadmin')->user();
//     $superAdmin = SuperAdmin::find($auth->id);

//     if ($request->hasFile('profile_photo')) {
//         if ($superAdmin->profile_image && Storage::disk('public')->exists($superAdmin->profile_image)) {
//             Storage::disk('public')->delete($superAdmin->profile_image);
//         }

//         $extension = $request->file('profile_photo')->getClientOriginalExtension();
//         $filename = now()->format('Ymd_His') . '_' . Str::random(5) . '.' . $extension;
//         $path = $request->file('profile_photo')->storeAs('super_admins', $filename, 'public');

//         $superAdmin->update([
//             'profile_image' => $path,
//         ]);
//     }

//     return redirect()->back()->with('success', 'Profile image updated successfully.');
// }
    public function userProfilePasswordUpdate(Request $request)
    {
        $user = Auth::guard('superadmin')->user();
        $superAdmin = SuperAdmin::find($user->id);
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
            'role'         => 'super_admin',
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
        if ($request->user('admin')) {
            $user = $request->user('admin');
            $guard = 'admin';
        } elseif ($request->user('superadmin')) {
            $user = $request->user('superadmin');
            $guard = 'superadmin';
        } elseif ($request->user('member')) {
            $user = $request->user('member');
            $guard = 'member';
        } else {
            return back()->with('error', 'User not authenticated');
        }

        FcmToken::updateOrCreate(
            [
                'user_id' => $user->id,
                'guard' => $guard,
                'device_id' => $request->browserId,
            ],
            [
                'token' => $request->token,
            ]
        );

        return back()->with('success', 'Notification settings saved!');
    }

    public function exportDashboardData(Request $request)
{
    $year = $request->input('year', date('Y'));
    $month = $request->input('month', date('n'));
    $memberId = $request->input('member_id', null);

    // Get the filtered data (similar to your index method)
    $taskQuery = Task::query()
        ->whereYear('created_at', $year)
        ->whereMonth('created_at', $month);

    $taskInstanceQuery = TaskInstance::query()
        ->whereYear('created_at', $year)
        ->whereMonth('created_at', $month);

    if ($memberId) {
        $taskQuery->whereHas('assignedMembers', function ($q) use ($memberId) {
            $q->where('assigned_to', $memberId);
        });

        $taskInstanceQuery->where('assigned_to', $memberId);
    }

    $tasks = $taskQuery->with(['assignedMembers.member', 'department'])->get();
    $taskInstances = $taskInstanceQuery->with(['task', 'assignedTo'])->get();

    // Format data for export
    $exportData = [
        'filters' => [
            'year' => $year,
            'month' => $month,
            'member' => $memberId ? Member::find($memberId)->name : 'All Members'
        ],
        'summary' => [
            'total_tasks' => $tasks->count(),
            'completed_tasks' => $taskInstances->where('status', 'completed')->count(),
            'pending_tasks' => $taskInstances->where('status', 'pending')->count(),
            'overdue_tasks' => $taskInstances->where('due_date', '<', now())
                ->where('status', '!=', 'completed')->count(),
        ],
        'tasks' => $tasks,
        'task_instances' => $taskInstances
    ];

    // Return as JSON (you could also implement CSV or Excel export)
    return response()->json($exportData);
}
}
