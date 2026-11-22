<?php

namespace App\Http\Controllers\Admin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\ClientInvoice;
use App\Models\ClientPayment;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\Member;
use App\Services\Construction\ConstructionBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    use ResolvesConstructionActor;

    public function index(ConstructionBillingService $billingService): Response
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        $projectIds = $this->getAccessibleProjectIds($actor);

        $projects = Project::whereIn('id', $projectIds)
            ->with([
                'client:id,client_code,name,company_id,contact_person,email,phone,alternate_phone,gst_number,billing_address,site_address',
                'company:id,name',
                'draftingJobs:id,project_id,khasra_number,site_area',
                'clientInvoices' => fn ($q) => $q->latest(),
            ])
            ->orderByDesc('id')
            ->get();

        $projectsDirectory = $billingService->getProjectsBillingDirectory($projects);

        return Inertia::render('Admin/Construction/Billing/Index', [
            'projects' => $projectsDirectory,
            'raw_projects' => $projects->map(fn ($p) => ['id' => $p->id, 'project_code' => $p->project_code, 'name' => $p->name])->all(),
            'invoices' => ClientInvoice::with(['project', 'items', 'payments'])
                ->whereIn('project_id', $projectIds)
                ->latest()
                ->take(60)
                ->get(),
            'payments' => ClientPayment::with(['project', 'invoice'])
                ->whereIn('project_id', $projectIds)
                ->latest()
                ->take(80)
                ->get(),
        ]);
    }

    public function exportCsv(Request $request, ConstructionBillingService $billingService): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        $projectIds = $this->getAccessibleProjectIds($actor);

        $startDate = $request->input('start_date') ?: $request->input('from_date');
        $endDate = $request->input('end_date') ?: $request->input('to_date');

        $query = Project::whereIn('id', $projectIds)
            ->with([
                'client:id,client_code,name,company_id,contact_person,email,phone,alternate_phone,gst_number,billing_address,site_address',
                'company:id,name',
                'draftingJobs:id,project_id,khasra_number,site_area',
                'clientInvoices' => fn ($q) => $q->latest(),
            ])
            ->orderByDesc('id');

        if ($startDate && $endDate) {
            $query->where(function ($q) use ($startDate, $endDate) {
                $q->where(function ($sq) use ($startDate, $endDate) {
                    $sq->whereNotNull('start_date')
                       ->whereBetween('start_date', [$startDate, $endDate]);
                })
                ->orWhere(function ($sq) use ($startDate, $endDate) {
                    $sq->whereNotNull('expected_end_date')
                       ->whereBetween('expected_end_date', [$startDate, $endDate]);
                })
                ->orWhere(function ($sq) use ($startDate, $endDate) {
                    $sq->whereNull('start_date')
                       ->whereNull('expected_end_date')
                       ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
                })
                ->orWhereHas('clientInvoices', fn ($iq) => $iq->whereBetween('invoice_date', [$startDate, $endDate]));
            });
        } elseif ($startDate) {
            $query->where(function ($q) use ($startDate) {
                $q->where(function ($sq) use ($startDate) {
                    $sq->whereNotNull('start_date')
                       ->where('start_date', '>=', $startDate);
                })
                ->orWhere(function ($sq) use ($startDate) {
                    $sq->whereNotNull('expected_end_date')
                       ->where('expected_end_date', '>=', $startDate);
                })
                ->orWhere(function ($sq) use ($startDate) {
                    $sq->whereNull('start_date')
                       ->whereNull('expected_end_date')
                       ->where('created_at', '>=', $startDate . ' 00:00:00');
                })
                ->orWhereHas('clientInvoices', fn ($iq) => $iq->where('invoice_date', '>=', $startDate));
            });
        } elseif ($endDate) {
            $query->where(function ($q) use ($endDate) {
                $q->where(function ($sq) use ($endDate) {
                    $sq->whereNotNull('start_date')
                       ->where('start_date', '<=', $endDate);
                })
                ->orWhere(function ($sq) use ($endDate) {
                    $sq->whereNotNull('expected_end_date')
                       ->where('expected_end_date', '<=', $endDate);
                })
                ->orWhere(function ($sq) use ($endDate) {
                    $sq->whereNull('start_date')
                       ->whereNull('expected_end_date')
                       ->where('created_at', '<=', $endDate . ' 23:59:59');
                })
                ->orWhereHas('clientInvoices', fn ($iq) => $iq->where('invoice_date', '<=', $endDate));
            });
        }

        $projects = $query->get();
        $projectsDirectory = $billingService->getProjectsBillingDirectory($projects);

        return $billingService->exportProjectsBillingCsv($projectsDirectory, $startDate, $endDate);
    }

    private function ensureProjectAccess(int $projectId, ?Member $actor): void
    {
        abort_unless(
            $actor && ProjectTeamMember::where('project_id', $projectId)
                ->where('member_id', $actor->getKey())
                ->where('status', 'active')
                ->exists(),
            403
        );
    }

    public function storeInvoice(Request $request, ConstructionBillingService $billingService): RedirectResponse
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'invoice_code' => ['nullable', 'string', 'max:30'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'tax_type' => ['required', 'in:intra,inter'],
            'status' => ['nullable', 'in:draft,issued'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.gst_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $this->ensureProjectAccess((int) $validated['project_id'], $actor);

        $project = Project::findOrFail($validated['project_id']);
        $billingService->createInvoice($project, $validated, $actor, $request);

        return back()->with('success', 'Invoice saved successfully.');
    }

    public function storePayment(Request $request, ConstructionBillingService $billingService): RedirectResponse
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'invoice_id' => ['required', 'exists:construction_client_invoices,id'],
            'payment_code' => ['nullable', 'string', 'max:30'],
            'received_at' => ['nullable', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['nullable', 'in:cash,bank_transfer,upi,cheque,card,other'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->ensureProjectAccess((int) $validated['project_id'], $actor);

        $project = Project::findOrFail($validated['project_id']);
        $billingService->recordPayment($project, $validated, $actor, $request);

        return back()->with('success', 'Payment recorded successfully.');
    }
}

