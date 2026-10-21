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

    private function createActiveMember(string $name): \App\Models\Member
    {
        $superAdmin = \App\Models\SuperAdmin::firstOrCreate(
            ['email' => 'admin-test@example.com'],
            [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'name' => 'Super Admin',
                'phone' => '9999999999',
                'whatsapp_phone' => '9999999999',
                'password' => 'password',
            ]
        );

        $username = \Illuminate\Support\Str::slug($name) . '-' . \Illuminate\Support\Str::random(5);

        return \App\Models\Member::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'created_by' => $superAdmin->id,
            'name' => $name,
            'username' => $username,
            'slug' => $username,
            'email' => $username . '@example.com',
            'phone' => '9' . random_int(100000000, 999999999),
            'status' => \App\Models\Member::STATUS_ACTIVE,
            'password' => 'password',
        ]);
    }

    public function test_authenticated_member_can_report_location_every_20_minutes(): void
    {
        $member = $this->createActiveMember('Field Engineer');

        \Laravel\Sanctum\Sanctum::actingAs($member, ['*']);

        $response = $this->postJson('/api/member/location', [
            'latitude' => 26.9124,
            'longitude' => 75.7873,
            'accuracy' => 12.5,
            'captured_at' => now()->toIso8601String(),
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'member_id' => $member->id,
                    'latitude' => 26.9124,
                    'longitude' => 75.7873,
                    'accuracy' => 12.5,
                    'sync_interval_minutes' => 20,
                ],
            ]);

        $this->assertDatabaseHas('employee_locations', [
            'member_id' => $member->id,
            'latitude' => 26.9124,
            'longitude' => 75.7873,
        ]);

        $this->assertDatabaseHas('employees', [
            'member_id' => $member->id,
        ]);

        $member->refresh();
        $this->assertEquals(26.9124, (float) $member->latitude);
        $this->assertEquals(75.7873, (float) $member->longitude);
    }

    public function test_member_location_reporting_accepts_aliases(): void
    {
        $member = $this->createActiveMember('Alias Tester');

        \Laravel\Sanctum\Sanctum::actingAs($member, ['*']);

        $response = $this->postJson('/api/member/track-location', [
            'lat' => 28.6139,
            'lng' => 77.2090,
            'gps_accuracy_meters' => 8.2,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'latitude' => 28.6139,
                    'longitude' => 77.2090,
                ],
            ]);
    }

    public function test_member_location_reporting_accepts_batch_pings(): void
    {
        $member = $this->createActiveMember('Batch Sync Member');

        \Laravel\Sanctum\Sanctum::actingAs($member, ['*']);

        $response = $this->postJson('/api/member/locations', [
            'locations' => [
                [
                    'lat' => 26.9100,
                    'lng' => 75.7800,
                    'accuracy' => 10,
                    'captured_at' => now()->subMinutes(40)->toIso8601String(),
                ],
                [
                    'lat' => 26.9124,
                    'lng' => 75.7873,
                    'accuracy' => 12,
                    'captured_at' => now()->subMinutes(20)->toIso8601String(),
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'count' => 2,
                    'member_id' => $member->id,
                    'sync_interval_minutes' => 20,
                ],
            ]);
    }

    public function test_invalid_coordinates_return_validation_error(): void
    {
        $member = $this->createActiveMember('Invalid Coord Member');

        \Laravel\Sanctum\Sanctum::actingAs($member, ['*']);

        $response = $this->postJson('/api/member/location', [
            'latitude' => 150.0, // Invalid latitude (> 90)
            'longitude' => 75.7873,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }
}
