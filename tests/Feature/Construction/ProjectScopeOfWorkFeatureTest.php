<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectScopeOfWorkFeatureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_creating_project_persists_scope_of_work_and_displays_it_on_project_show(): void
    {
        [$company, $superAdmin, $client] = $this->createCompanyActorAndClient();

        $scopeText = "Complete topographic boundary survey, elevation mapping, and CAD layout submission.";

        $storeResponse = $this->actingAs($superAdmin, 'superadmin')
            ->post(route('super.construction.projects.store'), [
                'company_id' => $company->id,
                'client_id' => $client->id,
                'name' => 'Scope Of Work New Project ' . Str::random(4),
                'priority' => 'high',
                'status' => 'active',
                'current_stage' => 'planning',
                'scope_of_work' => $scopeText,
            ]);

        $storeResponse->assertRedirect();

        $project = Project::where('company_id', $company->id)->latest('id')->first();
        $this->assertNotNull($project);
        $this->assertSame($scopeText, $project->scope_of_work);

        $showResponse = $this->actingAs($superAdmin, 'superadmin')
            ->get(route('super.construction.projects.show', $project->id));

        $showResponse->assertStatus(200);
        $showResponse->assertSee('Complete topographic boundary survey', false);
    }

    public function test_superadmin_can_update_project_scope_of_work_from_modal_endpoint(): void
    {
        [$company, $superAdmin, $client] = $this->createCompanyActorAndClient();

        $project = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => 'PRJ-' . Str::upper(Str::random(6)),
            'name' => 'Editable Scope Project',
            'slug' => 'editable-scope-' . Str::lower(Str::random(6)),
            'status' => 'active',
            'scope_of_work' => 'Initial draft scope.',
        ]);

        $updatedScope = "Phase 1: Total station survey.\nPhase 2: AutoCAD 2D/3D deliverables & final sign-off.";

        $response = $this->actingAs($superAdmin, 'superadmin')
            ->from(route('super.construction.projects.show', $project->id))
            ->patch(route('super.construction.projects.scope-of-work.update', $project->id), [
                'scope_of_work' => $updatedScope,
            ]);

        $response->assertRedirect(route('super.construction.projects.show', $project->id));
        $response->assertSessionHasNoErrors();
        $this->assertSame($updatedScope, $project->fresh()->scope_of_work);
        $this->assertDatabaseHas('construction_activity_logs', [
            'project_id' => $project->id,
            'module' => 'project',
            'action' => 'updated_scope_of_work',
        ]);
    }

    public function test_company_superadmin_cannot_update_other_company_project_scope_of_work(): void
    {
        [$companyA, $actorA, $clientA] = $this->createCompanyActorAndClient();
        [$companyB, $actorB] = $this->createCompanyActorAndClient();

        $projectA = Project::create([
            'company_id' => $companyA->id,
            'client_id' => $clientA->id,
            'project_code' => 'PRJ-' . Str::upper(Str::random(6)),
            'name' => 'Company A Protected Scope Project',
            'slug' => 'protected-scope-' . Str::lower(Str::random(6)),
            'status' => 'active',
            'scope_of_work' => 'Original Company A Scope',
        ]);

        $response = $this->actingAs($actorB, 'superadmin')
            ->patch(route('super.construction.projects.scope-of-work.update', $projectA->id), [
                'scope_of_work' => 'Tampered Scope',
            ]);

        $response->assertStatus(403);
        $this->assertSame('Original Company A Scope', $projectA->fresh()->scope_of_work);
    }

    public function test_scope_of_work_update_validates_max_length(): void
    {
        [$company, $superAdmin, $client] = $this->createCompanyActorAndClient();

        $project = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => 'PRJ-' . Str::upper(Str::random(6)),
            'name' => 'Length Validation Project',
            'slug' => 'len-val-' . Str::lower(Str::random(6)),
            'status' => 'active',
            'scope_of_work' => 'Valid scope',
        ]);

        $response = $this->actingAs($superAdmin, 'superadmin')
            ->from(route('super.construction.projects.show', $project->id))
            ->patch(route('super.construction.projects.scope-of-work.update', $project->id), [
                'scope_of_work' => str_repeat('A', 5050),
            ]);

        $response->assertSessionHasErrors('scope_of_work');
        $this->assertSame('Valid scope', $project->fresh()->scope_of_work);
    }

    private function createCompanyActorAndClient(): array
    {
        $company = Company::create([
            'name' => 'Scope Co ' . Str::random(5),
            'status' => 'active',
        ]);
        $actor = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'roles' => json_encode(['super_admin']),
            'name' => 'Company Super Admin',
            'email' => 'sa-scope-' . Str::lower(Str::random(6)) . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'whatsapp_phone' => (string) random_int(7000000000, 9999999999),
            'password' => 'password',
        ]);
        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-' . Str::upper(Str::random(6)),
            'name' => 'Scope Test Client',
            'status' => 'active',
        ]);

        return [$company, $actor, $client];
    }
}
