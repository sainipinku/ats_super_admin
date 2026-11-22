<?php

namespace Tests\Feature\Construction;

use App\Models\Company;
use App\Models\Project;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectOwnerActionsVisibilityFeatureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_superadmin_project_owner_can_update_project(): void
    {
        $superAdmin = SuperAdmin::first();
        if (!$superAdmin) {
            $superAdmin = SuperAdmin::create([
                'name' => 'Project Owner Test',
                'email' => 'superadmin_po_' . Str::random(5) . '@example.com',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]);
        }

        $company = Company::create(['name' => 'PO Co ' . Str::random(5), 'status' => 'active']);
        $project = Project::create([
            'company_id' => $company->id,
            'project_code' => 'PRJ-PO-' . Str::upper(Str::random(4)),
            'name' => 'Original Project Name',
            'slug' => 'prj-po-' . Str::lower(Str::random(4)),
            'status' => 'active',
        ]);

        $response = $this->actingAs($superAdmin, 'superadmin')
            ->from(route('super.construction.projects.show', $project->id))
            ->put(route('super.construction.projects.update', $project->id), [
                'company_id' => $company->id,
                'project_code' => $project->project_code,
                'name' => 'Updated Project Name by Owner',
                'priority' => 'medium',
                'status' => 'active',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('super.construction.projects.show', $project->id));

        $this->assertDatabaseHas('construction_projects', [
            'id' => $project->id,
            'name' => 'Updated Project Name by Owner',
        ]);
    }

    public function test_superadmin_project_owner_can_delete_project(): void
    {
        $superAdmin = SuperAdmin::first();
        if (!$superAdmin) {
            $superAdmin = SuperAdmin::create([
                'name' => 'Project Owner Test',
                'email' => 'superadmin_po2_' . Str::random(5) . '@example.com',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]);
        }

        $company = Company::create(['name' => 'Del Co ' . Str::random(5), 'status' => 'active']);
        $project = Project::create([
            'company_id' => $company->id,
            'project_code' => 'PRJ-DEL-' . Str::upper(Str::random(4)),
            'name' => 'Project to Delete',
            'slug' => 'prj-del-' . Str::lower(Str::random(4)),
            'status' => 'active',
        ]);

        $response = $this->actingAs($superAdmin, 'superadmin')
            ->delete(route('super.construction.projects.destroy', $project->id));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('super.construction.projects.index'));

        $this->assertSoftDeleted('construction_projects', [
            'id' => $project->id,
        ]);
    }
}
