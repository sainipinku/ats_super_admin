<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Member;
use App\Models\SuperAdmin;
use App\Enums\ActionTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Legacy register (kept for backward compat — auto-activates)
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:members,phone'],
            'email' => ['nullable', 'email', 'max:255', 'unique:members,email'],
        ]);

        $creatorId = SuperAdmin::query()->value('id');
        if (! $creatorId) {
            return response()->json([
                'success' => false,
                'message' => 'Super admin not configured.',
            ], 500);
        }

        $usernameBase = 'user' . preg_replace('/\D+/', '', $validated['phone']);
        $username = $this->makeUnique('members', 'username', substr($usernameBase, 0, 20));

        $slugBase = 'member-' . preg_replace('/\D+/', '', $validated['phone']);
        $slug = $this->makeUnique('members', 'slug', Str::slug($slugBase));

        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $creatorId,
            'name' => $validated['name'],
            'username' => $username,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'],
            'password' => Str::random(32),
            'status' => 1,
            'approved_by' => $creatorId,
            'approved_at' => now(),
            'roles' => [3],
            'slug' => $slug,
        ]);

        $this->issueOtp($member);

        $payload = [
            'success' => true,
            'message' => 'Registered successfully. OTP sent to your phone.',
            'member_id' => $member->id,
        ];

        if (config('app.debug')) {
            $payload['otp'] = $member->otp;
            $payload['otp_expire_at'] = optional($member->otp_expire)->toISOString();
        }

        return response()->json($payload, 201);
    }

    /**
     * Registration v2 — full form per mobile UI screenshot.
     * Creates INACTIVE account (status=0) awaiting Super Admin approval.
     * Accepts either (A) member_temp_id returned from verifyOtp(purpose=registration)
     * OR (B) inline otp parameter that will be verified on the temp row first.
     */
    public function registerV2(Request $request)
    {
        $validated = $request->validate([
            'member_temp_id'        => ['nullable', 'integer', 'exists:members,id'],
            'name'                  => ['required', 'string', 'max:255'],
            'phone'                 => ['required', 'string', 'digits:10'],
            'email'                 => ['nullable', 'email', 'max:255', 'unique:members,email'],
            'company_name'          => ['nullable', 'string', 'max:255'],
            'state'                 => ['required', 'string', 'max:100'],
            'city'                  => ['required', 'string', 'max:100'],
            'password'              => ['required', 'confirmed', Password::min(6)->mixedCase()->numbers()->symbols()],
            'terms_agreed'          => ['required', 'boolean', 'in:1'],
            'otp'                   => ['nullable', 'required_without:member_temp_id', 'string', 'max:10'],
        ], [
            'phone.digits'          => 'Mobile number must be exactly 10 digits.',
            'terms_agreed.in'       => 'You must agree to the Terms & Conditions and Privacy Policy.',
            'password.mixed_case'   => 'Password must contain uppercase and lowercase letters.',
            'password.numbers'      => 'Password must contain at least one number.',
            'password.symbols'      => 'Password must contain at least one special character.',
            'otp.required_without'  => 'OTP is required when member_temp_id is not provided.',
        ]);

        $query = Member::query()->where('phone', $validated['phone']);
        if (! empty($validated['member_temp_id'])) {
            $query->orWhere('id', (int) $validated['member_temp_id']);
        }
        $member = $query->first();

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Phone not registered for OTP flow. Please send OTP first.',
            ], 422);
        }

        if (empty($member->phone_verify_at) && empty($validated['otp'])) {
            return response()->json([
                'success' => false,
                'message' => 'Phone not verified. Please provide OTP or complete verify-otp step first.',
            ], 422);
        }

        if (empty($member->phone_verify_at) && ! empty($validated['otp'])) {
            if (! $member->otp || ! $member->otp_expire || $member->otp_expire->isPast() || (string) $member->otp !== (string) $validated['otp']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired OTP. Please verify your phone number first.',
                ], 422);
            }
            $member->forceFill([
                'otp'             => null,
                'otp_expire'      => null,
                'phone_verify_at' => now(),
            ])->save();
        }

        $usernameBase = 'user' . preg_replace('/\D+/', '', $validated['phone']);
        $username = $this->makeUnique('members', 'username', substr($usernameBase, 0, 20));

        $slugBase = 'member-' . preg_replace('/\D+/', '', $validated['phone']);
        $slug = $this->makeUnique('members', 'slug', Str::slug($slugBase));

        $member->update([
            'name'              => $validated['name'],
            'username'          => $username,
            'email'             => $validated['email'] ?? null,
            'phone'             => $validated['phone'],
            'company_name'      => $validated['company_name'] ?? null,
            'state'             => $validated['state'],
            'city'              => $validated['city'],
            'password'          => $validated['password'],
            'terms_agreed'      => true,
            'terms_agreed_at'   => now(),
            'status'            => 0,
            'approved_by'       => null,
            'approved_at'       => null,
            'roles'             => [],
            'slug'              => $slug,
        ]);

        return response()->json([
            'success'        => true,
            'message'        => 'Registration submitted successfully. Your account is awaiting Super Admin approval. You will be notified once approved.',
            'member_id'      => $member->id,
            'member_uuid'    => $member->uuid,
            'account_status' => 'pending_approval',
        ], 202);
    }

    /**
     * Login with identifier (email/phone/username) and password.
     * Distinguishes between INACTIVE (pending approval) and truly disabled accounts.
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'identifier'  => ['required', 'string'],
            'password'    => ['required', 'string'],
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

        if ((string) $member->status === '0' && empty($member->approved_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Your registration is pending Super Admin approval. You will be notified once your account is activated.',
                'account_status' => 'pending_approval',
                'can_login' => false,
            ], 403);
        }

        if ((int) ($member->status ?? 1) !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated. Please contact admin.',
                'account_status' => 'deactivated',
            ], 403);
        }

        if ($member->must_change_password && Hash::check($validated['password'], $member->password)) {
            $tokenName = $validated['device_name'] ?? ($request->userAgent() ?: 'api-token');
            $member->tokens()->where('name', $tokenName)->delete();
            $tempToken = $member->createToken($tokenName, ['password-change-only'])->plainTextToken;
            return response()->json([
                'success' => false,
                'message' => 'Password change required. Please set a new password before continuing.',
                'must_change_password' => true,
                'temp_token' => $tempToken,
                'token_type' => 'Bearer',
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
                'message' => 'No role assigned. Please contact admin.',
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
            'member' => $member,
            'account_status' => 'active',
        ]);
    }

    /**
     * Send OTP — supports 'login' (existing active member) or 'registration' (pre-creates temp row).
     */
    public function sendOtp(Request $request)
    {
        $validated = $request->validate([
            'phone'   => ['required', 'string', 'max:20'],
            'purpose' => ['nullable', 'string', 'in:login,registration'],
        ]);

        $purpose = $validated['purpose'] ?? 'login';
        $phone = preg_replace('/\D+/', '', $validated['phone']);

        $member = Member::query()->where('phone', $validated['phone'])->first();

        if ($purpose === 'registration') {
            if (! $member) {
                $slugBase = 'pending-' . $phone;
                $member = Member::create([
                    'uuid'       => (string) Str::uuid(),
                    'created_by' => SuperAdmin::query()->value('id') ?? null,
                    'name'       => 'Pending_' . substr($phone, -4),
                    'username'   => 'tmp' . $phone . Str::random(4),
                    'phone'      => $validated['phone'],
                    'password'   => Hash::make(Str::random(32)),
                    'status'     => 0,
                    'roles'      => [],
                    'slug'       => $this->makeUnique('members', 'slug', Str::slug($slugBase)),
                ]);
            }
        } elseif (! $member || ((int) ($member->status ?? 1) !== 1 && empty($member->approved_at) === false)) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found or inactive.',
            ], 404);
        }

        if ($purpose === 'login' && (! $member || (int) ($member->status ?? 1) !== 1)) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found or inactive.',
            ], 404);
        }

        $this->issueOtp($member);

        $payload = [
            'success' => true,
            'message' => 'OTP sent successfully.',
            'purpose' => $purpose,
        ];

        if (config('app.debug')) {
            $payload['otp'] = $member->otp;
            $payload['otp_expire_at'] = optional($member->otp_expire)->toISOString();
        }

        return response()->json($payload);
    }

    /**
     * Verify OTP — for login issues a token; for registration only marks phone_verify_at.
     */
    public function verifyOtp(Request $request)
    {
        $validated = $request->validate([
            'phone'       => ['required', 'string', 'max:20'],
            'otp'         => ['required', 'string', 'max:10'],
            'purpose'     => ['nullable', 'string', 'in:login,registration'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $purpose = $validated['purpose'] ?? 'login';

        $member = Member::query()->where('phone', $validated['phone'])->first();

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found.',
            ], 404);
        }

        if ($purpose === 'login' && (int) ($member->status ?? 1) !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Account is inactive.',
            ], 403);
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
            'otp'            => null,
            'otp_expire'     => null,
            'phone_verify_at' => now(),
        ])->save();

        if ($purpose === 'registration') {
            return response()->json([
                'success'         => true,
                'message'         => 'Phone verified successfully. Please complete registration.',
                'phone_verified'  => true,
                'member_temp_id'  => $member->id,
            ]);
        }

        if (empty($member->approved_at) && (string) $member->status === '0') {
            return response()->json([
                'success'        => false,
                'message'        => 'Your registration is pending Super Admin approval.',
                'account_status' => 'pending_approval',
                'phone_verified' => true,
            ], 403);
        }

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
            'success'    => true,
            'message'    => 'OTP verified successfully.',
            'token'      => $token,
            'token_type' => 'Bearer',
            'member'     => $member,
        ]);
    }

    /**
     * Get authenticated member profile
     */
    public function me(Request $request)
    {
        $member = $request->user();
        $member->loadMissing(['employee', 'fcm_token']);

        return response()->json([
            'success' => true,
            'member' => $member,
        ]);
    }

    /**
     * Logout - Revoke current token
     */
    public function logout(Request $request)
    {
        $member = $request->user();
        $token = $member?->currentAccessToken();

        if ($token) {
            $tokenName = $token->name;

            // Log activity before revoking token
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

    /**
     * Issue OTP to member (random 6-digit OTP, in debug mode returns 123456)
     */
    private function issueOtp(Member $member): void
    {
        $otp = config('app.debug') ? '123456' : (string) random_int(100000, 999999);
        $member->forceFill([
            'otp' => $otp,
            'otp_expire' => now()->addMinutes(5),
        ])->save();
    }

    /**
     * Make unique value for table column
     */
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
