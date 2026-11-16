<?php

namespace App\Services\Construction;

use App\Models\ClientInvoice;
use App\Models\ClientInvoiceItem;
use App\Models\ClientPayment;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConstructionBillingService
{
    public function __construct(
        private readonly ConstructionActivityService $activityService
    ) {
    }

    public function createInvoice(Project $project, array $validated, ?Model $actor, ?Request $request = null): ClientInvoice
    {
        return DB::transaction(function () use ($project, $validated, $actor, $request) {
            $items = $validated['items'] ?? [];
            if (count($items) === 0) {
                throw ValidationException::withMessages([
                    'items' => 'At least one invoice item is required.',
                ]);
            }

            $nextId = (ClientInvoice::max('id') ?? 0) + 1;
            $invoiceCode = $validated['invoice_code'] ?? ('INV-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT));
            $taxType = $validated['tax_type'] ?? 'intra';

            if (!in_array($taxType, ['intra', 'inter'], true)) {
                throw ValidationException::withMessages([
                    'tax_type' => 'Invalid tax type.',
                ]);
            }

            $invoice = ClientInvoice::create([
                'project_id' => $project->id,
                'invoice_code' => $invoiceCode,
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'] ?? null,
                'tax_type' => $taxType,
                'status' => $validated['status'] ?? 'issued',
                'notes' => $validated['notes'] ?? null,
                'subtotal_amount' => 0,
                'cgst_amount' => 0,
                'sgst_amount' => 0,
                'igst_amount' => 0,
                'total_tax_amount' => 0,
                'total_amount' => 0,
                'paid_amount' => 0,
                'balance_amount' => 0,
                'created_by_type' => $actor ? $actor::class : null,
                'created_by_id' => $actor?->getKey(),
            ]);

            $subtotal = 0.0;
            $cgst = 0.0;
            $sgst = 0.0;
            $igst = 0.0;

            foreach ($items as $index => $item) {
                $description = trim((string) ($item['description'] ?? ''));
                if ($description === '') {
                    throw ValidationException::withMessages([
                        "items.$index.description" => 'Description is required.',
                    ]);
                }

                $quantity = (float) ($item['quantity'] ?? 0);
                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        "items.$index.quantity" => 'Quantity must be greater than 0.',
                    ]);
                }

                $rate = (float) ($item['rate'] ?? 0);
                if ($rate < 0) {
                    throw ValidationException::withMessages([
                        "items.$index.rate" => 'Rate cannot be negative.',
                    ]);
                }

                $gstPercent = (float) ($item['gst_percent'] ?? 0);
                if ($gstPercent < 0 || $gstPercent > 100) {
                    throw ValidationException::withMessages([
                        "items.$index.gst_percent" => 'GST percent must be between 0 and 100.',
                    ]);
                }

                $lineSubtotal = $quantity * $rate;
                $lineTax = $lineSubtotal * ($gstPercent / 100);
                $lineCgst = 0.0;
                $lineSgst = 0.0;
                $lineIgst = 0.0;

                if ($taxType === 'intra') {
                    $lineCgst = $lineTax / 2;
                    $lineSgst = $lineTax / 2;
                } else {
                    $lineIgst = $lineTax;
                }

                $lineTotal = $lineSubtotal + $lineTax;

                ClientInvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $description,
                    'quantity' => $quantity,
                    'unit' => $item['unit'] ?? null,
                    'rate' => $rate,
                    'line_subtotal' => $lineSubtotal,
                    'gst_percent' => $gstPercent,
                    'cgst_amount' => $lineCgst,
                    'sgst_amount' => $lineSgst,
                    'igst_amount' => $lineIgst,
                    'line_total_tax' => $lineTax,
                    'line_total' => $lineTotal,
                ]);

                $subtotal += $lineSubtotal;
                $cgst += $lineCgst;
                $sgst += $lineSgst;
                $igst += $lineIgst;
            }

            $totalTax = $cgst + $sgst + $igst;
            $total = $subtotal + $totalTax;

            $invoice->forceFill([
                'subtotal_amount' => $subtotal,
                'cgst_amount' => $cgst,
                'sgst_amount' => $sgst,
                'igst_amount' => $igst,
                'total_tax_amount' => $totalTax,
                'total_amount' => $total,
                'paid_amount' => 0,
                'balance_amount' => $total,
            ])->save();

            $this->activityService->log(
                module: 'billing_invoice',
                action: 'created',
                actor: $actor,
                reference: $invoice,
                companyId: $project->company_id,
                projectId: $project->id,
                meta: [
                    'invoice_code' => $invoice->invoice_code,
                    'tax_type' => $invoice->tax_type,
                    'total_amount' => $invoice->total_amount,
                ],
                request: $request
            );

            return $invoice->load(['items', 'payments']);
        });
    }

    public function updateDraftInvoice(ClientInvoice $invoice, array $validated, ?Model $actor, ?Request $request = null): ClientInvoice
    {
        return DB::transaction(function () use ($invoice, $validated, $actor, $request) {
            // Issued invoices are immutable. Only draft invoices may be edited.
            if ($invoice->status !== 'draft') {
                throw ValidationException::withMessages([
                    'invoice' => 'Only draft invoices can be edited. Issued invoices are immutable.',
                ]);
            }

            $items = $validated['items'] ?? [];
            if (count($items) === 0) {
                throw ValidationException::withMessages([
                    'items' => 'At least one invoice item is required.',
                ]);
            }

            $taxType = $validated['tax_type'] ?? $invoice->tax_type;
            if (!in_array($taxType, ['intra', 'inter'], true)) {
                throw ValidationException::withMessages([
                    'tax_type' => 'Invalid tax type.',
                ]);
            }

            $invoice->update([
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'] ?? null,
                'tax_type' => $taxType,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Replace draft items safely. Existing items are removed and
            // recreated with recalculated financial values.
            $invoice->items()->delete();

            $subtotal = 0.0;
            $cgst = 0.0;
            $sgst = 0.0;
            $igst = 0.0;

            foreach ($items as $index => $item) {
                $description = trim((string) ($item['description'] ?? ''));
                if ($description === '') {
                    throw ValidationException::withMessages([
                        "items.$index.description" => 'Description is required.',
                    ]);
                }

                $quantity = (float) ($item['quantity'] ?? 0);
                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        "items.$index.quantity" => 'Quantity must be greater than 0.',
                    ]);
                }

                $rate = (float) ($item['rate'] ?? 0);
                if ($rate < 0) {
                    throw ValidationException::withMessages([
                        "items.$index.rate" => 'Rate cannot be negative.',
                    ]);
                }

                $gstPercent = (float) ($item['gst_percent'] ?? 0);
                if ($gstPercent < 0 || $gstPercent > 100) {
                    throw ValidationException::withMessages([
                        "items.$index.gst_percent" => 'GST percent must be between 0 and 100.',
                    ]);
                }

                $lineSubtotal = $quantity * $rate;
                $lineTax = $lineSubtotal * ($gstPercent / 100);
                $lineCgst = 0.0;
                $lineSgst = 0.0;
                $lineIgst = 0.0;

                if ($taxType === 'intra') {
                    $lineCgst = $lineTax / 2;
                    $lineSgst = $lineTax / 2;
                } else {
                    $lineIgst = $lineTax;
                }

                $lineTotal = $lineSubtotal + $lineTax;

                ClientInvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $description,
                    'quantity' => $quantity,
                    'unit' => $item['unit'] ?? null,
                    'rate' => $rate,
                    'line_subtotal' => $lineSubtotal,
                    'gst_percent' => $gstPercent,
                    'cgst_amount' => $lineCgst,
                    'sgst_amount' => $lineSgst,
                    'igst_amount' => $lineIgst,
                    'line_total_tax' => $lineTax,
                    'line_total' => $lineTotal,
                ]);

                $subtotal += $lineSubtotal;
                $cgst += $lineCgst;
                $sgst += $lineSgst;
                $igst += $lineIgst;
            }

            $totalTax = $cgst + $sgst + $igst;
            $total = $subtotal + $totalTax;

            $invoice->forceFill([
                'subtotal_amount' => $subtotal,
                'cgst_amount' => $cgst,
                'sgst_amount' => $sgst,
                'igst_amount' => $igst,
                'total_tax_amount' => $totalTax,
                'total_amount' => $total,
                'balance_amount' => $total,
            ])->save();

            $this->activityService->log(
                module: 'billing_invoice',
                action: 'updated',
                actor: $actor,
                reference: $invoice,
                companyId: $invoice->project->company_id,
                projectId: $invoice->project_id,
                meta: [
                    'invoice_code' => $invoice->invoice_code,
                    'tax_type' => $invoice->tax_type,
                    'total_amount' => $invoice->total_amount,
                ],
                request: $request
            );

            return $invoice->load(['items', 'payments']);
        });
    }

    public function recordPayment(Project $project, array $validated, ?Model $actor, ?Request $request = null): ClientPayment
    {
        return DB::transaction(function () use ($project, $validated, $actor, $request) {
            /** @var ClientInvoice|null $invoice */
            $invoice = ClientInvoice::query()
                ->whereKey((int) $validated['invoice_id'])
                ->where('project_id', $project->id)
                ->lockForUpdate()
                ->first();

            if (!$invoice) {
                throw ValidationException::withMessages([
                    'invoice_id' => 'The selected invoice does not belong to the chosen project.',
                ]);
            }

            if ($invoice->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'invoice_id' => 'Cancelled invoices cannot receive payments.',
                ]);
            }

            $amount = (float) $validated['amount'];
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment amount must be greater than 0.',
                ]);
            }

            $balance = (float) $invoice->balance_amount;
            if ($amount > $balance) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment amount cannot exceed invoice balance.',
                ]);
            }

            $nextId = (ClientPayment::max('id') ?? 0) + 1;
            $paymentCode = $validated['payment_code'] ?? ('PAY-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT));

            $payment = ClientPayment::create([
                'project_id' => $project->id,
                'invoice_id' => $invoice->id,
                'payment_code' => $paymentCode,
                'received_at' => $validated['received_at'] ?? now(),
                'amount' => $amount,
                'method' => $validated['method'] ?? 'bank_transfer',
                'reference_no' => $validated['reference_no'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'received_by_type' => $actor ? $actor::class : null,
                'received_by_id' => $actor?->getKey(),
            ]);

            $paidAmount = (float) $invoice->paid_amount + $amount;
            $balanceAmount = (float) $invoice->total_amount - $paidAmount;
            if ($balanceAmount < 0) {
                $balanceAmount = 0;
            }

            $status = $balanceAmount == 0.0 ? 'paid' : 'partially_paid';

            $invoice->forceFill([
                'paid_amount' => $paidAmount,
                'balance_amount' => $balanceAmount,
                'status' => $status,
            ])->save();

            $this->activityService->log(
                module: 'billing_payment',
                action: 'received',
                actor: $actor,
                reference: $payment,
                companyId: $project->company_id,
                projectId: $project->id,
                meta: [
                    'invoice_id' => $invoice->id,
                    'payment_code' => $payment->payment_code,
                    'amount' => $amount,
                ],
                request: $request
            );

            return $payment->load(['invoice']);
        });
    }

    /**
     * Build rich Project & Client Directory data including tasks counts, checklist counts, khasra numbers, and financial summaries.
     */
    public function getProjectsBillingDirectory(iterable $projects): array
    {
        $collection = $projects instanceof \Illuminate\Support\Collection ? $projects : collect($projects);
        $projectIds = $collection->pluck('id')->filter()->all();

        if (empty($projectIds)) {
            return [];
        }

        $taskChecklistsByProject = [];

        try {
            \App\Models\TaskChecklist::query()
                ->selectRaw('et.project_id as p_id, COUNT(*) as c_total, SUM(CASE WHEN construction_task_checklists.is_completed = 1 THEN 1 ELSE 0 END) as c_completed')
                ->join('construction_execution_tasks as et', 'et.id', '=', 'construction_task_checklists.execution_task_id')
                ->whereIn('et.project_id', $projectIds)
                ->groupBy('et.project_id')
                ->each(static function ($row) use (&$taskChecklistsByProject) {
                    $pid = (int) $row->p_id;
                    $taskChecklistsByProject[$pid] = [
                        'total' => ($taskChecklistsByProject[$pid]['total'] ?? 0) + (int) $row->c_total,
                        'completed' => ($taskChecklistsByProject[$pid]['completed'] ?? 0) + (int) $row->c_completed,
                    ];
                });
        } catch (\Throwable) {
        }

        try {
            \App\Models\TaskChecklist::query()
                ->selectRaw('sp.project_id as p_id, COUNT(*) as c_total, SUM(CASE WHEN construction_task_checklists.is_completed = 1 THEN 1 ELSE 0 END) as c_completed')
                ->join('construction_survey_plans as sp', 'sp.id', '=', 'construction_task_checklists.survey_plan_id')
                ->whereIn('sp.project_id', $projectIds)
                ->groupBy('sp.project_id')
                ->each(static function ($row) use (&$taskChecklistsByProject) {
                    $pid = (int) $row->p_id;
                    $taskChecklistsByProject[$pid] = [
                        'total' => ($taskChecklistsByProject[$pid]['total'] ?? 0) + (int) $row->c_total,
                        'completed' => ($taskChecklistsByProject[$pid]['completed'] ?? 0) + (int) $row->c_completed,
                    ];
                });
        } catch (\Throwable) {
        }

        try {
            \App\Models\TaskChecklistItem::query()
                ->selectRaw('COALESCE(task_checklist_items.project_id, tasks.project_id) as p_id, COUNT(*) as c_total, SUM(CASE WHEN task_checklist_items.is_completed = 1 THEN 1 ELSE 0 END) as c_completed')
                ->leftJoin('tasks', 'tasks.id', '=', 'task_checklist_items.task_id')
                ->where(function ($q) use ($projectIds) {
                    $q->whereIn('task_checklist_items.project_id', $projectIds)
                        ->orWhereIn('tasks.project_id', $projectIds);
                })
                ->whereNull('task_checklist_items.deleted_at')
                ->groupBy('p_id')
                ->each(static function ($row) use (&$taskChecklistsByProject) {
                    $pid = (int) $row->p_id;
                    if ($pid > 0) {
                        $taskChecklistsByProject[$pid] = [
                            'total' => ($taskChecklistsByProject[$pid]['total'] ?? 0) + (int) $row->c_total,
                            'completed' => ($taskChecklistsByProject[$pid]['completed'] ?? 0) + (int) $row->c_completed,
                        ];
                    }
                });
        } catch (\Throwable) {
        }

        $tasksStatsByProject = collect();
        try {
            $tasksStatsByProject = \App\Models\ExecutionTask::query()
                ->selectRaw('project_id, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress', ['completed', 'in_progress'])
                ->whereIn('project_id', $projectIds)
                ->groupBy('project_id')
                ->get()
                ->keyBy('project_id');
        } catch (\Throwable) {
        }

        $unifiedTasksStatsByProject = collect();
        try {
            $unifiedTasksStatsByProject = \App\Models\Task::query()
                ->selectRaw('project_id, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress', ['completed', 'in_progress'])
                ->whereIn('project_id', $projectIds)
                ->whereNull('deleted_at')
                ->groupBy('project_id')
                ->get()
                ->keyBy('project_id');
        } catch (\Throwable) {
        }

        $invoicesSummary = collect();
        try {
            $invoicesSummary = \App\Models\ClientInvoice::query()
                ->selectRaw('project_id, COUNT(*) as inv_count, SUM(total_amount) as total_billed, SUM(paid_amount) as total_paid, SUM(balance_amount) as outstanding')
                ->whereIn('project_id', $projectIds)
                ->groupBy('project_id')
                ->get()
                ->keyBy('project_id');
        } catch (\Throwable) {
        }

        $paymentsSummary = collect();
        try {
            $paymentsSummary = \App\Models\ClientPayment::query()
                ->selectRaw('project_id, COUNT(*) as pay_count')
                ->whereIn('project_id', $projectIds)
                ->groupBy('project_id')
                ->get()
                ->keyBy('project_id');
        } catch (\Throwable) {
        }

        return $collection->map(function ($project) use (
            $taskChecklistsByProject,
            $tasksStatsByProject,
            $unifiedTasksStatsByProject,
            $invoicesSummary,
            $paymentsSummary
        ) {
            $pid = (int) $project->id;
            $execStats = $tasksStatsByProject->get($pid);
            $uniStats = $unifiedTasksStatsByProject->get($pid);

            $totalTasks = (int) ($execStats?->total ?? 0) + (int) ($uniStats?->total ?? 0);
            $completedTasks = (int) ($execStats?->completed ?? 0) + (int) ($uniStats?->completed ?? 0);
            $inProgressTasks = (int) ($execStats?->in_progress ?? 0) + (int) ($uniStats?->in_progress ?? 0);
            $pendingTasks = max(0, $totalTasks - $completedTasks - $inProgressTasks);

            $chk = $taskChecklistsByProject[$pid] ?? ['total' => 0, 'completed' => 0];
            $totalChecklists = (int) $chk['total'];
            $completedChecklists = (int) $chk['completed'];
            $pendingChecklists = max(0, $totalChecklists - $completedChecklists);

            $invSum = $invoicesSummary->get($pid);
            $paySum = $paymentsSummary->get($pid);

            $khasraNumber = $project->khasra_number;
            if (empty($khasraNumber) && $project->relationLoaded('draftingJobs')) {
                $khasraNumber = $project->draftingJobs->pluck('khasra_number')->filter()->unique()->implode(', ');
            }

            $siteArea = $project->site_area;
            if (empty($siteArea) && $project->relationLoaded('draftingJobs')) {
                $siteArea = $project->draftingJobs->pluck('site_area')->filter()->first();
            }

            return [
                'id' => $project->id,
                'project_code' => $project->project_code,
                'name' => $project->name,
                'current_stage' => $project->current_stage ?? 'planning',
                'status' => $project->status ?? 'active',
                'priority' => $project->priority ?? 'medium',
                'start_date' => $project->start_date ? (is_string($project->start_date) ? $project->start_date : $project->start_date->format('Y-m-d')) : null,
                'expected_end_date' => $project->expected_end_date ? (is_string($project->expected_end_date) ? $project->expected_end_date : $project->expected_end_date->format('Y-m-d')) : null,
                'project_address' => $project->project_address,
                'location_name' => $project->location_name,
                'khasra_number' => $khasraNumber ?: '—',
                'site_area' => $siteArea ?: '—',
                'scope_of_work' => $project->scope_of_work,
                'client' => [
                    'id' => $project->client?->id,
                    'client_code' => $project->client?->client_code ?: '—',
                    'name' => $project->client?->name ?: 'N/A',
                    'company_name' => $project->company?->name ?: ($project->client?->name ?: 'N/A'),
                    'contact_person' => $project->client?->contact_person ?: '—',
                    'email' => $project->client?->email ?: '—',
                    'phone' => $project->client?->phone ?: '—',
                    'alternate_phone' => $project->client?->alternate_phone ?: '—',
                    'gst_number' => $project->client?->gst_number ?: '—',
                    'billing_address' => $project->client?->billing_address ?: '—',
                    'site_address' => $project->client?->site_address ?: '—',
                ],
                'task_counts' => [
                    'total' => $totalTasks,
                    'completed' => $completedTasks,
                    'in_progress' => $inProgressTasks,
                    'pending' => $pendingTasks,
                    'progress_percent' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0,
                ],
                'checklist_counts' => [
                    'total' => $totalChecklists,
                    'completed' => $completedChecklists,
                    'pending' => $pendingChecklists,
                    'progress_percent' => $totalChecklists > 0 ? round(($completedChecklists / $totalChecklists) * 100) : 0,
                ],
                'billing_summary' => [
                    'total_billed' => (float) ($invSum?->total_billed ?? 0),
                    'total_paid' => (float) ($invSum?->total_paid ?? 0),
                    'outstanding' => (float) ($invSum?->outstanding ?? 0),
                    'invoices_count' => (int) ($invSum?->inv_count ?? 0),
                    'payments_count' => (int) ($paySum?->pay_count ?? 0),
                ],
                'invoices' => $project->relationLoaded('clientInvoices') ? $project->clientInvoices->map(fn ($inv) => [
                    'id' => $inv->id,
                    'invoice_code' => $inv->invoice_code,
                    'invoice_date' => $inv->invoice_date,
                    'due_date' => $inv->due_date,
                    'total_amount' => (float) $inv->total_amount,
                    'paid_amount' => (float) $inv->paid_amount,
                    'balance_amount' => (float) $inv->balance_amount,
                    'status' => $inv->status,
                ])->all() : [],
            ];
        })->values()->all();
    }

    /**
     * Export project and client billing summary as a downloadable CSV.
     */
    public function exportProjectsBillingCsv(array $projectsData): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="projects_clients_billing_summary_' . date('Y-m-d_His') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($projectsData) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Project Code',
                'Project Name',
                'Stage',
                'Status',
                'Client Code',
                'Client Name',
                'Company Name',
                'Contact Person',
                'Client Phone',
                'Client Email',
                'Khasra Number',
                'Site Area',
                'Total Tasks',
                'Completed Tasks',
                'In Progress Tasks',
                'Pending Tasks',
                'Total Checklists',
                'Completed Checklists',
                'Total Billed (INR)',
                'Total Paid (INR)',
                'Balance Outstanding (INR)',
            ]);

            foreach ($projectsData as $row) {
                fputcsv($handle, [
                    $row['project_code'] ?? '',
                    $row['name'] ?? '',
                    $row['current_stage'] ?? '',
                    $row['status'] ?? '',
                    $row['client']['client_code'] ?? '',
                    $row['client']['name'] ?? '',
                    $row['client']['company_name'] ?? '',
                    $row['client']['contact_person'] ?? '',
                    $row['client']['phone'] ?? '',
                    $row['client']['email'] ?? '',
                    $row['khasra_number'] ?? '',
                    $row['site_area'] ?? '',
                    $row['task_counts']['total'] ?? 0,
                    $row['task_counts']['completed'] ?? 0,
                    $row['task_counts']['in_progress'] ?? 0,
                    $row['task_counts']['pending'] ?? 0,
                    $row['checklist_counts']['total'] ?? 0,
                    $row['checklist_counts']['completed'] ?? 0,
                    $row['billing_summary']['total_billed'] ?? 0,
                    $row['billing_summary']['total_paid'] ?? 0,
                    $row['billing_summary']['outstanding'] ?? 0,
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}

