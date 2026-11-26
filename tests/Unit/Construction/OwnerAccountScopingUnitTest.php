<?php

namespace Tests\Unit\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Models\Client;
use App\Models\Company;
use App\Models\Member;
use App\Models\Project;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OwnerAccountScopingUnitTest extends TestCase
{
    use DatabaseTransactions;

    private object $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new class {
            use ResolvesConstructionActor;

            public function projectIds($actor): Collection
            {
                return $this->getAccessibleProjectIds($actor);
            }

            public function companyIds($actor): Collection
            {
                return $this->getOwnedCompanyIds($actor);
            }

            public function clientIds($actor): Collection
            {
                return $this->getAccessibleClientIds($actor);
            }

            public function checkProject(int|Project $project, $actor): Project
            {
                return $this->ensureProjectAccess($project, $actor);
            }
        };
    }

    private function createSuperAdmin(array $attributes = []): SuperAdmin
    {
        return SuperAdmin::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => 'Owner ' . Str::random(5),
            'email' => 'sa_' . Str::lower(Str::random(8)) . '@example.com',
            'password' => 'password123',
            'status' => 1,
        ], $attributes));
    }

    private function createCompany(string $prefix, ?SuperAdmin $creator = null): Company
    {
        return Company::create([
            'name' => $prefix . ' ' . Str::random(5),
            'status' => 'active',
            'created_by_type' => $creator ? SuperAdmin::class : null,
            'created_by_id' => $creator?->id,
        ]);
    }

    public function test_root_project_owner_does_not_see_other_superadmin_company_or_creator_projects(): void
    {
        $rootOwner = SuperAdmin::find(1) ?? $this->createSuperAdmin([
            'name' => 'Root Project Owner',
            'email' => 'root_' . Str::lower(Str::random(6)) . '@example.com',
        ]);
        $rootOwner->company_id = null;
        $rootOwner->save();

        $rootCompany = $this->createCompany('Root Co', $rootOwner);
        $otherCompany = $this->createCompany('Other Co');

        $otherSuperAdmin = $this->createSuperAdmin([
            'company_id' => $otherCompany->id,
            'roles' => ['super_admin'],
        ]);
        $otherCompany->update([
            'created_by_type' => SuperAdmin::class,
            'created_by_id' => $otherSuperAdmin->id,
        ]);

        $projectOwnerRoleAccount = $this->createSuperAdmin([
            'company_id' => $otherCompany->id,
            'roles' => ['project_owner'],
        ]);

        $rootMember = Member::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Root Admin Member',
            'username' => 'root_mem_' . Str::lower(Str::random(6)),
            'slug' => 'root-mem-' . Str::lower(Str::random(6)),
            'email' => 'root_mem_' . Str::lower(Str::random(6)) . '@example.com',
            'password' => 'password123',
            'created_by' => $rootOwner->id,
            'status' => Member::STATUS_ACTIVE,
        ]);

        $otherMember = Member::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Admin Member',
            'username' => 'other_mem_' . Str::lower(Str::random(6)),
            'slug' => 'other-mem-' . Str::lower(Str::random(6)),
            'email' => 'other_mem_' . Str::lower(Str::random(6)) . '@example.com',
            'password' => 'password123',
            'created_by' => $otherSuperAdmin->id,
            'status' => Member::STATUS_ACTIVE,
        ]);

        $rootDirectProject = Project::create([
            'company_id' => $rootCompany->id,
            'name' => 'Root Direct Project',
            'status' => 'active',
            'created_by_type' => SuperAdmin::class,
            'created_by_id' => $rootOwner->id,
        ]);

        $rootMemberProject = Project::create([
            'company_id' => $rootCompany->id,
            'name' => 'Root Member Project',
            'status' => 'active',
            'created_by_type' => Member::class,
            'created_by_id' => $rootMember->id,
        ]);

        $otherCompanyProject = Project::create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Company Project',
            'status' => 'active',
            'created_by_type' => SuperAdmin::class,
            'created_by_id' => $otherSuperAdmin->id,
        ]);

        $otherMemberProject = Project::create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Member Project',
            'status' => 'active',
            'created_by_type' => Member::class,
            'created_by_id' => $otherMember->id,
        ]);

        $rootAccessible = $this->resolver->projectIds($rootOwner);
        $this->assertTrue($rootAccessible->contains($rootDirectProject->id));
        $this->assertTrue($rootAccessible->contains($rootMemberProject->id));
        $this->assertFalse($rootAccessible->contains($otherCompanyProject->id));
        $this->assertFalse($rootAccessible->contains($otherMemberProject->id));

        // Even a SuperAdmin with roles=['project_owner'] and a company_id must only see their own company projects
        $projectOwnerRoleAccessible = $this->resolver->projectIds($projectOwnerRoleAccount);
        $this->assertTrue($projectOwnerRoleAccessible->contains($otherCompanyProject->id));
        $this->assertTrue($projectOwnerRoleAccessible->contains($otherMemberProject->id));
        $this->assertFalse($projectOwnerRoleAccessible->contains($rootDirectProject->id));
        $this->assertFalse($projectOwnerRoleAccessible->contains($rootMemberProject->id));
    }

    public function test_owned_company_ids_and_client_ids_isolate_superadmin_accounts(): void
    {
        $ownerA = $this->createSuperAdmin();
        $companyA = $this->createCompany('Account A Co', $ownerA);
        $ownerA->company_id = $companyA->id;
        $ownerA->save();

        $ownerB = $this->createSuperAdmin(['roles' => ['project_owner']]);
        $companyB = $this->createCompany('Account B Co', $ownerB);
        $ownerB->company_id = $companyB->id;
        $ownerB->save();

        $clientA = Client::create([
            'company_id' => $companyA->id,
            'name' => 'Client Alpha',
            'client_type' => 'company',
            'status' => 'active',
            'created_by_type' => SuperAdmin::class,
            'created_by_id' => $ownerA->id,
        ]);

        $clientB = Client::create([
            'company_id' => $companyB->id,
            'name' => 'Client Beta',
            'client_type' => 'company',
            'status' => 'active',
            'created_by_type' => SuperAdmin::class,
            'created_by_id' => $ownerB->id,
        ]);

        $companiesA = $this->resolver->companyIds($ownerA);
        $companiesB = $this->resolver->companyIds($ownerB);

        $this->assertTrue($companiesA->contains($companyA->id));
        $this->assertFalse($companiesA->contains($companyB->id));
        $this->assertTrue($companiesB->contains($companyB->id));
        $this->assertFalse($companiesB->contains($companyA->id));

        $clientsA = $this->resolver->clientIds($ownerA);
        $clientsB = $this->resolver->clientIds($ownerB);

        $this->assertTrue($clientsA->contains($clientA->id));
        $this->assertFalse($clientsA->contains($clientB->id));
        $this->assertTrue($clientsB->contains($clientB->id));
        $this->assertFalse($clientsB->contains($clientA->id));
    }

    public function test_ensure_project_access_blocks_cross_account_project(): void
    {
        $companyA = $this->createCompany('Alpha Co');
        $ownerA = $this->createSuperAdmin(['company_id' => $companyA->id]);

        $companyB = $this->createCompany('Beta Co');
        $ownerB = $this->createSuperAdmin(['company_id' => $companyB->id]);

        $projectA = Project::create([
            'company_id' => $companyA->id,
            'name' => 'Project A',
            'status' => 'active',
            'created_by_type' => SuperAdmin::class,
            'created_by_id' => $ownerA->id,
        ]);

        $projectB = Project::create([
            'company_id' => $companyB->id,
            'name' => 'Project B',
            'status' => 'active',
            'created_by_type' => SuperAdmin::class,
            'created_by_id' => $ownerB->id,
        ]);

        $resolved = $this->resolver->checkProject($projectA->id, $ownerA);
        $this->assertSame($projectA->id, $resolved->id);

        $this->expectException(HttpException::class);
        $this->resolver->checkProject($projectB->id, $ownerA);
    }
}
