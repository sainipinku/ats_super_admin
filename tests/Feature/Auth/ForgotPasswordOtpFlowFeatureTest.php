<?php

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\Member;
use App\Models\SuperAdmin;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ForgotPasswordOtpFlowFeatureTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createActiveMember(): Member
    {
        $suffix = Str::lower(Str::random(6));
        $company = Company::create([
            'name' => "Auth OTP Co {$suffix}",
            'status' => 'active',
        ]);

        $superAdmin = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'name' => "SA {$suffix}",
            'email' => "sa-otp-{$suffix}@example.com",
            'phone' => (string) random_int(7000000000, 9999999999),
            'whatsapp_phone' => (string) random_int(7000000000, 9999999999),
            'password' => Hash::make('password123'),
            'status' => 1,
        ]);

        return Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $superAdmin->id,
            'company_name' => $company->name,
            'name' => "OTP Member {$suffix}",
            'username' => "otp_user_{$suffix}",
            'slug' => "otp-user-{$suffix}",
            'email' => "member-otp-{$suffix}@example.com",
            'phone' => (string) random_int(7000000000, 9999999999),
            'password' => Hash::make('OldPassword@123'),
            'status' => Member::STATUS_ACTIVE,
            'roles' => [1],
        ]);
    }

    public function test_members_table_has_reset_otp_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('members', 'reset_otp'));
        $this->assertTrue(Schema::hasColumn('members', 'reset_otp_expires_at'));
        $this->assertTrue(Schema::hasColumn('members', 'reset_otp_verified_at'));
    }

    public function test_forgot_password_resend_verify_and_reset_password_end_to_end_flow(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-26 12:00:00'));
        $member = $this->createActiveMember();

        // 1. Hit forgot-password POST -> stores static reset_otp = 123456 with +2 min expiry
        $forgotRes = $this->postJson('/api/auth/forgot-password', [
            'identifier' => $member->email,
        ]);

        $forgotRes->assertOk()
            ->assertJson([
                'success' => true,
                'expires_in_seconds' => 120,
            ]);

        $member->refresh();
        $this->assertSame('123456', $member->reset_otp);
        $this->assertNotNull($member->reset_otp_expires_at);
        $this->assertSame('2026-11-26 12:02:00', $member->reset_otp_expires_at->format('Y-m-d H:i:s'));
        $this->assertNull($member->reset_otp_verified_at);

        // 2. Hit resend OTP API after 90 seconds -> refreshes static 123456 and resets +2 min expiry
        Carbon::setTestNow(Carbon::parse('2026-11-26 12:01:30'));
        $resendRes = $this->postJson('/api/auth/forgot-password/resend-otp', [
            'identifier' => $member->phone,
        ]);

        $resendRes->assertOk()
            ->assertJson([
                'success' => true,
                'expires_in_seconds' => 120,
            ]);

        $member->refresh();
        $this->assertSame('123456', $member->reset_otp);
        $this->assertSame('2026-11-26 12:03:30', $member->reset_otp_expires_at->format('Y-m-d H:i:s'));

        // 3. Cannot reset password BEFORE verify-otp is completed
        $prematureReset = $this->postJson('/api/auth/reset-password', [
            'identifier' => $member->email,
            'new_password' => 'NewPassword@456',
            'new_password_confirmation' => 'NewPassword@456',
        ]);
        $prematureReset->assertStatus(422);

        // 4. Wrong OTP fails verification
        $wrongOtpRes = $this->postJson('/api/auth/forgot-password/verify-otp', [
            'identifier' => $member->email,
            'otp' => '999999',
        ]);
        $wrongOtpRes->assertStatus(422)
            ->assertJson(['success' => false]);

        // 5. Valid static OTP 123456 verifies cleanly within 2 minutes and issues reset_token
        $verifyRes = $this->postJson('/api/auth/forgot-password/verify-otp', [
            'identifier' => $member->email,
            'otp' => '123456',
        ]);
        $verifyRes->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure(['reset_token', 'expires_in_seconds']);

        $resetToken = $verifyRes->json('reset_token');
        $member->refresh();
        $this->assertNull($member->reset_otp);
        $this->assertNotNull($member->reset_otp_verified_at);

        // 6. Mismatched confirm password fails validation
        $mismatchRes = $this->postJson('/api/auth/reset-password', [
            'identifier' => $member->email,
            'reset_token' => $resetToken,
            'new_password' => 'NewPassword@456',
            'new_password_confirmation' => 'Different@999',
        ]);
        $mismatchRes->assertStatus(422)
            ->assertJsonValidationErrors('new_password');

        // 7. Reset password with matching new_password & new_password_confirmation succeeds and updates password
        $resetRes = $this->postJson('/api/auth/reset-password', [
            'identifier' => $member->email,
            'reset_token' => $resetToken,
            'new_password' => 'NewPassword@456',
            'new_password_confirmation' => 'NewPassword@456',
        ]);
        $resetRes->assertOk()
            ->assertJson(['success' => true]);

        $member->refresh();
        $this->assertTrue(Hash::check('NewPassword@456', $member->password));
        $this->assertFalse(Hash::check('OldPassword@123', $member->password));
        $this->assertNull($member->reset_otp);
        $this->assertNull($member->reset_otp_verified_at);
        $this->assertNull($member->reset_password_token);

        // Member can immediately log in with the new password
        $loginRes = $this->postJson('/api/auth/login', [
            'identifier' => $member->email,
            'password' => 'NewPassword@456',
        ]);
        $loginRes->assertOk()->assertJson(['success' => true]);
    }

    public function test_verify_otp_rejects_expired_reset_otp_after_two_minutes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-26 14:00:00'));
        $member = $this->createActiveMember();

        $this->postJson('/api/auth/forgot-password', [
            'identifier' => $member->email,
        ])->assertOk();

        // Advance time past the 2-minute expiration window
        Carbon::setTestNow(Carbon::parse('2026-11-26 14:02:01'));

        $expiredRes = $this->postJson('/api/auth/forgot-password/verify-otp', [
            'identifier' => $member->email,
            'otp' => '123456',
        ]);
        $expiredRes->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_api_json_documents_forgot_password_otp_endpoints(): void
    {
        $filePath = base_path('api.json');
        $this->assertFileExists($filePath);

        $data = json_decode((string) file_get_contents($filePath), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('/auth/forgot-password', $data['paths'] ?? []);
        $this->assertArrayHasKey('/auth/forgot-password/resend-otp', $data['paths'] ?? []);
        $this->assertArrayHasKey('/auth/forgot-password/verify-otp', $data['paths'] ?? []);
        $this->assertArrayHasKey('/auth/reset-password', $data['paths'] ?? []);

        $this->assertNotEmpty($data['paths']['/auth/forgot-password']['post']['requestBody'] ?? null);
        $this->assertNotEmpty($data['paths']['/auth/forgot-password/resend-otp']['post']['requestBody'] ?? null);
        $this->assertNotEmpty($data['paths']['/auth/forgot-password/verify-otp']['post']['requestBody'] ?? null);
        $this->assertNotEmpty($data['paths']['/auth/reset-password']['post']['requestBody'] ?? null);
    }
}
