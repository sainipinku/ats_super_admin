<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\SuperAdmin;
use App\Models\Construction\MemberRoleAssignment;
use App\Models\Construction\Role as ConstructionRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SuperAdminApprovalApiController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            if (! $user || ! method_exists($user, 'isSuperAdmin') || ! $user->isSuperAdmin()) {
                $member = Member::find($user?->id);
                $isSuper = $member?->isSuperAdmin() || in_array('2', (array) ($member?->roles ?? []), true);
                if (! $isSuper) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized. Super Admin access required.',
                    ], 403);
                }
            }
            return $next($request);
        });
    }

    public function pendingApprovals(Request $request)
    {
        $perPage = (int) ($request->per_page ?? 20);
        $search = $request->search;

        $query = Member::query()
            ->pendingApproval()
            ->with(['approver:id,name'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('state', 'like', "%{$search}%");
                });
            })
            ->latest();

        $members = $query->paginate($perPage);

        $members->getCollection()->transform(function ($m) {
            return [
                'id'                => $m->id,
                'uuid'              => $m->uuid,
                'name'              => $m->name,
                'email'             => $m->email,
                'phone'             => $m->phone,
                'company_name'      => $m->company_name,
                'state'             => $m->state,
                'city'              => $m->city,
                'phone_verified'    => ! empty($m->phone_verify_at),
                'terms_agreed'      => (bool) $m->terms_agreed,
                'registered_at'     => optional($m->created_at)->toISOString(),
            ];
        });

        return response()->json([
            'success' => true,
            'pending_count' => Member::pendingApproval()->count(),
            'members' => $members,
        ]);
    }

    public function approve(Request $request, $memberId)
    {
        $validator = Validator::make($request->all(), [
            'construction_role_id' => ['required', 'integer', 'exists:construction_roles,id'],
            'project_id'           => ['nullable', 'integer', 'exists:construction_projects,id'],
            'global_role_ids'      => ['nullable', 'array'],
            'global_role_ids.*'    => ['integer'],
            'department_ids'       => ['nullable', 'array'],
            'department_ids.*'     => ['integer'],
            'designation_ids'      => ['nullable', 'array'],
            'designation_ids.*'    => ['integer'],
            'approval_notes'       => ['nullable', 'string', 'max:1000'],
            'temporary_password'   => ['nullable', 'string', 'min:6'],
            'is_senior'            => ['nullable', 'boolean'],
            'can_self_draft'       => ['nullable', 'boolean'],
            'notify_user'          => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validate();
        $member = Member::pendingApproval()->find($memberId);

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found in pending approval list.',
            ], 404);
        }

        $superAdminId = SuperAdmin::query()->value('id');
        if (empty($superAdminId)) {
            return response()->json([
                'success' => false,
                'message' => 'Super Admin not configured.',
            ], 500);
        }

        $passwordChanged = false;
        if (! empty($data['temporary_password'])) {
            $member->password = Hash::make($data['temporary_password']);
            $member->must_change_password = true;
            $passwordChanged = true;
        }

        $member->status          = 1;
        $member->approved_by     = $superAdminId;
        $member->approved_at     = now();
        $member->approval_notes  = $data['approval_notes'] ?? $member->approval_notes;
        $member->roles           = ! empty($data['global_role_ids']) ? $data['global_role_ids'] : [3];
        $member->departments     = $data['department_ids'] ?? ($member->departments ?? []);
        $member->designation     = $data['designation_ids'] ?? ($member->designation ?? []);
        $member->save();

        $roleAssignment = MemberRoleAssignment::firstOrCreate(
            [
                'member_id'  => $member->id,
                'role_id'    => (int) $data['construction_role_id'],
                'project_id' => ! empty($data['project_id']) ? (int) $data['project_id'] : null,
            ],
            [
                'is_senior'      => $data['is_senior'] ?? false,
                'can_self_draft' => $data['can_self_draft'] ?? false,
            ]
        );
        $roleAssignment->update([
            'is_senior'      => $data['is_senior'] ?? $roleAssignment->is_senior,
            'can_self_draft' => $data['can_self_draft'] ?? $roleAssignment->can_self_draft,
        ]);

        $roleName = ConstructionRole::where('id', (int) $data['construction_role_id'])->value('name');

        Log::info('Member approved by Super Admin.', [
            'member_id'     => $member->id,
            'sa_id'         => $superAdminId,
            'const_role'    => $data['construction_role_id'],
            'password_set'  => $passwordChanged,
        ]);

        return response()->json([
            'success'                 => true,
            'message'                 => 'Member approved successfully. ' . ($passwordChanged ? 'Temporary password set (must change on first login).' : ''),
            'member'                  => $member->fresh(['approver:id,name']),
            'construction_role_name'  => $roleName,
            'role_assignment_id'      => $roleAssignment->id,
            'temporary_password'      => $data['temporary_password'] ?? null,
            'must_change_password'    => (bool) $member->must_change_password,
        ]);
    }

    public function reject(Request $request, $memberId)
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:1000'],
            'notify_user'      => ['nullable', 'boolean'],
        ]);

        $member = Member::pendingApproval()->find($memberId);
        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found in pending approval list.',
            ], 404);
        }

        $member->status         = 0;
        $member->approved_by    = null;
        $member->approved_at    = null;
        $member->approval_notes = 'REJECTED: ' . $validated['rejection_reason'];
        $member->save();

        Log::info('Member registration rejected by Super Admin.', [
            'member_id' => $member->id,
            'reason'    => $validated['rejection_reason'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Member registration rejected.',
            'rejection_reason' => $validated['rejection_reason'],
        ]);
    }

    public function assignConstructionRole(Request $request, $memberId)
    {
        $validated = $request->validate([
            'construction_role_id' => ['required', 'integer', 'exists:construction_roles,id'],
            'project_id'           => ['nullable', 'integer', 'exists:construction_projects,id'],
            'is_senior'            => ['nullable', 'boolean'],
            'can_self_draft'       => ['nullable', 'boolean'],
        ]);

        $member = Member::approved()->find($memberId);
        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Approved member not found.',
            ], 404);
        }

        $assignment = MemberRoleAssignment::updateOrCreate(
            [
                'member_id'  => $member->id,
                'role_id'    => (int) $validated['construction_role_id'],
                'project_id' => ! empty($validated['project_id']) ? (int) $validated['project_id'] : null,
            ],
            [
                'is_senior'      => $validated['is_senior'] ?? false,
                'can_self_draft' => $validated['can_self_draft'] ?? false,
            ]
        );

        return response()->json([
            'success'          => true,
            'message'          => 'Construction role assigned successfully.',
            'role_assignment'  => $assignment,
            'member'           => $member->only(['id', 'name', 'phone', 'email']),
        ]);
    }
}
