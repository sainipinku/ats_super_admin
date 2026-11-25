<?php

namespace Tests\Unit\Construction;

use App\Models\ConstructionRole;
use App\Models\Member;
use App\Models\MemberRoleAssignment;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectMemberRoleBadgesUnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_set_roles_attribute_prefers_database_role_id_over_hardcoded_legacy_ids(): void
    {
        $customSurveyorRole = new Role();
        $customSurveyorRole->forceFill([
            'id' => 14,
            'name' => 'Surveyor',
            'slug' => 'surveyor',
            'status' => 1,
            'is_default' => 1,
            'created_by' => 1,
        ])->save();

        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Surveyor Custom ID',
            'username' => 'surv_custom_' . Str::lower(Str::random(5)),
            'slug' => 'surv-custom-' . Str::lower(Str::random(5)),
            'email' => 'surv_custom_' . Str::lower(Str::random(5)) . '@example.com',
            'phone' => '9876543210',
            'created_by' => 1,
            'status' => Member::STATUS_ACTIVE,
            'roles' => ['surveyor'],
            'password' => 'password',
        ]);

        $rawRoles = json_decode((string) $member->getRawOriginal('roles'), true);
        $this->assertSame([14], $rawRoles);
    }

    public function test_member_get_roles_attribute_drops_stale_non_admin_raw_roles_when_assignment_history_exists(): void
    {
        $memberRole = new Role();
        $memberRole->forceFill(['id' => 1, 'name' => 'Member', 'slug' => 'member', 'status' => 1, 'is_default' => 1, 'created_by' => 1])->save();

        $surveyorRole = new Role();
        $surveyorRole->forceFill(['id' => 4, 'name' => 'Surveyor', 'slug' => 'surveyor', 'status' => 1, 'is_default' => 1, 'created_by' => 1])->save();

        $driverRole = new Role();
        $driverRole->forceFill(['id' => 6, 'name' => 'Vehicle Driver', 'slug' => 'vehicle_driver', 'status' => 1, 'is_default' => 1, 'created_by' => 1])->save();

        $adminRole = new Role();
        $adminRole->forceFill(['id' => 9, 'name' => 'Admin', 'slug' => 'admin', 'status' => 1, 'is_default' => 1, 'created_by' => 1])->save();

        $cSurveyor = ConstructionRole::create(['name' => 'Surveyor', 'slug' => 'surveyor', 'status' => 'active', 'is_system_role' => true]);
        $cDriver = ConstructionRole::create(['name' => 'Vehicle Driver', 'slug' => 'vehicle_driver', 'status' => 'active', 'is_system_role' => true]);

        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Priyanka Saini',
            'username' => 'priyanka_' . Str::lower(Str::random(5)),
            'slug' => 'priyanka-' . Str::lower(Str::random(5)),
            'email' => 'priynka_' . Str::lower(Str::random(5)) . '@example.com',
            'phone' => '9876543211',
            'created_by' => 1,
            'status' => Member::STATUS_ACTIVE,
            'roles' => [9, 4, 6],
            'password' => 'password',
        ]);

        // Only Surveyor is active; Driver was deactivated (status = 0), but stale `6` lingered in raw `roles`
        MemberRoleAssignment::create(['member_id' => $member->id, 'role_id' => $cSurveyor->id, 'status' => 1]);
        MemberRoleAssignment::create(['member_id' => $member->id, 'role_id' => $cDriver->id, 'status' => 0]);

        $fresh = $member->fresh();

        $this->assertContains(4, $fresh->roles);
        $this->assertContains(9, $fresh->roles);
        $this->assertNotContains(6, $fresh->roles, 'Stale deactivated driver role ID must not survive when MemberRoleAssignment history exists');
        $this->assertFalse($fresh->isDriver());
    }

    public function test_member_get_role_badges_attribute_only_resolves_system_roles_for_surveyor(): void
    {
        $surveyorRole = new Role();
        $surveyorRole->forceFill(['id' => 4, 'name' => 'Surveyor', 'slug' => 'surveyor', 'status' => 1, 'is_default' => 1, 'created_by' => 1])->save();

        $cProjectAdmin = new ConstructionRole();
        $cProjectAdmin->forceFill(['id' => 1, 'name' => 'Project Admin', 'slug' => 'project_admin', 'status' => 'active'])->save();
        $cSurveyor = new ConstructionRole();
        $cSurveyor->forceFill(['id' => 2, 'name' => 'Surveyor', 'slug' => 'surveyor', 'status' => 'active'])->save();
        $cDraftPerson = new ConstructionRole();
        $cDraftPerson->forceFill(['id' => 3, 'name' => 'Draft Person', 'slug' => 'draft_person', 'status' => 'active'])->save();
        $cDriver = new ConstructionRole();
        $cDriver->forceFill(['id' => 4, 'name' => 'Vehicle Driver', 'slug' => 'vehicle_driver', 'status' => 'active'])->save();

        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Priyanka Badges',
            'username' => 'priyanka_b_' . Str::lower(Str::random(5)),
            'slug' => 'priyanka-b-' . Str::lower(Str::random(5)),
            'email' => 'priynka_b_' . Str::lower(Str::random(5)) . '@example.com',
            'phone' => '9876543212',
            'created_by' => 1,
            'status' => Member::STATUS_ACTIVE,
            'roles' => [4],
            'password' => 'password',
        ]);

        MemberRoleAssignment::create(['member_id' => $member->id, 'role_id' => $cSurveyor->id, 'status' => 1]);

        $badges = $member->fresh()->role_badges;
        $this->assertNotContains('Vehicle Driver', $badges);
        $this->assertNotContains('Driver', $badges);
    }

    public function test_surveyor_to_draftsman_assignment_service_does_not_treat_project_admin_role_id_3_as_draft_person(): void
    {
        $adminRole = new Role();
        $adminRole->forceFill(['id' => 3, 'name' => 'Project Admin', 'slug' => 'project_admin', 'status' => 1, 'is_default' => 1, 'created_by' => 1])->save();

        $draftCRole = new ConstructionRole();
        $draftCRole->forceFill(['id' => 3, 'name' => 'Draft Person', 'slug' => 'draft_person', 'status' => 'active'])->save();

        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin Only',
            'username' => 'admin_only_' . Str::lower(Str::random(5)),
            'slug' => 'admin-only-' . Str::lower(Str::random(5)),
            'email' => 'admin_only_' . Str::lower(Str::random(5)) . '@example.com',
            'phone' => '9876543299',
            'created_by' => 1,
            'status' => Member::STATUS_ACTIVE,
            'roles' => [3],
            'designation' => ['Manager'],
            'password' => 'password',
        ]);

        $service = app(\App\Services\Construction\SurveyorToDraftsmanAssignmentService::class);
        $method = new \ReflectionMethod($service, 'memberHasDraftsmanRole');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($service, $member));
    }

    public function test_show_component_dynamically_matches_roles_without_designation_fallback(): void
    {
        $showCode = file_get_contents(resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx'));
        $this->assertIsString($showCode);

        $start = strpos($showCode, 'const sortedMembers = useMemo');
        $end = strpos($showCode, 'const metrics = useMemo', (int) $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $block = substr($showCode, (int) $start, (int) ($end - $start));

        $this->assertStringNotContainsString('m.designation_text', $block, 'Team member role filtering must not match designation_text dynamically');
        $this->assertStringNotContainsString('let targetKeyword = ""', $block, 'Team member role filtering must not use static hardcoded keyword switches');
    }
}
