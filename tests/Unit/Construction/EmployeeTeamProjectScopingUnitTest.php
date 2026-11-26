<?php

namespace Tests\Unit\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Tests\TestCase;

class EmployeeTeamProjectScopingUnitTest extends TestCase
{
    public function test_resolves_construction_actor_does_not_bypass_admin_check_via_ambient_admin_guard(): void
    {
        $traitCode = file_get_contents(app_path('Http/Controllers/Concerns/ResolvesConstructionActor.php'));

        $this->assertMatchesRegularExpression(
            '/function\s+isCompanyAdminActor\s*\(/',
            $traitCode
        );

        // Extract isCompanyAdminActor and isFullAdminActor bodies to verify no raw Auth::guard('admin')->check() return true bypass
        preg_match('/function\s+isCompanyAdminActor[\s\S]*?^\s{4}\}/m', $traitCode, $companyMatch);
        preg_match('/function\s+isFullAdminActor[\s\S]*?^\s{4}\}/m', $traitCode, $fullMatch);

        $companyBody = $companyMatch[0] ?? '';
        $fullBody = $fullMatch[0] ?? '';

        $this->assertNotEmpty($companyBody);
        $this->assertNotEmpty($fullBody);

        $this->assertStringNotContainsString(
            "if (Auth::guard('admin')->check()) {\n            return true;\n        }",
            $companyBody,
            'isCompanyAdminActor must inspect the actor roles instead of blindly trusting Auth::guard(\'admin\')->check()'
        );

        $this->assertStringNotContainsString(
            "if (Auth::guard('admin')->check()) {\n            return true;\n        }",
            $fullBody,
            'isFullAdminActor must inspect the actor roles instead of blindly trusting Auth::guard(\'admin\')->check()'
        );
    }

    public function test_admin_dashboard_controller_intersects_projects_with_accessible_project_ids(): void
    {
        $code = file_get_contents(app_path('Http/Controllers/Admin/Construction/DashboardController.php'));

        $this->assertStringContainsString(
            '$this->getAccessibleProjectIds($actor)',
            $code,
            'Admin DashboardController must always resolve getAccessibleProjectIds($actor)'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/\$projectsQuery\s*=\s*Project::query\(\)\s*;\s*if\s*\(\s*\$effectiveCompanyId\s*\)\s*\{\s*\$projectsQuery->where\(\s*[\'"]company_id[\'"]\s*,\s*\$effectiveCompanyId\s*\)\s*;\s*\}/s',
            $code,
            'Admin DashboardController must not bypass getAccessibleProjectIds whenever effectiveCompanyId is present'
        );
    }
}
