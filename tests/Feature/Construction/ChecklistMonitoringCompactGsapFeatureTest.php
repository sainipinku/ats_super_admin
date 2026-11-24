<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use App\Models\SuperAdmin;
use App\Models\TaskChecklist;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChecklistMonitoringCompactGsapFeatureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_projects_index_page_delivers_checklist_monitoring_data(): void
    {
        [$company, $superAdmin, $client] = $this->createCompanyActorAndClient();

        $project = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => 'PRJ-' . Str::upper(Str::random(6)),
            'name' => 'Compact GSAP Test Project',
            'slug' => 'compact-gsap-' . Str::lower(Str::random(6)),
            'status' => 'active',
            'current_stage' => 'planning',
        ]);

        $response = $this->actingAs($superAdmin, 'superadmin')
            ->get(route('super.construction.projects.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Construction/Projects/Index')
            ->has('projects')
            ->has('companies')
            ->has('clients')
        );
    }

    private function createCompanyActorAndClient(): array
    {
        $company = Company::create([
            'name' => 'Gsap Co ' . Str::random(5),
            'status' => 'active',
        ]);
        $actor = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'roles' => json_encode(['super_admin']),
            'name' => 'Gsap Super Admin',
            'email' => 'sa-gsap-' . Str::lower(Str::random(6)) . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'whatsapp_phone' => (string) random_int(7000000000, 9999999999),
            'password' => 'password',
        ]);
        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-' . Str::upper(Str::random(6)),
            'name' => 'Gsap Test Client',
            'status' => 'active',
        ]);

        return [$company, $actor, $client];
    }
}
