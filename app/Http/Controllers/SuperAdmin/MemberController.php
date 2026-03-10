<?php

namespace App\Http\Controllers\SuperAdmin;

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
use App\Models\Designation;
use App\Models\SuperAdmin;
use App\Models\Task;
use App\Models\TaskInstance;
use Carbon\Carbon;
use App\Jobs\SendAccountCreationEmail;
use App\Models\EmailLog;
use App\Models\User;
use App\Models\SuperAdminPasswordLog;
use App\Jobs\SuperAdminPasswordChangeNotification;
use App\Models\WhatsappLog;
use App\Services\InteraktServices;
use App\Services\FirebaseService;
use function App\createMessagePayload;

class MemberController extends Controller
{

    /**
     * Display a listing of the members with optional filters.
     *
     * @param Request $request The incoming request with optional query params: search, status, per_page.
     * @return Response Returns an Inertia response with paginated members.
     */
   public function index(Request $request)
{
    $members = Member::query()
        ->when(
            $request->search,
            fn($q) =>
            $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%")
                ->orWhere('phone', 'like', "%{$request->search}%")
        )
        ->when(
            $request->status,
            fn($q) => $q->where('status', $request->status == 'active' ? 1 : 0)
        )
        // Order by roles priority: 2 first, then 1, then 3
        ->orderByRaw("
            CASE
                WHEN JSON_CONTAINS(roles, '\"2\"') THEN 1
                WHEN JSON_CONTAINS(roles, '\"1\"') THEN 2
                WHEN JSON_CONTAINS(roles, '\"3\"') THEN 3
                ELSE 4
            END
        ")
        ->paginate($request->per_page ?? 10);

    $members->getCollection()->transform(function ($member) {
        $departmentIds = is_array($member->departments) ? $member->departments : ($member->departments ? [$member->departments] : []);
        $member->departments_data = Department::whereIn('id', $departmentIds)->get();
        $designationIds = is_array($member->designation) ? $member->designation : ($member->designation ? [$member->designation] : []);
        $member->designations_data = Designation::whereIn('id', $designationIds)->get();
        return $member;
    });

    $departments = Department::where('status', 1)->get(['id', 'name']);
    $roles = Role::where('status', 1)->get(['id', 'name']);

    return Inertia::render('SuperAdmin/Members/List', [
        'members' => $members,
        'departments' => $departments,
        'roles' => $roles,
        'filters' => $request->only(['search', 'status', 'per_page']),
    ]);
}

    /**
     * Store a newly created member or update an existing member.
     *
     * @param Request $request The request containing member data.
     * @param int|null $id Optional member ID for updating.
     * @return RedirectResponse Redirects back with success message.
     *
     * @throws \Illuminate\Validation\ValidationException If the request validation fails.
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If the member is not found for update.
     */

    public function store(Request $request, $id = null,FirebaseService $firebaseService)
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required',
                'string',
                Rule::unique('members')->ignore($id)->whereNull('deleted_at'),
            ],
            'email' => [
                'nullable',
                'email',
                Rule::unique('members')->ignore($id)->whereNull('deleted_at'),
            ],
            'departments' => 'required|array',
            'designations' => 'required|array',
            'roles' => 'required|array',
            'gender' => 'nullable|in:male,female,other',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'password' => $id ? 'nullable|min:6|same:confirm_password' : 'required|min:6|same:confirm_password',
            'confirm_password' => $id ? 'nullable|min:6' : 'required|min:6',
            'dob' => 'nullable',
        ];

        $messages = [
            'phone.unique' => 'This phone number is already in use.',
            'email.unique' => 'This email address is already in use.',
            'departments.required' => 'Please select at least one department.',
            'designations.required' => 'Please select at least one designation.',
            'roles.required' => 'Please select at least one role.',
        ];

        $validated = $request->validate($rules, $messages);

        try {
            $data = [
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'] ?? null,
                'departments' => $validated['departments'],
                'roles' => $validated['roles'],
                'slug' => Str::slug($validated['name'] . '-' . Str::random(4)),
                'gender' => $validated['gender'] ?? null,
                'designation' => $request->designations,
                'dob'  => $request->dob,
            ];

            // Only generate username during creation
            if (is_null($id)) {
                $data['username'] = $this->generateUsername($validated['name']);
            }

            if (!empty($validated['password'])) {
                $data['password'] = Hash::make($validated['password']);
            }

            if ($request->hasFile('image')) {
                 $now = now();
        $profilePhoto = $request->file('image');
        $filename = $now->format('Y_m_d_His_') . Str::random(16) . '.' . $profilePhoto->getClientOriginalExtension();
                        $path = 'profile_image/' . $filename;
                        $mediaUrl = $firebaseService->uploadFile($profilePhoto, $path);

                $data['image'] = $mediaUrl;
            }

            if ($id) {
                // Update case
                $member = Member::findOrFail($id);
                $member->update($data);
                $message = 'Member updated successfully!';
                if (in_array(2, $member->roles)) {
                $existingSuperAdmin = SuperAdmin::where('phone', $member->phone)->first();
                if (!$existingSuperAdmin) {
                    SuperAdmin::create([
                        'name'           => $request->name,
                        'roles'          => "super",
                        'phone'          => $request->phone,
                        'whatsapp_phone' => $request->phone,
                        'status'         => 1,
                        'username'       => $data['username'] ?? null,
                        'password'       => Hash::make($request->password),
                        'profile_image'  => $member->image ?? ($data['image'] ?? null),
                    ]);
                }
            }
                $phoneNumber = $member->phone;
                $templateName = "member_profile_update_message";
                $languageCode = "en";
                $bodyParameters = [
                    $member->name ?? '--',
                    $member->email ?? '--',
                    $member->phone ?? '--',
                    $member->gender ?? '--',
                    $member->dob ?? '--',
                    $member->department_names ?? '--',
                    $member->designation_names ?? '--',
                    $member->role_names ?? '--'
                ];
                $payload = createMessagePayload($phoneNumber, $templateName, $languageCode, null, $bodyParameters);
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
            } else {
                $existing = Member::withTrashed()
                    ->where(function ($q) use ($validated) {
                        $q->where('phone', $validated['phone']);
                        if (!empty($validated['email'])) {
                            $q->orWhere('email', $validated['email']);
                        }
                    })
                    ->first();

                if ($existing && $existing->trashed()) {
                    // Restore instead of inserting duplicate
                    $existing->restore();
                    $existing->update($data);
                    $member = $existing;
                    $message = 'Member restored successfully!';
                } else {
                    $data['created_by'] = auth('superadmin')->id();
                    $member = Member::create($data);

                    //Send Message
                    $phoneNumber = $member->phone;
                    $templateName = "member_add_message";
                    $languageCode = "en";
                    $bodyParameters = [
                        $member->name ?? '--',
                        $member->email ?? '--',
                        $member->phone ?? '--',
                        $member->department_names ?? '--',
                        $member->designation_names ?? '--',
                        $member->role_names ?? '--'
                    ];
                    $payload = createMessagePayload($phoneNumber, $templateName, $languageCode, null, $bodyParameters);
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

                    $message = 'Member created successfully!';
                }

                // ✅ Send email only for fresh creation (not restore)
                if (!empty($validated['email']) && (!$existing || !$existing->wasRecentlyRestored)) {
                    $departmentNames = Department::whereIn('id', $validated['departments'])->pluck('name')->implode(', ');
                    $designationNames = Designation::whereIn('id', $request->designations)->pluck('name')->implode(', ');

                    SendAccountCreationEmail::dispatchSync(
                        $validated['email'],
                        $validated['name'],
                        $data['username'],
                        $request->password, // plain password
                        $departmentNames,
                        $designationNames
                    );

                    EmailLog::create([
                        'user_id' => auth('superadmin')->id(),
                        'subject' => 'Account Creation Notification',
                        'to' => $validated['email'],
                        'from' => config('mail.from.address'),
                        'body_html' => 'Account created for ' . $validated['name'] .
                            ' with username: ' . $data['username'] .
                            ' and departments: ' . $departmentNames,
                        'status' => 'sent',
                        'sent_at' => now(),
                        'ip' => $request->ip(),
                        'user_agent' => $request->header('User-Agent'),
                    ]);
                }
            }

            if (in_array(2, $request->roles)) {
                $existingSuperAdmin = SuperAdmin::where(function ($q) use ($request, $data) {
                    $q->where('phone', $request->phone)
                        ->orWhere('username', $data['username'] ?? null);
                })->first();

                if (!$existingSuperAdmin) {
                    SuperAdmin::create([
                        'name' => $request->name,
                        'roles' => "super",
                        'phone' => $request->phone,
                        'whatsapp_phone' => $request->phone,
                        'status' => 1,
                        'username' => $data['username'] ?? null,
                        'password' => Hash::make($request->password),
                    ]);
                }
            }

            return redirect()->back()->with('success', $message);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }






    /**
     * Soft delete the specified member.
     *
     * @param int $id The ID of the member to delete.
     * @return RedirectResponse Redirects back with a success message.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If the member is not found.
     */
    public function destroy($id)
    {
        $member = Member::where('uuid', $id)->first();
        if (!empty($member)) {
            $member->delete();
        }
        return redirect()->route('super.members.list');
    }

  protected function generateUsername(string $name, $excludeId = null): string
{
    $cleanName = Str::lower(preg_replace('/[^a-z0-9]/', '', $name));
    $base = $cleanName;
    $counter = 0;

    do {
        $username = $base . ($counter > 0 ? $counter : '');
        $exists = Member::where('username', $username)
            ->whereNull('deleted_at')
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();
        $counter++;
    } while ($exists);

    return $username;
}

    protected function applyPattern(string $cleanName, string $pattern): string
    {
        $parts = explode(' ', Str::title($cleanName));

        if (count($parts) < 2) {
            return $cleanName;
        }

        $first = Str::lower($parts[0]);
        $last = Str::lower($parts[1]);

        return match ($pattern) {
            'first.last' => $first . '.' . $last,
            'first_last' => $first . '_' . $last,
            'firstlast'  => $first . $last,
            'f.last'     => $first[0] . '.' . $last,
            'first.l'    => $first . '.' . $last[0],
            'flast'      => $first[0] . $last,
            default      => $first . $last,
        };
    }

    public function updatePassword(Request $request, Member $member)
    {
        $validated = $request->validate([
            'new_password' => [
                'required',
                'string',
                'confirmed',
                Password::min(6),
            ],
        ]);
        $superAdmin = Auth::guard('superadmin')->user();
        SuperAdminPasswordLog::create([
            'email'        => $member->email,
            'role'         => 'super_admin',
            'new_password' => $request->new_password,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
        $member->update([
            'password' => Hash::make($validated['new_password']),
        ]);
        if ($member->email) {
            EmailLog::create([
                'user_id'   => $superAdmin->id,
                'subject'   => 'Password Updated Notification',
                'to'        => $member->email,
                'from'      => config('mail.from.address'),
                'body_html' => 'Hello ' . $member->name . ',<br><br>'
                    . 'Your account password has been updated by Super Admin.<br>'
                    . 'If you did not request this change, please contact support immediately.<br><br>'
                    . 'Regards,<br>'
                    . 'Shashi Task Tracking',
                'status'    => 'sent',
                'sent_at'   => now(),
                'ip'        => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);
            SuperAdminPasswordChangeNotification::dispatch($member, $superAdmin, $validated['new_password']);
        }

        return redirect()->back()->with('success', 'Password updated successfully');
    }

    public function getByDepartments(Request $request)
    {
        try {
            $departmentIds = $request->input('department_ids', []);
            if (!is_array($departmentIds)) {
                $departmentIds = [$departmentIds];
            }
            $departmentIds = array_filter($departmentIds, function ($id) {
                return is_numeric($id) && $id > 0;
            });
            if (empty($departmentIds)) {
                return response()->json([]);
            }

            $designations = Designation::query()
                ->whereIn('department_id', $departmentIds)
                ->where('status', 1)
                ->orderBy('name')
                ->get(['id', 'name', 'department_id']);

            return response()->json($designations);
        } catch (\Exception $e) {
            return response()->json([], 500);
        }
    }
    public function updateStatus(Request $request, $uuid, $status = 1)
    {
        try {
            $member = Member::where('uuid', $uuid)->first();
            if (!$member) {
                return redirect()->back()->with('error', 'Member not found.');
            }
            $validatedStatus = $request->status ?? $status;
            if (!in_array($validatedStatus, [0, 1])) {
                return redirect()->back()->with('error', 'Invalid status value.');
            }
            $member->status = $validatedStatus;
            $member->save();

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

            return redirect()->back()->with('success', 'Member status updated successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Something went wrong while updating the member status.');
        }
    }

    public function memberDetails(Request $request, $uuid)
    {
        $member = Member::where('uuid', $uuid)->firstOrFail();
        $taskQuery = Task::with([
            'creator',
            'assignedMembers' => function ($query) use ($member) {
                $query->withPivot(['uuid as task_assignment_uuid', 'assigned_by', 'start_date', 'end_date']);
            },
            'instances' => function ($query) use ($member) {
                $query->where('assigned_to', $member->id)
                    ->select(['uuid', 'task_id', 'due_date', 'status', 'completed_at']);
            }
        ])
            ->whereHas('assignedMembers', function ($query) use ($member) {
                $query->where('assigned_to', $member->id);
            });
        $taskQuery->when($request->search, function ($q) use ($request) {
            $q->where(function ($query) use ($request) {
                $query->where('title', 'like', "%{$request->search}%")
                    ->orWhere('description', 'like', "%{$request->search}%");
            });
        })
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->date_range, function ($q) use ($request) {
                $dates = explode(' to ', $request->date_range);
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = isset($dates[1]) ? Carbon::parse($dates[1])->endOfDay() : $startDate->copy()->endOfDay();

                $q->where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate])
                        ->orWhere(function ($q) use ($startDate, $endDate) {
                            $q->where('start_date', '<=', $startDate)
                                ->where('end_date', '>=', $endDate);
                        });
                });
            });
        $tasks = $taskQuery->latest()
            ->paginate($request->per_page ?? 10)
            ->withQueryString();
        $tasks->getCollection()->transform(function ($task) {
            if ($task->instances) {
                $task->instances->transform(function ($instance) {
                    $instance->due_date = $instance->due_date ? Carbon::parse($instance->due_date)->format('Y-m-d H:i') : null;
                    $instance->completed_at = $instance->completed_at ? Carbon::parse($instance->completed_at)->format('Y-m-d H:i') : null;
                    return $instance;
                });
            }
            return $task;
        });
        $taskStats = Task::whereHas('assignedMembers', fn($q) => $q->where('assigned_to', $member->id))
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress")
            ->selectRaw("SUM(CASE WHEN status = 'running' THEN 1 ELSE 0 END) as running")
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue")
            ->selectRaw("SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed")
            ->first()
            ->toArray();
        $now = now();
        $taskInstanceStats = TaskInstance::where('assigned_to', $member->id)
            ->selectRaw('COUNT(*) as total_instances')
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_instances")
            ->selectRaw("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_instances")
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_instances")
            ->selectRaw("SUM(CASE WHEN due_date < ? AND status <> 'completed' THEN 1 ELSE 0 END) as overdue_instances", [$now])
            ->first()
            ->toArray();
        $taskStats = array_map('intval', $taskStats);
        $taskInstanceStats = array_map('intval', $taskInstanceStats);
        return Inertia::render('SuperAdmin/Members/MemberDetails', [
            'member' => $member,
            'tasks' => array_merge($tasks->toArray(), $taskStats),
            'task_instances' => $taskInstanceStats,
            'filters' => $request->only(['search', 'status', 'date_range', 'per_page']),
        ]);
    }

    public function emailLogs(Request $request, $uuid)
    {
        $user = Member::where('uuid', $uuid)->firstOrFail();
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        $subject = $request->input('subject');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $query = EmailLog::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere('to', $user->email);
        })
            ->orderBy('sent_at', 'desc');
        if ($subject) {
            $query->where('subject', 'LIKE', '%' . $subject . '%');
        }
        if ($startDate) {
            $query->whereDate('sent_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('sent_at', '<=', $endDate);
        }
        $emailLogs = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json([
            'emailLogs' => $emailLogs,
            'filters' => [
                'subject' => $subject,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'per_page' => $perPage,
                'current_page' => $page
            ],
            'status' => true
        ]);
    }
}
