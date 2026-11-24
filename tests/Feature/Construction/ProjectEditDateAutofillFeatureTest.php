<?php

namespace Tests\Feature\Construction;

use App\Models\Company;
use App\Models\Project;
use App\Models\SuperAdmin;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectEditDateAutofillFeatureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_project_update_persists_start_and_expected_end_dates_cleanly(): void
    {
        $superAdmin = SuperAdmin::first();
        if (!$superAdmin) {
            $superAdmin = SuperAdmin::create([
                'name' => 'Date Test Admin',
                'email' => 'date_admin_' . Str::random(5) . '@example.com',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]);
        }

        $company = Company::create(['name' => 'Date Co ' . Str::random(5), 'status' => 'active']);
        $project = Project::create([
            'company_id' => $company->id,
            'project_code' => 'PRJ-DATE-' . Str::upper(Str::random(4)),
            'name' => 'Date Autofill Project',
            'slug' => 'prj-date-' . Str::lower(Str::random(4)),
            'status' => 'active',
            'priority' => 'medium',
            'start_date' => Carbon::parse('2026-11-10'),
            'expected_end_date' => Carbon::parse('2026-11-25'),
        ]);

        $response = $this->actingAs($superAdmin, 'superadmin')
            ->from(route('super.construction.projects.index'))
            ->put(route('super.construction.projects.update', $project->id), [
                'company_id' => $company->id,
                'project_code' => $project->project_code,
                'name' => 'Date Autofill Project Updated',
                'priority' => 'high',
                'start_date' => '2026-11-16',
                'expected_end_date' => '2026-11-30',
                'status' => 'active',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('construction_projects', [
            'id' => $project->id,
            'start_date' => '2026-11-16 00:00:00',
            'expected_end_date' => '2026-11-30 00:00:00',
        ]);
    }
}
