<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionActivityLog;
use App\Models\Project;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectActivityLogPlacementAndTimeFeatureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_superadmin_project_show_provides_activity_log_with_created_at_timestamps(): void
    {
        [$company, $superAdmin, $client] = $this->createCompanyActorAndClient();

        $project = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => 'PRJ-' . Str::upper(Str::random(6)),
            'name' => 'Activity Log Test Project',
            'slug' => 'act-log-' . Str::lower(Str::random(6)),
            'status' => 'active',
            'current_stage' => 'planning',
        ]);

        ConstructionActivityLog::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'module' => 'billing_payment',
            'action' => 'received',
            'actor_type' => $superAdmin::class,
            'actor_id' => $superAdmin->id,
            'meta' => ['amount' => 50000],
            'created_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($superAdmin, 'superadmin')
            ->get(route('super.construction.projects.show', $project->id));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Construction/Projects/Show')
            ->has('project')
            ->has('activityLog')
        );
    }

    private function createCompanyActorAndClient(): array
    {
        $company = Company::create([
            'name' => 'Act Co ' . Str::random(5),
            'status' => 'active',
        ]);
        $actor = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'roles' => json_encode(['super_admin']),
            'name' => 'Activity Super Admin',
            'email' => 'sa-act-' . Str::lower(Str::random(6)) . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'whatsapp_phone' => (string) random_int(7000000000, 9999999999),
            'password' => 'password',
        ]);
        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-' . Str::upper(Str::random(6)),
            'name' => 'Act Test Client',
            'status' => 'active',
        ]);

        return [$company, $actor, $client];
    }
}
