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

class AuthController extends Controller
{
    /**
     * Register a new member
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
     * Login with identifier (email/phone/username) and password
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

        // Member not found
        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 422);
        }

        // Check if account is active
        if ((int) ($member->status ?? 1) !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive. Please contact admin.',
            ], 403);
        }

        // Block calling team members from logging in via member API
        if ($member->is_calling_team) {
            return response()->json([
                'success' => false,
                'message' => 'Use the calling team portal to access your account.',
            ], 403);
        }

        // Check if member has at least one valid role assigned
        $roles = is_array($member->roles) ? $member->roles : [];
        if (empty($roles)) {
            return response()->json([
                'success' => false,
                'message' => 'No role assigned. Please contact admin.',
            ], 403);
        }
        // Verify password
        if (! Hash::check($validated['password'], $member->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 422);
        }

        // Revoke old tokens for this device to maintain security
        // Only keep the latest token per device
        $tokenName = $validated['device_name'] ?? ($request->userAgent() ?: 'api-token');
        $member->tokens()->where('name', $tokenName)->delete();

        // Create new Sanctum token
        $token = $member->createToken($tokenName)->plainTextToken;

        // Log activity
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
        ]);
    }

    /**
     * Send OTP to member's phone
     */
    public function sendOtp(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $member = Member::query()->where('phone', $validated['phone'])->first();

        if (! $member || (int) ($member->status ?? 1) !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found or inactive.',
            ], 404);
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
     * Verify OTP and get token
     */
    public function verifyOtp(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'otp' => ['required', 'string', 'max:10'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $member = Member::query()->where('phone', $validated['phone'])->first();

        if (! $member || (int) ($member->status ?? 1) !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found or inactive.',
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

        // Clear OTP and mark phone as verified
        $member->forceFill([
            'otp' => null,
            'otp_expire' => null,
            'phone_verify_at' => now(),
        ])->save();

        // Create token
        $tokenName = $validated['device_name'] ?? ($request->userAgent() ?: 'api-token');
        $token = $member->createToken($tokenName)->plainTextToken;

        // Log activity
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
            'member' => $member,
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