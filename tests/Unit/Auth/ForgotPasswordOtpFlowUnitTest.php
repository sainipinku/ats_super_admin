<?php

namespace Tests\Unit\Auth;

use App\Models\Member;
use Tests\TestCase;

class ForgotPasswordOtpFlowUnitTest extends TestCase
{
    public function test_member_model_exposes_reset_otp_fields_and_hides_sensitive_otp_columns(): void
    {
        $member = new Member();

        $fillable = $member->getFillable();
        $casts = $member->getCasts();
        $hidden = $member->getHidden();

        $this->assertContains('reset_otp', $fillable);
        $this->assertContains('reset_otp_expires_at', $fillable);
        $this->assertContains('reset_otp_verified_at', $fillable);

        $this->assertSame('datetime', $casts['reset_otp_expires_at'] ?? null);
        $this->assertSame('datetime', $casts['reset_otp_verified_at'] ?? null);

        $this->assertContains('reset_otp', $hidden);
        $this->assertContains('otp', $hidden);
        $this->assertContains('reset_password_token', $hidden);
    }

    public function test_api_routes_and_controller_define_forgot_resend_verify_and_reset_endpoints(): void
    {
        $routesCode = file_get_contents(base_path('routes/api.php'));
        $controllerCode = file_get_contents(app_path('Http/Controllers/Api/AuthController.php'));

        $this->assertStringContainsString("'/forgot-password'", $routesCode);
        $this->assertStringContainsString("'/forgot-password/resend-otp'", $routesCode);
        $this->assertStringContainsString("'/forgot-password/verify-otp'", $routesCode);
        $this->assertStringContainsString("'/reset-password'", $routesCode);

        $this->assertStringContainsString('function forgotPassword', $controllerCode);
        $this->assertStringContainsString('function resendResetOtp', $controllerCode);
        $this->assertStringContainsString('function verifyResetOtp', $controllerCode);
        $this->assertStringContainsString('function resetPassword', $controllerCode);
        $this->assertStringContainsString('addMinutes(2)', $controllerCode);
        $this->assertStringContainsString("'123456'", $controllerCode);
    }
}
