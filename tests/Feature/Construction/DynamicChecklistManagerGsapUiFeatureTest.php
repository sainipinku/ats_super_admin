<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class DynamicChecklistManagerGsapUiFeatureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_super_admin_and_admin_project_show_pages_mount_dynamic_checklist_manager(): void
    {
        $superShow = file_get_contents(resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx'));
        $adminShow = file_get_contents(resource_path('js/Pages/Admin/Construction/Projects/Show.jsx'));

        $this->assertStringContainsString('import DynamicChecklistManager from "@/Pages/Construction/Components/DynamicChecklistManager";', $superShow);
        $this->assertStringContainsString('<DynamicChecklistManager', $superShow);

        $this->assertStringContainsString('import DynamicChecklistManager from "@/Pages/Construction/Components/DynamicChecklistManager";', $adminShow);
        $this->assertStringContainsString('<DynamicChecklistManager', $adminShow);
    }

    public function test_project_show_endpoint_renders_with_checklist_relations_for_dynamic_checklist_manager(): void
    {
        $company = Company::create([
            'name' => 'Checklist UI Co ' . Str::random(5),
            'status' => 'active',
        ]);
        $superAdmin = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'roles' => json_encode(['super_admin']),
            'name' => 'Super Admin Checklist UI',
            'email' => 'sa-chk-' . Str::lower(Str::random(6)) . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'whatsapp_phone' => (string) random_int(7000000000, 9999999999),
            'password' => 'password',
        ]);
        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-' . Str::upper(Str::random(6)),
            'name' => 'Checklist Client',
            'status' => 'active',
        ]);
        $project = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => 'PRJ-' . Str::upper(Str::random(6)),
            'name' => 'Checklist GSAP Project',
            'slug' => 'checklist-gsap-' . Str::lower(Str::random(6)),
            'status' => 'active',
        ]);

        $response = $this->actingAs($superAdmin, 'superadmin')
            ->get(route('super.construction.projects.show', $project->id));

        $response->assertStatus(200);
        $response->assertSee('SuperAdmin\/Construction\/Projects\/Show', false);
    }

    public function test_dynamic_checklist_manager_preserves_all_crud_and_grouping_controls(): void
    {
        $source = file_get_contents(resource_path('js/Pages/Construction/Components/DynamicChecklistManager.jsx'));

        $this->assertStringContainsString('Dynamic Checklist Manager', $source);
        $this->assertStringContainsString('Group by Task', $source);
        $this->assertStringContainsString('Group by Day', $source);
        $this->assertStringContainsString('Add Checklist Item', $source);
        $this->assertStringContainsString('.checklists.store', $source);
        $this->assertStringContainsString('.checklists.update', $source);
        $this->assertStringContainsString('.checklists.toggle', $source);
        $this->assertStringContainsString('.checklists.destroy', $source);
        $this->assertStringContainsString('prefers-reduced-motion', $source);
    }
}
