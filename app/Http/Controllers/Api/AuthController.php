<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ForgotPasswordOtpRequest;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\Member;
use App\Models\SuperAdmin;
use App\Enums\ActionTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Register New Member
     *
     * Registers a new construction team member or contractor into the system. The account is created in a pending state until approved by a super admin.
     *
     * @tags Authentication
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:members,phone'],
            'email' => ['nullable', 'email', 'max:255', 'unique:members,email'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(6)],
            'password_confirmation' => ['required', 'string', 'min:6'],
            'terms_agreed' => ['required', 'boolean', 'in:1'],
            'alternate_number' => ['nullable', 'string', 'max:20'],
            'aadhaar_number' => ['nullable', 'string', 'max:20', 'unique:employees,aadhaar_number'],
            'pan_number' => ['nullable', 'string', 'max:20', 'unique:employees,pan_number'],
        ]);

        $creatorId = SuperAdmin::query()->value('id');
        if (! $creatorId) {
            return response()->json([
                'success' => false,
                'message' => 'Super admin not configured. System setup incomplete.',
            ], 500);
        }

        $usernameBase = 'user' . preg_replace('/\D+/', '', $validated['phone']);
        $username = $this->makeUnique('members', 'username', substr($usernameBase, 0, 20));

        $slugBase = 'member-' . preg_replace('/\D+/', '', $validated['phone']);
        $slug = $this->makeUnique('members', 'slug', Str::slug($slugBase));

        $member = null;
        $employee = null;

        DB::transaction(function () use ($validated, $creatorId, $username, $slug, $request, &$member, &$employee) {
            $member = Member::create([
                'uuid' => (string) Str::uuid(),
                'created_by' => $creatorId,
                'name' => $validated['name'],
                'username' => $username,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'],
                'company_name' => $validated['company_name'] ?? null,
                'state' => $validated['state'] ?? null,
                'city' => $validated['city'] ?? null,
                'password' => Hash::make($validated['password']),
                'status' => Member::STATUS_PENDING,
                'registration_source' => $request->isJson() || $request->wantsJson() || $request->header('X-Mobile-App')
                    ? 'mobile_api'
                    : 'web',
                'roles' => [],
                'departments' => [],
                'designation' => [],
                'slug' => $slug,
            ]);

            $employee = Employee::create([
                'member_id' => $member->id,
                'alternate_number' => $validated['alternate_number'] ?? null,
                'aadhaar_number' => $validated['aadhaar_number'] ?? null,
                'pan_number' => $validated['pan_number'] ?? null,
            ]);
        });

        $this->issueOtp($member);

        $payload = [
            'success' => true,
            'message' => 'Registration submitted successfully. Your account is pending admin approval.',
            'member_id' => $member->id,
            'member_uuid' => $member->uuid,
            'employee_id' => $employee->employee_id,
            'employee_uuid' => $employee->uuid,
            'status' => 'pending_approval',
            'status_text' => 'Pending Admin Approval',
            'estimated_time' => 'Typically within 24-48 hours',
        ];

        if (config('app.debug')) {
            $payload['otp'] = $member->otp;
            $payload['otp_expire_at'] = optional($member->otp_expire)->toISOString();
        }

        return response()->json($payload, 201);
    }

    /**
     * Check Registration & Approval Status
     *
     * Inquires whether a registered member account is pending approval, approved, or rejected based on phone number.
     *
     * @tags Authentication
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function registrationStatus(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $member = Member::query()->with('employee')->where('phone', $validated['phone'])->first();

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'No registration found for this phone number.',
                'registered' => false,
            ], 404);
        }

        $statusMap = [
            Member::STATUS_PENDING => [
                'key' => 'pending_approval',
                'text' => 'Pending Admin Approval',
                'description' => 'Your account is awaiting review by our admin team.',
                'can_login' => false,
            ],
            Member::STATUS_ACTIVE => [
                'key' => 'approved',
                'text' => 'Account Approved',
                'description' => $member->approved_at
                    ? 'Your account was approved on ' . optional($member->approved_at)->format('M d, Y') . '.'
                    : 'Your account is active and ready to use.',
                'can_login' => true,
                'has_roles' => !empty($member->roles),
                'role_names' => $member->role_names,
            ],
            Member::STATUS_REJECTED => [
                'key' => 'rejected',
                'text' => 'Registration Rejected',
                'description' => $member->approval_remark
                    ? 'Reason: ' . $member->approval_remark
                    : 'Unfortunately, your registration was not approved.',
                'can_login' => false,
            ],
        ];

        $statusInfo = $statusMap[$member->status] ?? $statusMap[Member::STATUS_PENDING];

        return response()->json([
            'success' => true,
            'registered' => true,
            'member' => [
                'uuid' => $member->uuid,
                'name' => $member->name,
                'phone' => $member->phone,
                'email' => $member->email,
                'company_name' => $member->company_name,
                'state' => $member->state,
                'city' => $member->city,
                'employee_id' => $member->employee?->employee_id,
                'alternate_number' => $member->employee?->alternate_number,
                'aadhaar_number' => $member->employee?->aadhaar_number,
                'pan_number' => $member->employee?->pan_number,
            ],
            'approval' => $statusInfo + [
                'code' => (int) $member->status,
                'registered_at' => optional($member->created_at)->toISOString(),
                'approved_at' => optional($member->approved_at)->toISOString(),
                'rejected_at' => optional($member->rejected_at)->toISOString(),
                'approval_remark' => $member->approval_remark,
            ],
        ]);
    }

    /**
     * Member Login (Password-based)
     *
     * Authenticates an approved member using phone, email, or username along with their password, returning a Sanctum Bearer token and member profile.
     *
     * @tags Authentication
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $identifier = $validated['identifier'];

        $member = Member::query()
            ->where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 422);
        }

        if ($member->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is pending admin approval. You will be notified once approved.',
                'error_code' => 'PENDING_APPROVAL',
                'can_check_status' => true,
            ], 403);
        }

        if ($member->isRejected()) {
            return response()->json([
                'success' => false,
                'message' => 'Your registration was not approved.' . ($member->approval_remark ? " Reason: {$member->approval_remark}" : ''),
                'error_code' => 'ACCOUNT_REJECTED',
            ], 403);
        }

        if (! $member->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive. Please contact admin.',
            ], 403);
        }

        if ($member->is_calling_team) {
            return response()->json([
                'success' => false,
                'message' => 'Use the calling team portal to access your account.',
            ], 403);
        }

        $roles = is_array($member->roles) ? $member->roles : [];
        if (empty($roles)) {
            return response()->json([
                'success' => false,
                'message' => 'No role assigned. Please contact admin to assign your construction role (Driver, Surveyor, Draftsman, Supervisor).',
                'error_code' => 'NO_ROLE_ASSIGNED',
            ], 403);
        }

        if (! Hash::check($validated['password'], $member->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 422);
        }

        $tokenName = $validated['device_name'] ?? ($request->userAgent() ?: 'api-token');
        $member->tokens()->where('name', $tokenName)->delete();

        $token = $member->createToken($tokenName)->plainTextToken;

        ActivityLog::create([
            'user_id'     => $member->id,
            'user_role'   => 'doer',
            'action_type' => ActionTypeEnum::LOGIN,
            'description' => 'Member logged in via API (' . $tokenName . ')',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'action_time' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'token' => $token,
            'token_type' => 'Bearer',
            'member' => $member->loadMissing(['employee', 'fcm_token']),
            'role_names' => $member->role_names,
            'department_names' => $member->department_names,
            'designation_names' => $member->designation_names,
            'must_change_password' => (bool) $member->must_change_password,
        ]);
    }

    /**
     * Send OTP for Member Authentication
     *
     * Issues and sends a 6-digit One-Time Password (OTP) to the member's registered phone number.
     *
     * @tags Authentication
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendOtp(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $member = Member::query()->where('phone', $validated['phone'])->first();

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'No account found for this phone number.',
            ], 404);
        }

        if ($member->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is pending admin approval. OTP login is not available until approved.',
                'error_code' => 'PENDING_APPROVAL',
            ], 403);
        }

        if ($member->isRejected()) {
            return response()->json([
                'success' => false,
                'message' => 'Your registration was not approved.',
            ], 403);
        }

        if (! $member->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Account is not active.',
            ], 403);
        }

        $this->issueOtp($member);

        $payload = [
            'success' => true,
            'message' => 'OTP sent successfully.',
        ];

        if (config('app.debug')) {
            $payload['otp'] = $member->otp;
            $payload['otp_expire_at'] = optional($member->otp_expire)->toISOString();
        }

        return response()->json($payload);
    }

    /**
     * Verify OTP & Login
     *
     * Verifies the 6-digit OTP received by the member and generates a Sanctum Bearer access token upon success.
     *
     * @tags Authentication
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyOtp(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'otp' => ['required', 'string', 'max:10'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $member = Member::query()->where('phone', $validated['phone'])->first();

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'No account found for this phone number.',
            ], 404);
        }

        if (! $member->otp || ! $member->otp_expire || $member->otp_expire->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'OTP expired. Please request a new OTP.',
            ], 422);
        }

        if ((string) $member->otp !== (string) $validated['otp']) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP.',
            ], 422);
        }

        $member->forceFill([
            'otp' => null,
            'otp_expire' => null,
            'phone_verify_at' => now(),
        ])->save();

        $roles = is_array($member->roles) ? $member->roles : [];

        $tokenName = $validated['device_name'] ?? ($request->userAgent() ?: 'api-token');
        $token = $member->createToken($tokenName)->plainTextToken;

        ActivityLog::create([
            'user_id'     => $member->id,
            'user_role'   => 'doer',
            'action_type' => ActionTypeEnum::LOGIN,
            'description' => 'Member logged in via OTP verification (' . $tokenName . ')',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'action_time' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully.',
            'token' => $token,
            'token_type' => 'Bearer',
            'member' => $member->loadMissing(['employee', 'fcm_token']),
            'role_names' => $member->role_names,
        ]);
    }

    /**
     * Request Password Reset OTP
     *
     * Initiates the forgot-password flow by generating a static 6-digit `reset_otp` (`123456`) with a 2-minute expiration window and saving it on the member record.
     *
     * @tags Authentication
     * @param ForgotPasswordOtpRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(ForgotPasswordOtpRequest $request)
    {
        return $this->sendResetOtpResponse($request->validated(), 'Password reset OTP sent successfully.');
    }

    /**
     * Resend Password Reset OTP
     *
     * Re-issues the static 6-digit `reset_otp` (`123456`) and resets the 2-minute expiration timer for an ongoing password reset request.
     *
     * @tags Authentication
     * @param ForgotPasswordOtpRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendResetOtp(ForgotPasswordOtpRequest $request)
    {
        return $this->sendResetOtpResponse($request->validated(), 'Password reset OTP resent successfully.');
    }

    /**
     * Verify Password Reset OTP
     *
     * Validates the submitted `reset_otp` (`otp`) against the stored value within the 2-minute expiry window and returns a short-lived `reset_token` to authorize password reset.
     *
     * @tags Authentication
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyResetOtp(Request $request)
    {
        $validated = $request->validate([
            'identifier' => ['required_without_all:email,phone', 'nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'otp' => ['required_without:reset_otp', 'nullable', 'string', 'max:10'],
            'reset_otp' => ['nullable', 'string', 'max:10'],
        ]);

        $resolved = $this->resolveResetMemberFromValidated($validated);
        if ($resolved instanceof \Illuminate\Http\JsonResponse) {
            return $resolved;
        }
        $member = $resolved;

        if (! $member->reset_otp || ! $member->reset_otp_expires_at || $member->reset_otp_expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Reset OTP expired. Please request a new OTP.',
            ], 422);
        }

        $submittedOtp = (string) ($validated['otp'] ?? $validated['reset_otp'] ?? '');
        if (! hash_equals((string) $member->reset_otp, $submittedOtp)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid reset OTP.',
            ], 422);
        }

        $resetToken = Str::random(64);
        $member->forceFill([
            'reset_otp' => null,
            'reset_otp_expires_at' => null,
            'reset_otp_verified_at' => now(),
            'reset_password_token' => $resetToken,
            'reset_password_token_expires_at' => now()->addMinutes(10),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Reset OTP verified successfully.',
            'reset_token' => $resetToken,
            'expires_in_seconds' => 600,
        ]);
    }

    /**
     * Reset Password (After OTP Verification)
     *
     * Sets a new password for a member whose reset OTP was verified. Requires `new_password` and matching `new_password_confirmation`.
     *
     * @tags Authentication
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'identifier' => ['required_without_all:email,phone', 'nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'reset_token' => ['nullable', 'string', 'max:255'],
            'new_password' => ['required', 'string', 'confirmed', Password::min(6)],
            'new_password_confirmation' => ['required', 'string', 'min:6'],
        ]);

        $resolved = $this->resolveResetMemberFromValidated($validated);
        if ($resolved instanceof \Illuminate\Http\JsonResponse) {
            return $resolved;
        }
        $member = $resolved;

        $isVerifiedWindowValid = $member->reset_otp_verified_at !== null
            && $member->reset_otp_verified_at->greaterThanOrEqualTo(now()->subMinutes(10));

        if (! $isVerifiedWindowValid) {
            return response()->json([
                'success' => false,
                'message' => 'Reset OTP has not been verified or verification has expired.',
            ], 422);
        }

        if (! empty($validated['reset_token'])) {
            $tokenValid = $member->reset_password_token
                && $member->reset_password_token_expires_at
                && ! $member->reset_password_token_expires_at->isPast()
                && hash_equals((string) $member->reset_password_token, (string) $validated['reset_token']);

            if (! $tokenValid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired reset token.',
                ], 422);
            }
        }

        $member->forceFill([
            'password' => Hash::make($validated['new_password']),
            'must_change_password' => false,
            'reset_otp' => null,
            'reset_otp_expires_at' => null,
            'reset_otp_verified_at' => null,
            'reset_password_token' => null,
            'reset_password_token_expires_at' => null,
        ])->save();

        // Invalidate old Sanctum tokens so leaked credentials cannot persist across password resets
        $member->tokens()->delete();

        ActivityLog::create([
            'user_id'     => $member->id,
            'user_role'   => 'doer',
            'action_type' => ActionTypeEnum::LOGIN,
            'description' => 'Member reset password via Forgot Password OTP API',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'action_time' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. You can now log in with your new password.',
        ]);
    }

    /**
     * Get Authenticated Member Profile
     *
     * Returns the currently authenticated member's details, roles, employee ID, and approval status.
     *
     * @tags Authentication
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        $member = $request->user();
        $member->loadMissing(['employee', 'fcm_token', 'approver:id,name']);

        return response()->json([
            'success' => true,
            'member' => $member,
            'employee_id' => $member->employee_code,
            'roles' => $member->roles,
            'assigned_roles' => $member->assigned_roles,
            'assigned_role_names' => $member->assigned_role_names,
            'role_names' => $member->role_names,
            'department_names' => $member->department_names,
            'designation_names' => $member->designation_names,
            'status_info' => [
                'code' => (int) $member->status,
                'text' => match((int) $member->status) {
                    Member::STATUS_PENDING => 'Pending Approval',
                    Member::STATUS_ACTIVE => 'Active',
                    Member::STATUS_REJECTED => 'Rejected',
                    default => 'Unknown',
                },
            ],
        ]);
    }

    /**
     * Logout Member
     *
     * Revokes the current Sanctum Bearer access token and logs the activity.
     *
     * @tags Authentication
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $member = $request->user();
        $token = $member?->currentAccessToken();

        if ($token) {
            $tokenName = $token->name;

            ActivityLog::create([
                'user_id'     => $member->id,
                'user_role'   => 'doer',
                'action_type' => ActionTypeEnum::LOGOUT,
                'description' => 'Member logged out from API (' . $tokenName . ')',
                'ip_address'  => $request->ip(),
                'user_agent'  => $request->userAgent(),
                'action_time' => now(),
            ]);

            $token->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    private function sendResetOtpResponse(array $validated, string $successMessage): \Illuminate\Http\JsonResponse
    {
        $resolved = $this->resolveResetMemberFromValidated($validated);
        if ($resolved instanceof \Illuminate\Http\JsonResponse) {
            return $resolved;
        }
        $member = $resolved;

        $expiresAt = now()->addMinutes(2);
        $member->forceFill([
            'reset_otp' => '123456',
            'reset_otp_expires_at' => $expiresAt,
            'reset_otp_verified_at' => null,
            'reset_password_token' => null,
            'reset_password_token_expires_at' => null,
        ])->save();

        $payload = [
            'success' => true,
            'message' => $successMessage,
            'otp_expires_at' => $expiresAt->toISOString(),
            'expires_in_seconds' => 120,
        ];

        if (config('app.debug')) {
            $payload['otp'] = '123456';
        }

        return response()->json($payload);
    }

    private function resolveResetMemberFromValidated(array $validated): Member|\Illuminate\Http\JsonResponse
    {
        $identifier = trim((string) ($validated['identifier'] ?? $validated['email'] ?? $validated['phone'] ?? ''));

        $member = Member::query()
            ->where(function ($q) use ($identifier) {
                $q->where('email', $identifier)
                    ->orWhere('phone', $identifier)
                    ->orWhere('username', $identifier);
            })
            ->first();

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'No account found for the provided email, phone, or username.',
            ], 404);
        }

        if ($member->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is pending admin approval.',
                'error_code' => 'PENDING_APPROVAL',
            ], 403);
        }

        if ($member->isRejected()) {
            return response()->json([
                'success' => false,
                'message' => 'Your registration was not approved.',
                'error_code' => 'ACCOUNT_REJECTED',
            ], 403);
        }

        if (! $member->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive. Please contact admin.',
            ], 403);
        }

        return $member;
    }

    private function issueOtp(Member $member): void
    {
        $otp = config('app.debug') ? '123456' : (string) random_int(100000, 999999);
        $member->forceFill([
            'otp' => $otp,
            'otp_expire' => now()->addMinutes(5),
        ])->save();
    }

    private function makeUnique(string $table, string $column, string $base): string
    {
        $value = $base;
        $i = 1;

        while (\Illuminate\Support\Facades\DB::table($table)->where($column, $value)->exists()) {
            $suffix = (string) $i;
            $value = substr($base, 0, max(0, 255 - strlen($suffix) - 1)) . '-' . $suffix;
            $i++;
        }

        return $value;
    }
}
