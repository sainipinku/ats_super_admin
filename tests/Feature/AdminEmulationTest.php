<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Member;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminEmulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_emulate_company_admin_and_access_dashboard(): void
    {
        $company = Company::create(['name' => 'Test Company', 'status' => 'active']);

        $superAdmin = SuperAdmin::create([
            'name' => 'Company Super Admin',
            'email' => 'superadmin@company.com',
            'username' => 'superadmin_company',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'status' => 1,
        ]);

        $adminMember = Member::create([
            'name' => 'Company Admin User',
            'username' => 'admin_user',
            'email' => 'admin@company.com',
            'password' => Hash::make('password'),
            'created_by' => $superAdmin->id,
            'slug' => 'admin',
            'status' => 1,
            'roles' => [1],
        ]);

        // Start emulation
        $response = $this->actingAs($superAdmin, 'superadmin')
            ->post(route('emulate.start'), [
                'target_type' => 'admin',
                'target_id' => $adminMember->id,
            ]);

        $response->assertRedirect(route('admin.dashboard'));

        // Follow redirect to admin dashboard as emulated user
        $dashboardResponse = $this->get(route('admin.construction.dashboard'));
        $dashboardResponse->assertOk();
    }

    public function test_super_admin_project_owner_emulating_member_never_receives_permission_denied_error(): void
    {
        $superAdmin = SuperAdmin::create([
            'name' => 'Project Owner Super Admin',
            'email' => 'owner_admin@company.com',
            'username' => 'owner_admin',
            'password' => Hash::make('password'),
            'status' => 1,
        ]);

        $plainMember = Member::create([
            'name' => 'Plain Field Worker',
            'username' => 'field_worker',
            'email' => 'field_worker@company.com',
            'password' => Hash::make('password'),
            'created_by' => $superAdmin->id,
            'slug' => 'field-worker',
            'status' => 1,
            'roles' => [],
        ]);

        // Start emulation on plain member
        $response = $this->actingAs($superAdmin, 'superadmin')
            ->post(route('emulate.start'), [
                'target_type' => 'member',
                'target_id' => $plainMember->id,
            ]);

        $response->assertRedirect();

        // Access member construction dashboard protected by EnsureConstructionPermission
        $dashboardResponse = $this->get(route('member.construction.dashboard'));
        $dashboardResponse->assertOk();
        $this->assertFalse(str_contains((string) $dashboardResponse->getContent(), 'Access Denied'));
    }

    public function test_duplicate_phone_number_returns_validation_error_instead_of_500_sql_exception(): void
    {
        $superAdmin = SuperAdmin::create([
            'name' => 'Owner Admin',
            'email' => 'owner@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
        ]);

        Member::create([
            'name' => 'Existing Member',
            'email' => 'existing@example.com',
            'username' => 'existing_user',
            'phone' => '9887646564',
            'password' => Hash::make('password'),
            'created_by' => $superAdmin->id,
            'slug' => 'existing-user',
            'status' => 1,
        ]);

        $response = $this->actingAs($superAdmin, 'superadmin')
            ->post(route('super.super_admins.store_admin'), [
                'name' => 'New Admin',
                'email' => 'newadmin@example.com',
                'phone' => '9887646564', // Same duplicate phone number
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role_slug' => 'admin',
            ]);

        $response->assertSessionHasErrors(['phone']);
    }

    public function test_phone_number_must_be_exactly_10_digits_when_creating_admin(): void
    {
        $superAdmin = SuperAdmin::create([
            'name' => 'Owner Admin',
            'email' => 'owner2@example.com',
            'password' => Hash::make('password'),
            'status' => 1,
        ]);

        // Test with fewer than 10 digits (e.g., 4 digits '7878')
        $responseShort = $this->actingAs($superAdmin, 'superadmin')
            ->post(route('super.super_admins.store_admin'), [
                'name' => 'Admin Short Phone',
                'email' => 'shortphone@example.com',
                'phone' => '7878',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role_slug' => 'admin',
            ]);

        $responseShort->assertSessionHasErrors(['phone']);

        // Test with more than 10 digits (e.g., 12 digits)
        $responseLong = $this->actingAs($superAdmin, 'superadmin')
            ->post(route('super.super_admins.store_admin'), [
                'name' => 'Admin Long Phone',
                'email' => 'longphone@example.com',
                'phone' => '987654321012',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role_slug' => 'admin',
            ]);

        $responseLong->assertSessionHasErrors(['phone']);

        // Test with valid 10 digits
        $responseValid = $this->actingAs($superAdmin, 'superadmin')
            ->post(route('super.super_admins.store_admin'), [
                'name' => 'Admin Valid Phone',
                'email' => 'validphone@example.com',
                'phone' => '9876543210',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role_slug' => 'admin',
            ]);

        $responseValid->assertSessionHasNoErrors();
    }
}
