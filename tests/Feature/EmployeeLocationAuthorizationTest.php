<?php

namespace Tests\Feature;

use Tests\TestCase;

class EmployeeLocationAuthorizationTest extends TestCase
{
    public function test_employee_location_reads_require_a_super_admin_session(): void
    {
        $this->getJson('/api/admin/employee-locations')->assertUnauthorized();
        $this->getJson('/api/admin/employees/EMP-000001/location-history')->assertUnauthorized();
    }

    public function test_employee_location_reports_require_an_authenticated_member(): void
    {
        $this->postJson('/api/member/location', [
            'latitude' => 26.9124,
            'longitude' => 75.7873,
        ])->assertUnauthorized();
    }
}
