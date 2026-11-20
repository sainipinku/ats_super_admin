<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChecklistMonitoringDashboardFeatureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_projects_index_renders_checklist_monitoring_dashboard_with_deep_links(): void
    {
        [$company, $superAdmin, $client] = $this->createCompanyActorAndClient();

        $project = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => 'PRJ-' . Str::upper(Str::random(6)),
            'name' => 'Checklist Monitored Project',
            'slug' => 'chk-mon-' . Str::lower(Str::random(6)),
            'status' => 'active',
            'current_stage' => 'planning',
        ]);

        $response = $this->actingAs($superAdmin, 'superadmin')
            ->get(route('super.construction.projects.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Construction/Projects/Index')
            ->has('projects')
        );
    }

    public function test_project_show_page_loads_cleanly_with_checklist_focus_parameter(): void
    {
        [$company, $superAdmin, $client] = $this->createCompanyActorAndClient();

        $project = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => 'PRJ-' . Str::upper(Str::random(6)),
            'name' => 'Checklist Focus Project',
            'slug' => 'chk-foc-' . Str::lower(Str::random(6)),
            'status' => 'active',
            'current_stage' => 'planning',
        ]);

        $response = $this->actingAs($superAdmin, 'superadmin')
            ->get(route('super.construction.projects.show', ['project' => $project->id, 'focus' => 'checklist']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Construction/Projects/Show')
            ->has('project')
        );
    }

    private function createCompanyActorAndClient(): array
    {
        $company = Company::create([
            'name' => 'Mon Co ' . Str::random(5),
            'status' => 'active',
        ]);
        $actor = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'roles' => json_encode(['super_admin']),
            'name' => 'Company Super Admin',
            'email' => 'sa-mon-' . Str::lower(Str::random(6)) . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'whatsapp_phone' => (string) random_int(7000000000, 9999999999),
            'password' => 'password',
        ]);
        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-' . Str::upper(Str::random(6)),
            'name' => 'Mon Test Client',
            'status' => 'active',
        ]);

        return [$company, $actor, $client];
    }
}
