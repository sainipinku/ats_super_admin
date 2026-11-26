<?php

namespace Tests\Feature\Construction;

use App\Models\AttendanceRecord;
use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\ClientPayment;
use App\Models\Company;
use App\Models\DailyProgressReport;
use App\Models\DraftingJob;
use App\Models\ExecutionPlan;
use App\Models\ExecutionTask;
use App\Models\Material;
use App\Models\Member;
use App\Models\Project;
use App\Models\ProjectHandover;
use App\Models\ProjectHandoverItem;
use App\Models\PurchaseRequest;
use App\Models\SuperAdmin;
use App\Models\SurveyPlan;
use App\Models\SurveySubmission;
use App\Models\SurveyVisit;
use App\Models\Vendor;
use App\Support\Construction\SurveyStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class MultiAccountOwnerScopingFeatureTest extends TestCase
{
    use DatabaseTransactions;

    private SuperAdmin $rootOwner;
    private SuperAdmin $companyOwnerB;
    private Company $companyA;
    private Company $companyB;
    private Client $clientA;
    private Client $clientB;
    private Project $projectA;
    private Project $projectB;

    protected function setUp(): void
    {
        parent::setUp();

        $root = SuperAdmin::find(1);
        if (!$root) {
            $root = new SuperAdmin();
            $root->forceFill([
                'id' => 1,
                'uuid' => (string) Str::uuid(),
                'name' => 'Root Super Admin',
                'email' => 'root_' . Str::lower(Str::random(6)) . '@example.com',
                'password' => bcrypt('password123'),
                'status' => 1,
                'roles' => ['admin'],
            ])->save();
        }
        $this->rootOwner = $root;
        $this->rootOwner->company_id = null;
        $this->rootOwner->save();

        $this->companyA = Company::create([
            'name' => 'Betaxtech Scoping ' . Str::random(4),
            'status' => 'active',
            'created_by_type' => SuperAdmin::class,
            'created_by_id' => $this->rootOwner->id,
        ]);

        $this->companyOwnerB = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Other Project Owner',
            'email' => 'owner_b_' . Str::lower(Str::random(6)) . '@example.com',
            'password' => bcrypt('password123'),
            'status' => 1,
            'roles' => ['project_owner'],
        ]);

        $this->companyB = Company::create([
            'name' => 'Second Owner Co ' . Str::random(4),
            'status' => 'active',
            'created_by_type' => SuperAdmin::class,
            'created_by_id' => $this->companyOwnerB->id,
        ]);

        $this->companyOwnerB->update(['company_id' => $this->companyB->id]);

        $this->clientA = Client::create([
            'company_id' => $this->companyA->id,
            'client_code' => 'CLI-A-' . Str::upper(Str::random(4)),
            'client_type' => 'individual',
            'name' => 'Abhishek Account A',
            'status' => 'active',
            'created_by_type' => SuperAdmin::class,
            'created_by_id' => $this->rootOwner->id,
        ]);

        $this->clientB = Client::create([
            'company_id' => $this->companyB->id,
            'client_code' => 'CLI-B-' . Str::upper(Str::random(4)),
            'client_type' => 'individual',
            'name' => 'Vikas Account B',
            'status' => 'active',
            'created_by_type' => SuperAdmin::class,
            'created_by_id' => $this->companyOwnerB->id,
        ]);

        $this->projectA = Project::create([
            'company_id' => $this->companyA->id,
            'client_id' => $this->clientA->id,
            'project_code' => 'PRJ-A-' . Str::upper(Str::random(4)),
            'name' => 'Jaipur Metro Account A',
            'status' => 'active',
            'created_by_type' => SuperAdmin::class,
            'created_by_id' => $this->rootOwner->id,
        ]);

        $this->projectB = Project::create([
            'company_id' => $this->companyB->id,
            'client_id' => $this->clientB->id,
            'project_code' => 'PRJ-B-' . Str::upper(Str::random(4)),
            'name' => 'Chomu Plot Account B',
            'status' => 'active',
            'created_by_type' => SuperAdmin::class,
            'created_by_id' => $this->companyOwnerB->id,
        ]);
    }

    public function test_billing_directory_invoices_payments_and_csv_scope_to_logged_in_owner_account(): void
    {
        $invoiceA = ClientInvoice::create([
            'project_id' => $this->projectA->id,
            'invoice_code' => 'INV-A-' . Str::upper(Str::random(4)),
            'invoice_date' => '2026-11-10',
            'due_date' => '2026-11-25',
            'tax_type' => 'intra',
            'status' => 'draft',
            'subtotal_amount' => 10000,
            'total_tax_amount' => 1800,
            'total_amount' => 11800,
            'paid_amount' => 0,
            'balance_amount' => 11800,
            'created_by_type' => SuperAdmin::class,
            'created_by_id' => $this->rootOwner->id,
        ]);

        $invoiceB = ClientInvoice::create([
            'project_id' => $this->projectB->id,
            'invoice_code' => 'INV-B-' . Str::upper(Str::random(4)),
            'invoice_date' => '2026-11-10',
            'due_date' => '2026-11-25',
            'tax_type' => 'intra',
            'status' => 'draft',
            'subtotal_amount' => 25000,
            'total_tax_amount' => 4500,
            'total_amount' => 29500,
            'paid_amount' => 0,
            'balance_amount' => 29500,
            'created_by_type' => SuperAdmin::class,
            'created_by_id' => $this->companyOwnerB->id,
        ]);

        $paymentA = ClientPayment::create([
            'project_id' => $this->projectA->id,
            'invoice_id' => $invoiceA->id,
            'payment_code' => 'PAY-A-' . Str::upper(Str::random(4)),
            'received_at' => now(),
            'amount' => 1000,
            'method' => 'bank_transfer',
            'received_by_type' => SuperAdmin::class,
            'received_by_id' => $this->rootOwner->id,
        ]);

        $paymentB = ClientPayment::create([
            'project_id' => $this->projectB->id,
            'invoice_id' => $invoiceB->id,
            'payment_code' => 'PAY-B-' . Str::upper(Str::random(4)),
            'received_at' => now(),
            'amount' => 2000,
            'method' => 'bank_transfer',
            'received_by_type' => SuperAdmin::class,
            'received_by_id' => $this->companyOwnerB->id,
        ]);

        // Root owner only sees Account A billing records
        $rootIndex = $this->actingAs($this->rootOwner, 'superadmin')
            ->get(route('super.construction.billing.index'));
        $rootIndex->assertOk();
        $rootProps = $rootIndex->viewData('page')['props'];

        $this->assertContains($this->projectA->id, collect($rootProps['projects'])->pluck('id')->all());
        $this->assertNotContains($this->projectB->id, collect($rootProps['projects'])->pluck('id')->all());
        $this->assertNotContains($this->projectB->id, collect($rootProps['raw_projects'])->pluck('id')->all());
        $this->assertContains($invoiceA->id, collect($rootProps['invoices'])->pluck('id')->all());
        $this->assertNotContains($invoiceB->id, collect($rootProps['invoices'])->pluck('id')->all());
        $this->assertContains($paymentA->id, collect($rootProps['payments'])->pluck('id')->all());
        $this->assertNotContains($paymentB->id, collect($rootProps['payments'])->pluck('id')->all());

        // Company Owner B only sees Account B billing records
        $bIndex = $this->actingAs($this->companyOwnerB, 'superadmin')
            ->get(route('super.construction.billing.index'));
        $bIndex->assertOk();
        $bProps = $bIndex->viewData('page')['props'];

        $this->assertContains($this->projectB->id, collect($bProps['projects'])->pluck('id')->all());
        $this->assertNotContains($this->projectA->id, collect($bProps['projects'])->pluck('id')->all());
        $this->assertContains($invoiceB->id, collect($bProps['invoices'])->pluck('id')->all());
        $this->assertNotContains($invoiceA->id, collect($bProps['invoices'])->pluck('id')->all());

        // CSV Export strictly scoped
        $csvResponse = $this->actingAs($this->rootOwner, 'superadmin')
            ->get(route('super.construction.billing.export-csv'));
        $csvResponse->assertOk();
        ob_start();
        $csvResponse->sendContent();
        $csv = (string) ob_get_clean();
        $this->assertStringContainsString($this->projectA->project_code, $csv);
        $this->assertStringNotContainsString($this->projectB->project_code, $csv);

        // Cross-account billing mutations blocked
        $this->actingAs($this->rootOwner, 'superadmin')
            ->post(route('super.construction.billing.invoices.store'), [
                'project_id' => $this->projectB->id,
                'invoice_date' => '2026-11-15',
                'tax_type' => 'intra',
                'items' => [['description' => 'Work', 'quantity' => 1, 'rate' => 500]],
            ])->assertStatus(403);

        $this->actingAs($this->companyOwnerB, 'superadmin')
            ->put(route('super.construction.billing.invoices.update', $invoiceA->id), [
                'invoice_date' => '2026-11-16',
                'tax_type' => 'intra',
                'items' => [['description' => 'Tampered', 'quantity' => 1, 'rate' => 500]],
            ])->assertStatus(403);

        $this->actingAs($this->rootOwner, 'superadmin')
            ->post(route('super.construction.billing.payments.store'), [
                'project_id' => $this->projectB->id,
                'invoice_id' => $invoiceB->id,
                'amount' => 500,
                'received_at' => '2026-11-15',
                'method' => 'cash',
            ])->assertStatus(403);

        // Even on own projectB, recording a payment against Account A's invoice must be blocked
        $this->actingAs($this->companyOwnerB, 'superadmin')
            ->post(route('super.construction.billing.payments.store'), [
                'project_id' => $this->projectB->id,
                'invoice_id' => $invoiceA->id,
                'amount' => 500,
                'received_at' => '2026-11-15',
                'method' => 'cash',
            ])->assertStatus(403);
    }

    public function test_handover_module_scopes_index_and_blocks_cross_account_mutations(): void
    {
        $handoverA = ProjectHandover::create([
            'project_id' => $this->projectA->id,
            'handover_code' => 'HO-A-' . Str::upper(Str::random(4)),
            'status' => 'draft',
            'created_by_type' => SuperAdmin::class,
            'created_by_id' => $this->rootOwner->id,
        ]);
        $handoverB = ProjectHandover::create([
            'project_id' => $this->projectB->id,
            'handover_code' => 'HO-B-' . Str::upper(Str::random(4)),
            'status' => 'draft',
            'created_by_type' => SuperAdmin::class,
            'created_by_id' => $this->companyOwnerB->id,
        ]);
        $itemB = ProjectHandoverItem::create([
            'handover_id' => $handoverB->id,
            'title' => 'As-built drawing',
            'status' => 'pending',
        ]);

        $resA = $this->actingAs($this->rootOwner, 'superadmin')
            ->get(route('super.construction.handover.index'));
        $resA->assertOk();
        $propsA = $resA->viewData('page')['props'];
        $this->assertContains($this->projectA->id, collect($propsA['projects'])->pluck('id')->all());
        $this->assertNotContains($this->projectB->id, collect($propsA['projects'])->pluck('id')->all());
        $this->assertContains($handoverA->id, collect($propsA['handovers'])->pluck('id')->all());
        $this->assertNotContains($handoverB->id, collect($propsA['handovers'])->pluck('id')->all());

        $this->actingAs($this->rootOwner, 'superadmin')
            ->post(route('super.construction.handover.store'), [
                'project_id' => $this->projectB->id,
                'items' => [['title' => 'Keys']],
            ])->assertStatus(403);

        $this->actingAs($this->rootOwner, 'superadmin')
            ->post(route('super.construction.handover.items.update', $itemB->id), [
                'status' => 'completed',
            ])->assertStatus(403);

        $this->actingAs($this->rootOwner, 'superadmin')
            ->post(route('super.construction.handover.complete', $handoverB->id), [
                'client_signatory_name' => 'John',
            ])->assertStatus(403);

        $this->actingAs($this->rootOwner, 'superadmin')
            ->post(route('super.construction.handover.close', $handoverB->id), [])
            ->assertStatus(403);
    }

    public function test_execution_module_scopes_index_stats_and_blocks_cross_account_mutations(): void
    {
        $memberA = Member::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Exec Worker A',
            'username' => 'exec_a_' . Str::lower(Str::random(6)),
            'slug' => 'exec-a-' . Str::lower(Str::random(6)),
            'email' => 'exec_a_' . Str::lower(Str::random(6)) . '@example.com',
            'password' => bcrypt('password123'),
            'created_by' => $this->rootOwner->id,
            'status' => Member::STATUS_ACTIVE,
        ]);

        $memberB = Member::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Exec Worker B',
            'username' => 'exec_b_' . Str::lower(Str::random(6)),
            'slug' => 'exec-b-' . Str::lower(Str::random(6)),
            'email' => 'exec_b_' . Str::lower(Str::random(6)) . '@example.com',
            'password' => bcrypt('password123'),
            'created_by' => $this->companyOwnerB->id,
            'status' => Member::STATUS_ACTIVE,
        ]);

        $planA = ExecutionPlan::create([
            'project_id' => $this->projectA->id,
            'plan_code' => 'EP-A-' . Str::upper(Str::random(4)),
            'title' => 'Plan Alpha',
            'status' => 'active',
        ]);
        $planB = ExecutionPlan::create([
            'project_id' => $this->projectB->id,
            'plan_code' => 'EP-B-' . Str::upper(Str::random(4)),
            'title' => 'Plan Beta',
            'status' => 'active',
        ]);
        $taskB = ExecutionTask::create([
            'project_id' => $this->projectB->id,
            'execution_plan_id' => $planB->id,
            'task_code' => 'ET-B-' . Str::upper(Str::random(4)),
            'title' => 'Task Beta',
            'priority' => 'medium',
            'status' => 'planned',
        ]);
        $dprB = DailyProgressReport::create([
            'project_id' => $this->projectB->id,
            'execution_task_id' => $taskB->id,
            'report_date' => '2026-11-10',
            'submitted_by_member_id' => $memberB->id,
            'submitted_at' => now(),
            'status' => 'submitted',
        ]);
        $attendanceB = AttendanceRecord::create([
            'project_id' => $this->projectB->id,
            'member_id' => $memberB->id,
            'attendance_date' => '2026-11-10',
            'status' => 'pending',
        ]);

        $resA = $this->actingAs($this->rootOwner, 'superadmin')
            ->get(route('super.construction.execution.index'));
        $resA->assertOk();
        $propsA = $resA->viewData('page')['props'];

        $this->assertContains($this->projectA->id, collect($propsA['projects'])->pluck('id')->all());
        $this->assertNotContains($this->projectB->id, collect($propsA['projects'])->pluck('id')->all());
        $this->assertContains($planA->id, collect($propsA['executionPlans'])->pluck('id')->all());
        $this->assertNotContains($planB->id, collect($propsA['executionPlans'])->pluck('id')->all());
        $this->assertNotContains($taskB->id, collect($propsA['executionTasks'])->pluck('id')->all());
        $this->assertNotContains($dprB->id, collect($propsA['dailyProgressReports'])->pluck('id')->all());
        $this->assertNotContains($attendanceB->id, collect($propsA['attendanceRecords'])->pluck('id')->all());
        $this->assertContains($memberA->id, collect($propsA['members'])->pluck('id')->all());
        $this->assertNotContains($memberB->id, collect($propsA['members'])->pluck('id')->all());

        $this->actingAs($this->rootOwner, 'superadmin')
            ->post(route('super.construction.execution.plans.store'), [
                'project_id' => $this->projectB->id,
                'title' => 'Hijack Plan',
                'status' => 'planned',
            ])->assertStatus(403);

        $this->actingAs($this->rootOwner, 'superadmin')
            ->put(route('super.construction.execution.plans.update', $planB->id), [
                'title' => 'Updated Beta Plan',
            ])->assertStatus(403);

        $this->actingAs($this->rootOwner, 'superadmin')
            ->post(route('super.construction.execution.reports.review', $dprB->id), [
                'status' => 'approved',
            ])->assertStatus(403);

        $this->actingAs($this->rootOwner, 'superadmin')
            ->post(route('super.construction.execution.attendance.review', $attendanceB->id), [
                'status' => 'approved',
            ])->assertStatus(403);
    }

    public function test_materials_module_scopes_index_and_blocks_cross_account_mutations(): void
    {
        $vendorA = Vendor::create([
            'project_id' => $this->projectA->id,
            'vendor_code' => 'VND-A-' . Str::upper(Str::random(4)),
            'name' => 'Vendor Alpha',
            'status' => 'active',
        ]);
        $vendorB = Vendor::create([
            'project_id' => $this->projectB->id,
            'vendor_code' => 'VND-B-' . Str::upper(Str::random(4)),
            'name' => 'Vendor Beta',
            'status' => 'active',
        ]);
        $materialB = Material::create([
            'project_id' => $this->projectB->id,
            'material_code' => 'MAT-B-' . Str::upper(Str::random(4)),
            'name' => 'Cement Beta',
            'unit' => 'Bags',
            'default_rate' => 400,
            'status' => 'active',
        ]);
        $prB = PurchaseRequest::create([
            'project_id' => $this->projectB->id,
            'request_code' => 'PR-B-' . Str::upper(Str::random(4)),
            'request_date' => '2026-11-10',
            'status' => 'submitted',
        ]);

        $resA = $this->actingAs($this->rootOwner, 'superadmin')
            ->get(route('super.construction.materials.index'));
        $resA->assertOk();
        $propsA = $resA->viewData('page')['props'];

        $this->assertContains($this->projectA->id, collect($propsA['projects'])->pluck('id')->all());
        $this->assertNotContains($this->projectB->id, collect($propsA['projects'])->pluck('id')->all());
        $this->assertContains($vendorA->id, collect($propsA['vendors'])->pluck('id')->all());
        $this->assertNotContains($vendorB->id, collect($propsA['vendors'])->pluck('id')->all());
        $this->assertNotContains($materialB->id, collect($propsA['materials'])->pluck('id')->all());
        $this->assertNotContains($prB->id, collect($propsA['purchaseRequests'])->pluck('id')->all());

        $this->actingAs($this->rootOwner, 'superadmin')
            ->post(route('super.construction.materials.vendors.store'), [
                'project_id' => $this->projectB->id,
                'name' => 'Unauthorized Vendor',
            ])->assertStatus(403);

        $this->actingAs($this->rootOwner, 'superadmin')
            ->post(route('super.construction.materials.purchase_requests.review', $prB->id), [
                'status' => 'approved',
            ])->assertStatus(403);
    }

    public function test_survey_and_drafting_modules_scope_to_logged_in_owner_account(): void
    {
        $memberB = Member::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Surveyor B',
            'username' => 'surv_b_' . Str::lower(Str::random(6)),
            'slug' => 'surv-b-' . Str::lower(Str::random(6)),
            'email' => 'surv_b_' . Str::lower(Str::random(6)) . '@example.com',
            'password' => bcrypt('password123'),
            'created_by' => $this->companyOwnerB->id,
            'status' => Member::STATUS_ACTIVE,
        ]);

        $planA = SurveyPlan::create([
            'project_id' => $this->projectA->id,
            'survey_code' => 'SUR-A-' . Str::upper(Str::random(4)),
            'title' => 'Survey Alpha',
            'status' => SurveyPlan::STATUS_PLANNED,
        ]);
        $planB = SurveyPlan::create([
            'project_id' => $this->projectB->id,
            'survey_code' => 'SUR-B-' . Str::upper(Str::random(4)),
            'title' => 'Survey Beta',
            'status' => SurveyPlan::STATUS_PLANNED,
        ]);
        $visitB = SurveyVisit::create([
            'project_id' => $this->projectB->id,
            'survey_plan_id' => $planB->id,
            'checked_in_by_member_id' => $memberB->id,
            'checked_in_at' => now(),
            'status' => SurveyStatus::SUBMITTED,
        ]);
        $submissionB = SurveySubmission::create([
            'project_id' => $this->projectB->id,
            'survey_visit_id' => $visitB->id,
            'submitted_by_member_id' => $memberB->id,
            'submitted_at' => now(),
            'status' => SurveySubmission::STATUS_APPROVED,
        ]);
        $draftingB = DraftingJob::create([
            'project_id' => $this->projectB->id,
            'survey_submission_id' => $submissionB->id,
            'status' => 'queued',
        ]);

        $surveyRes = $this->actingAs($this->rootOwner, 'superadmin')
            ->get(route('super.construction.survey.index'));
        $surveyRes->assertOk();
        $surveyProps = $surveyRes->viewData('page')['props'];
        $this->assertContains($this->projectA->id, collect($surveyProps['projects'])->pluck('id')->all());
        $this->assertNotContains($this->projectB->id, collect($surveyProps['projects'])->pluck('id')->all());
        $this->assertContains($planA->id, collect($surveyProps['surveyPlans'])->pluck('id')->all());
        $this->assertNotContains($planB->id, collect($surveyProps['surveyPlans'])->pluck('id')->all());

        $draftingRes = $this->actingAs($this->rootOwner, 'superadmin')
            ->get(route('super.construction.drafting.index'));
        $draftingRes->assertOk();
        $draftingProps = $draftingRes->viewData('page')['props'];
        $this->assertNotContains($draftingB->id, collect($draftingProps['draftingJobs'])->pluck('id')->all());
        $this->assertNotContains($submissionB->id, collect($draftingProps['approvedSurveySubmissions'])->pluck('id')->all());

        $this->actingAs($this->rootOwner, 'superadmin')
            ->post(route('super.construction.drafting.jobs.store'), [
                'survey_submission_id' => $submissionB->id,
            ])->assertStatus(403);
    }

    public function test_companies_clients_projects_dashboard_and_api_scope_to_logged_in_owner_account(): void
    {
        // Companies index
        $companiesRes = $this->actingAs($this->rootOwner, 'superadmin')
            ->get(route('super.construction.companies.index'));
        $companiesRes->assertOk();
        $companiesProps = $companiesRes->viewData('page')['props'];
        $this->assertContains($this->companyA->id, collect($companiesProps['companies'])->pluck('id')->all());
        $this->assertNotContains($this->companyB->id, collect($companiesProps['companies'])->pluck('id')->all());

        // Clients index
        $clientsRes = $this->actingAs($this->rootOwner, 'superadmin')
            ->get(route('super.construction.clients.index'));
        $clientsRes->assertOk();
        $clientsProps = $clientsRes->viewData('page')['props'];
        $this->assertContains($this->clientA->id, collect($clientsProps['clients'])->pluck('id')->all());
        $this->assertNotContains($this->clientB->id, collect($clientsProps['clients'])->pluck('id')->all());

        // Projects index
        $projectsRes = $this->actingAs($this->rootOwner, 'superadmin')
            ->get(route('super.construction.projects.index'));
        $projectsRes->assertOk();
        $projectsProps = $projectsRes->viewData('page')['props'];
        $this->assertContains($this->projectA->id, collect($projectsProps['projects'])->pluck('id')->all());
        $this->assertNotContains($this->projectB->id, collect($projectsProps['projects'])->pluck('id')->all());
        $this->assertNotContains($this->companyB->id, collect($projectsProps['companies'])->pluck('id')->all());

        // Dashboard index (Root Owner must NOT see Company B stats/projects)
        $dashboardRes = $this->actingAs($this->rootOwner, 'superadmin')
            ->get(route('super.construction.dashboard'));
        $dashboardRes->assertOk();
        $dashboardProps = $dashboardRes->viewData('page')['props'];
        $this->assertContains($this->projectA->id, collect($dashboardProps['recentProjects'])->pluck('id')->all());
        $this->assertNotContains($this->projectB->id, collect($dashboardProps['recentProjects'])->pluck('id')->all());
        $this->assertNotContains($this->companyB->id, collect($dashboardProps['companies'])->pluck('id')->all());

        // API Construction Projects & Stats
        $apiList = $this->actingAs($this->rootOwner, 'sanctum')
            ->getJson('/api/construction/projects');
        $apiList->assertOk();
        $apiProjectIds = collect($apiList->json('data'))->pluck('id')->all();
        $this->assertContains($this->projectA->id, $apiProjectIds);
        $this->assertNotContains($this->projectB->id, $apiProjectIds);

        $this->actingAs($this->rootOwner, 'sanctum')
            ->getJson("/api/construction/projects/{$this->projectB->id}")
            ->assertStatus(403);

        $this->actingAs($this->companyOwnerB, 'sanctum')
            ->getJson("/api/construction/projects/{$this->projectA->id}")
            ->assertStatus(403);

        $statsB = $this->actingAs($this->companyOwnerB, 'sanctum')
            ->getJson('/api/construction/projects/stats');
        $statsB->assertOk();
        $this->assertEquals(1, $statsB->json('data.total'));
    }
}
