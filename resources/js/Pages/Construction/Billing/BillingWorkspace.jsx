import { useForm, usePage } from "@inertiajs/react";
import { useMemo, useState } from "react";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import Modal from "@/Components/Modal";

export default function BillingWorkspace({ variant = "super", projects = [], raw_projects = [], invoices = [], payments = [] }) {
    const pageProps = usePage().props;
    const auth = pageProps?.auth;
    const isSuperAdmin = auth?.guard === "superadmin" || auth?.user?.slug === "super-admin" || Boolean(auth?.user?.is_super_admin);
    const userPermissions = auth?.permissions || auth?.construction_permissions || pageProps?.permissions || [];

    const hasPerm = (permSlug) => {
        if (isSuperAdmin) return true;
        if (userPermissions.includes(permSlug)) return true;
        const prefix = permSlug.split(".")[0];
        return userPermissions.some((p) => typeof p === "string" && (p === permSlug || p.startsWith(prefix + ".")));
    };

    const canCreateInvoice = isSuperAdmin || hasPerm("billing_invoice.create") || hasPerm("billing_invoice.manage") || hasPerm("billing.manage");
    const canEditInvoice = isSuperAdmin || hasPerm("billing_invoice.edit") || hasPerm("billing_invoice.manage") || hasPerm("billing.manage");
    const canRecordPayment = isSuperAdmin || hasPerm("billing_payment.create") || hasPerm("billing_payment.manage") || hasPerm("billing.manage");

    const routeBase = variant === "super" ? "super.construction.billing" : "admin.construction.billing";

    const selectableProjects = useMemo(() => {
        if (Array.isArray(raw_projects) && raw_projects.length > 0) return raw_projects;
        return projects.map((p) => ({ id: p.id, project_code: p.project_code, name: p.name }));
    }, [raw_projects, projects]);

    const firstProjectId = selectableProjects[0]?.id ? String(selectableProjects[0].id) : "";

    // Active Tab: "directory" (Projects & Clients Listing - default) | "invoices" (Invoices & Payments Manager)
    const [activeTab, setActiveTab] = useState("directory");

    // Search and filter for Projects & Clients Listing
    const [searchQuery, setSearchQuery] = useState("");
    const [stageFilter, setStageFilter] = useState("all");

    // Modal state for project details
    const [selectedProject, setSelectedProject] = useState(null);

    // Global Stats calculation
    const stats = useMemo(() => {
        const totalBilled = invoices.reduce((sum, inv) => sum + Number(inv.total_amount || 0), 0);
        const totalReceived = invoices.reduce((sum, inv) => sum + Number(inv.paid_amount || 0), 0);
        const outstanding = invoices.reduce((sum, inv) => sum + Number(inv.balance_amount || 0), 0);

        const uniqueClientIds = new Set(
            projects
                .map((p) => p.client?.id || p.client?.client_code || p.client?.name)
                .filter(Boolean)
        );

        const totalTasks = projects.reduce((sum, p) => sum + Number(p.task_counts?.total || 0), 0);
        const completedTasks = projects.reduce((sum, p) => sum + Number(p.task_counts?.completed || 0), 0);

        return {
            projects: projects.length,
            clients: uniqueClientIds.size,
            totalTasks,
            completedTasks,
            invoices: invoices.length,
            payments: payments.length,
            totalBilled,
            totalReceived,
            outstanding,
        };
    }, [projects, invoices, payments]);

    // Filtered Projects for directory
    const filteredProjects = useMemo(() => {
        const query = searchQuery.trim().toLowerCase();
        return projects.filter((project) => {
            if (stageFilter !== "all" && String(project.current_stage || "").toLowerCase() !== stageFilter.toLowerCase()) {
                return false;
            }

            if (!query) return true;

            const matchesProject =
                String(project.project_code || "").toLowerCase().includes(query) ||
                String(project.name || "").toLowerCase().includes(query) ||
                String(project.khasra_number || "").toLowerCase().includes(query) ||
                String(project.site_area || "").toLowerCase().includes(query) ||
                String(project.project_address || "").toLowerCase().includes(query);

            const client = project.client || {};
            const matchesClient =
                String(client.name || "").toLowerCase().includes(query) ||
                String(client.client_code || "").toLowerCase().includes(query) ||
                String(client.phone || "").toLowerCase().includes(query) ||
                String(client.email || "").toLowerCase().includes(query) ||
                String(client.company_name || "").toLowerCase().includes(query) ||
                String(client.contact_person || "").toLowerCase().includes(query);

            return matchesProject || matchesClient;
        });
    }, [projects, searchQuery, stageFilter]);

    // CSV Download handler
    const handleDownloadCsv = (targetData = filteredProjects) => {
        const headers = [
            "Project Code",
            "Project Name",
            "Stage",
            "Status",
            "Priority",
            "Client Code",
            "Client Name",
            "Company Name",
            "Contact Person",
            "Client Phone",
            "Client Email",
            "GST Number",
            "Khasra Number",
            "Site Area",
            "Project Address",
            "Total Tasks",
            "Completed Tasks",
            "In Progress Tasks",
            "Pending Tasks",
            "Task Progress %",
            "Total Checklists",
            "Completed Checklists",
            "Checklist Progress %",
            "Total Billed (INR)",
            "Total Received (INR)",
            "Balance Outstanding (INR)",
        ];

        const escapeCsv = (val) => {
            if (val === null || val === undefined) return '""';
            const str = String(val).replace(/"/g, '""');
            return `"${str}"`;
        };

        const rows = targetData.map((p) => [
            escapeCsv(p.project_code || ""),
            escapeCsv(p.name || ""),
            escapeCsv(p.current_stage || ""),
            escapeCsv(p.status || ""),
            escapeCsv(p.priority || ""),
            escapeCsv(p.client?.client_code || ""),
            escapeCsv(p.client?.name || ""),
            escapeCsv(p.client?.company_name || ""),
            escapeCsv(p.client?.contact_person || ""),
            escapeCsv(p.client?.phone || ""),
            escapeCsv(p.client?.email || ""),
            escapeCsv(p.client?.gst_number || ""),
            escapeCsv(p.khasra_number || ""),
            escapeCsv(p.site_area || ""),
            escapeCsv(p.project_address || ""),
            escapeCsv(p.task_counts?.total ?? 0),
            escapeCsv(p.task_counts?.completed ?? 0),
            escapeCsv(p.task_counts?.in_progress ?? 0),
            escapeCsv(p.task_counts?.pending ?? 0),
            escapeCsv(`${p.task_counts?.progress_percent ?? 0}%`),
            escapeCsv(p.checklist_counts?.total ?? 0),
            escapeCsv(p.checklist_counts?.completed ?? 0),
            escapeCsv(`${p.checklist_counts?.progress_percent ?? 0}%`),
            escapeCsv(Number(p.billing_summary?.total_billed ?? 0).toFixed(2)),
            escapeCsv(Number(p.billing_summary?.total_paid ?? 0).toFixed(2)),
            escapeCsv(Number(p.billing_summary?.outstanding ?? 0).toFixed(2)),
        ]);

        const csvContent = "\uFEFF" + [headers.join(","), ...rows.map((r) => r.join(","))].join("\r\n");
        const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.setAttribute("href", url);
        link.setAttribute("download", `projects_clients_billing_summary_${new Date().toISOString().slice(0, 10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    };

    // Invoice Form & Logic
    const invoiceForm = useForm({
        project_id: firstProjectId,
        invoice_code: "",
        invoice_date: new Date().toISOString().slice(0, 10),
        due_date: "",
        tax_type: "intra",
        status: "issued",
        notes: "",
        items: [],
    });

    const [invoiceItems, setInvoiceItems] = useState([{ description: "", quantity: "1", unit: "", rate: "", gst_percent: "18" }]);

    const [editingInvoice, setEditingInvoice] = useState(null);

    const editInvoiceForm = useForm({
        invoice_date: "",
        due_date: "",
        tax_type: "intra",
        notes: "",
    });

    const [editInvoiceItems, setEditInvoiceItems] = useState([{ description: "", quantity: "1", unit: "", rate: "", gst_percent: "18" }]);

    const openEditInvoiceModal = (invoice) => {
        editInvoiceForm.clearErrors();
        editInvoiceForm.setData({
            invoice_date: invoice.invoice_date || "",
            due_date: invoice.due_date || "",
            tax_type: invoice.tax_type || "intra",
            notes: invoice.notes || "",
        });
        setEditInvoiceItems(
            (invoice.items || []).map((item) => ({
                description: item.description || "",
                quantity: String(item.quantity ?? ""),
                unit: item.unit || "",
                rate: String(item.rate ?? ""),
                gst_percent: String(item.gst_percent ?? ""),
            }))
        );
        setEditingInvoice(invoice);
    };

    const closeEditInvoiceModal = () => {
        editInvoiceForm.clearErrors();
        editInvoiceForm.reset();
        setEditingInvoice(null);
    };

    const submitEditInvoice = (e) => {
        e.preventDefault();
        editInvoiceForm.transform((data) => ({
            ...data,
            items: editInvoiceItems.map((item) => ({
                description: item.description,
                quantity: item.quantity,
                unit: item.unit,
                rate: item.rate,
                gst_percent: item.gst_percent,
            })),
        }));
        editInvoiceForm.put(route(`${routeBase}.invoices.update`, editingInvoice.id), {
            preserveScroll: true,
            onSuccess: () => closeEditInvoiceModal(),
        });
    };

    // Payment Form
    const paymentForm = useForm({
        project_id: firstProjectId,
        invoice_id: "",
        payment_code: "",
        received_at: "",
        amount: "",
        method: "bank_transfer",
        reference_no: "",
        notes: "",
    });

    const invoicesByProject = useMemo(() => {
        return invoices.reduce((carry, invoice) => {
            const pid = String(invoice.project_id);
            if (!carry[pid]) carry[pid] = [];
            carry[pid].push(invoice);
            return carry;
        }, {});
    }, [invoices]);

    const selectedProjectInvoices = invoicesByProject[String(paymentForm.data.project_id)] ?? [];

    const invoiceTotals = useMemo(() => {
        let subtotal = 0;
        let tax = 0;
        invoiceItems.forEach((item) => {
            const q = Number(item.quantity || 0);
            const r = Number(item.rate || 0);
            const p = Number(item.gst_percent || 0);
            const base = q * r;
            subtotal += base;
            tax += base * (p / 100);
        });
        return { subtotal, tax, total: subtotal + tax };
    }, [invoiceItems]);

    const renderProjectsSelect = (value, onChange, error) => (
        <SelectInput
            label="Project"
            value={value}
            onChange={onChange}
            options={selectableProjects.map((project) => ({
                value: String(project.id),
                label: `${project.project_code} • ${project.name}`,
            }))}
            error={error}
        />
    );

    return (
        <ConstructionShell
            title="Accounts & Billing"
            description="GST-aware client invoices, project directories, task completion tracking, and financial reconciliation."
            variant={variant}
        >
            {/* Top Stat Cards */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <StatCard label="Total Projects" value={stats.projects} />
                <StatCard label="Registered Clients" value={stats.clients} />
                <StatCard label="Execution Tasks" value={`${stats.completedTasks} / ${stats.totalTasks}`} />
                <StatCard label="Total Billed" value={`₹${stats.totalBilled.toFixed(2)}`} />
                <StatCard label="Total Received" value={`₹${stats.totalReceived.toFixed(2)}`} />
                <StatCard label="Outstanding Balance" value={`₹${stats.outstanding.toFixed(2)}`} />
            </div>

            {/* Navigation Tabs */}
            <div className="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-3 dark:border-slate-800">
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => setActiveTab("directory")}
                        className={`flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition ${
                            activeTab === "directory"
                                ? "bg-indigo-600 text-white shadow-sm"
                                : "bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                        }`}
                    >
                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        Projects & Clients Directory
                        <span className={`ml-1.5 rounded-full px-2 py-0.5 text-xs ${activeTab === "directory" ? "bg-indigo-500 text-white" : "bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300"}`}>
                            {projects.length}
                        </span>
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab("invoices")}
                        className={`flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition ${
                            activeTab === "invoices"
                                ? "bg-indigo-600 text-white shadow-sm"
                                : "bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                        }`}
                    >
                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Invoices & Payments Manager
                        <span className={`ml-1.5 rounded-full px-2 py-0.5 text-xs ${activeTab === "invoices" ? "bg-indigo-500 text-white" : "bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300"}`}>
                            {invoices.length}
                        </span>
                    </button>
                </div>

                {activeTab === "directory" ? (
                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            onClick={() => handleDownloadCsv(filteredProjects)}
                            className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-500"
                        >
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Download CSV
                        </button>
                    </div>
                ) : null}
            </div>

            {/* TAB 1: PROJECTS & CLIENTS DIRECTORY */}
            {activeTab === "directory" ? (
                <div className="space-y-4">
                    {/* Filter and Search Bar */}
                    <div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                        <div className="flex flex-1 items-center gap-3 min-w-[280px]">
                            <div className="relative w-full max-w-md">
                                <span className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </span>
                                <input
                                    type="text"
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    placeholder="Search by project, client, khasra no, phone..."
                                    className="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-9 pr-4 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:bg-white dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                />
                            </div>

                            <select
                                value={stageFilter}
                                onChange={(e) => setStageFilter(e.target.value)}
                                className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                            >
                                <option value="all">All Lifecycle Stages</option>
                                <option value="planning">Planning</option>
                                <option value="survey">Survey</option>
                                <option value="execution">Execution</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>

                        <div className="flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                            <span>Showing <strong>{filteredProjects.length}</strong> of <strong>{projects.length}</strong> projects</span>
                        </div>
                    </div>

                    {/* Listing Table */}
                    <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        {filteredProjects.length === 0 ? (
                            <div className="py-12">
                                <EmptyState
                                    title="No projects match your filter."
                                    description="Try clearing your search query or reset the stage filter to view all project records."
                                />
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-200 text-left text-sm dark:divide-slate-800">
                                    <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:bg-slate-950 dark:text-slate-400">
                                        <tr>
                                            <th className="px-5 py-3.5">Project</th>
                                            <th className="px-5 py-3.5">Client Details</th>
                                            <th className="px-5 py-3.5">Khasra / Site Area</th>
                                            <th className="px-5 py-3.5">Task Breakdown</th>
                                            <th className="px-5 py-3.5">Checklist Progress</th>
                                            <th className="px-5 py-3.5">Billing Snapshot</th>
                                            <th className="px-5 py-3.5 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                        {filteredProjects.map((project) => {
                                            const tasks = project.task_counts || { total: 0, completed: 0, in_progress: 0, pending: 0, progress_percent: 0 };
                                            const checklists = project.checklist_counts || { total: 0, completed: 0, pending: 0, progress_percent: 0 };
                                            const billing = project.billing_summary || { total_billed: 0, total_paid: 0, outstanding: 0 };
                                            const client = project.client || {};

                                            return (
                                                <tr key={project.id} className="transition hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                                    {/* Project */}
                                                    <td className="px-5 py-4">
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400">
                                                                {project.project_code}
                                                            </span>
                                                            <span className="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium capitalize text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                                {project.current_stage || "Planning"}
                                                            </span>
                                                        </div>
                                                        <p className="mt-1 font-semibold text-slate-900 dark:text-white">
                                                            {project.name}
                                                        </p>
                                                        {project.project_address ? (
                                                            <p className="mt-0.5 text-xs text-slate-500 line-clamp-1 dark:text-slate-400">
                                                                {project.project_address}
                                                            </p>
                                                        ) : null}
                                                    </td>

                                                    {/* Client Details */}
                                                    <td className="px-5 py-4">
                                                        <p className="font-semibold text-slate-900 dark:text-white">
                                                            {client.name || "N/A"}
                                                        </p>
                                                        <div className="mt-0.5 flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                                                            <span className="font-mono text-[11px] text-slate-400">{client.client_code}</span>
                                                            {client.phone && client.phone !== "—" ? (
                                                                <>
                                                                    <span>•</span>
                                                                    <span>{client.phone}</span>
                                                                </>
                                                            ) : null}
                                                        </div>
                                                        {client.email && client.email !== "—" ? (
                                                            <p className="mt-0.5 text-xs text-slate-400 line-clamp-1">{client.email}</p>
                                                        ) : null}
                                                    </td>

                                                    {/* Khasra / Site Area */}
                                                    <td className="px-5 py-4">
                                                        <div className="inline-flex items-center rounded-lg bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800 dark:bg-amber-950/50 dark:text-amber-300 border border-amber-200/60 dark:border-amber-800/40">
                                                            <span>Khasra: {project.khasra_number || "—"}</span>
                                                        </div>
                                                        {project.site_area && project.site_area !== "—" ? (
                                                            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                                Area: {project.site_area}
                                                            </p>
                                                        ) : null}
                                                    </td>

                                                    {/* Task Breakdown */}
                                                    <td className="px-5 py-4">
                                                        <div className="flex flex-wrap items-center gap-1.5 text-xs">
                                                            <span className="rounded-md bg-emerald-100 px-2 py-0.5 font-medium text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                                                {tasks.completed} Done
                                                            </span>
                                                            <span className="rounded-md bg-amber-100 px-2 py-0.5 font-medium text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                                                {tasks.in_progress} In Progress
                                                            </span>
                                                            <span className="rounded-md bg-slate-100 px-2 py-0.5 font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                                {tasks.pending} Pending
                                                            </span>
                                                        </div>
                                                        <div className="mt-2 flex items-center gap-2">
                                                            <div className="h-1.5 w-24 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                                                <div
                                                                    className="h-full bg-emerald-500 transition-all duration-300"
                                                                    style={{ width: `${tasks.progress_percent}%` }}
                                                                />
                                                            </div>
                                                            <span className="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                                                                {tasks.total} Total ({tasks.progress_percent}%)
                                                            </span>
                                                        </div>
                                                    </td>

                                                    {/* Checklist Progress */}
                                                    <td className="px-5 py-4">
                                                        <div className="flex items-center gap-2">
                                                            <span className="text-xs font-semibold text-slate-700 dark:text-slate-200">
                                                                {checklists.completed} / {checklists.total} done
                                                            </span>
                                                            <span className="text-[11px] text-slate-400">
                                                                ({checklists.progress_percent}%)
                                                            </span>
                                                        </div>
                                                        <div className="mt-1.5 h-1.5 w-24 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                                            <div
                                                                className="h-full bg-indigo-500 transition-all duration-300"
                                                                style={{ width: `${checklists.progress_percent}%` }}
                                                            />
                                                        </div>
                                                    </td>

                                                    {/* Billing Snapshot */}
                                                    <td className="px-5 py-4">
                                                        <p className="text-xs font-semibold text-slate-900 dark:text-white">
                                                            Billed: ₹{Number(billing.total_billed || 0).toFixed(2)}
                                                        </p>
                                                        <p className="text-xs text-emerald-600 dark:text-emerald-400">
                                                            Paid: ₹{Number(billing.total_paid || 0).toFixed(2)}
                                                        </p>
                                                        <p className={`text-xs font-medium ${billing.outstanding > 0 ? "text-rose-600 dark:text-rose-400" : "text-slate-400"}`}>
                                                            Due: ₹{Number(billing.outstanding || 0).toFixed(2)}
                                                        </p>
                                                    </td>

                                                    {/* Action */}
                                                    <td className="px-5 py-4 text-right">
                                                        <button
                                                            type="button"
                                                            onClick={() => setSelectedProject(project)}
                                                            className="inline-flex items-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50 px-3.5 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300 dark:hover:bg-indigo-900"
                                                        >
                                                            <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                            </svg>
                                                            View Details
                                                        </button>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            ) : null}

            {/* TAB 2: INVOICES & PAYMENTS MANAGER */}
            {activeTab === "invoices" ? (
                <div className="space-y-6">
                    {(canCreateInvoice || canRecordPayment) ? (
                        <div className="grid gap-6 xl:grid-cols-2">
                            {canCreateInvoice ? (
                                <SectionCard title="Create Invoice" description="Create a GST invoice with line items and tax type (intra/inter).">
                                    <form
                                        onSubmit={(event) => {
                                            event.preventDefault();
                                            invoiceForm.transform((data) => ({
                                                ...data,
                                                items: invoiceItems.map((item) => ({
                                                    description: item.description,
                                                    quantity: item.quantity,
                                                    unit: item.unit,
                                                    rate: item.rate,
                                                    gst_percent: item.gst_percent,
                                                })),
                                            }));
                                            invoiceForm.post(route(`${routeBase}.invoices.store`), {
                                                preserveScroll: true,
                                                onSuccess: () => {
                                                    invoiceForm.reset("invoice_code", "due_date", "notes");
                                                    setInvoiceItems([{ description: "", quantity: "1", unit: "", rate: "", gst_percent: "18" }]);
                                                },
                                            });
                                        }}
                                        className="space-y-4"
                                    >
                                        {renderProjectsSelect(
                                            invoiceForm.data.project_id,
                                            (value) => invoiceForm.setData("project_id", value),
                                            invoiceForm.errors.project_id
                                        )}
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <TextInput
                                                label="Invoice Date"
                                                type="date"
                                                value={invoiceForm.data.invoice_date}
                                                onChange={(value) => invoiceForm.setData("invoice_date", value)}
                                                error={invoiceForm.errors.invoice_date}
                                            />
                                            <TextInput
                                                label="Due Date"
                                                type="date"
                                                value={invoiceForm.data.due_date}
                                                onChange={(value) => invoiceForm.setData("due_date", value)}
                                                error={invoiceForm.errors.due_date}
                                            />
                                            <SelectInput
                                                label="Tax Type"
                                                value={invoiceForm.data.tax_type}
                                                onChange={(value) => invoiceForm.setData("tax_type", value)}
                                                options={[
                                                    { value: "intra", label: "Intra-state (CGST+SGST)" },
                                                    { value: "inter", label: "Inter-state (IGST)" },
                                                ]}
                                                error={invoiceForm.errors.tax_type}
                                            />
                                            <SelectInput
                                                label="Status"
                                                value={invoiceForm.data.status}
                                                onChange={(value) => invoiceForm.setData("status", value)}
                                                options={[
                                                    { value: "issued", label: "Issued" },
                                                    { value: "draft", label: "Draft" },
                                                ]}
                                                error={invoiceForm.errors.status}
                                            />
                                        </div>

                                        <LineItemsEditor items={invoiceItems} onChange={setInvoiceItems} errors={invoiceForm.errors} />

                                        <div className="rounded-2xl bg-slate-50 p-4 text-sm text-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                            <div className="flex items-center justify-between">
                                                <span>Subtotal</span>
                                                <span className="font-semibold">₹{invoiceTotals.subtotal.toFixed(2)}</span>
                                            </div>
                                            <div className="mt-2 flex items-center justify-between">
                                                <span>GST</span>
                                                <span className="font-semibold">₹{invoiceTotals.tax.toFixed(2)}</span>
                                            </div>
                                            <div className="mt-2 flex items-center justify-between text-base font-semibold text-slate-900 dark:text-white">
                                                <span>Total</span>
                                                <span>₹{invoiceTotals.total.toFixed(2)}</span>
                                            </div>
                                        </div>

                                        <TextAreaInput
                                            label="Notes"
                                            value={invoiceForm.data.notes}
                                            onChange={(value) => invoiceForm.setData("notes", value)}
                                            error={invoiceForm.errors.notes}
                                        />

                                        <PrimaryButton processing={invoiceForm.processing} label="Save Invoice" />
                                    </form>
                                </SectionCard>
                            ) : null}

                            {canRecordPayment ? (
                                <SectionCard title="Record Payment" description="Record incoming payment against an issued invoice (no overpayments allowed).">
                                    <form
                                        onSubmit={(event) => {
                                            event.preventDefault();
                                            paymentForm.post(route(`${routeBase}.payments.store`), {
                                                preserveScroll: true,
                                                onSuccess: () => paymentForm.reset("invoice_id", "payment_code", "received_at", "amount", "reference_no", "notes"),
                                            });
                                        }}
                                        className="space-y-4"
                                    >
                                        {renderProjectsSelect(
                                            paymentForm.data.project_id,
                                            (value) => paymentForm.setData("project_id", value),
                                            paymentForm.errors.project_id
                                        )}
                                        <SelectInput
                                            label="Invoice"
                                            value={paymentForm.data.invoice_id}
                                            onChange={(value) => paymentForm.setData("invoice_id", value)}
                                            options={[
                                                { value: "", label: "Select invoice" },
                                                ...selectedProjectInvoices.map((invoice) => ({
                                                    value: String(invoice.id),
                                                    label: `${invoice.invoice_code} • ₹${Number(invoice.balance_amount || 0).toFixed(2)} due`,
                                                })),
                                            ]}
                                            error={paymentForm.errors.invoice_id}
                                        />
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <TextInput
                                                label="Amount"
                                                value={paymentForm.data.amount}
                                                onChange={(value) => paymentForm.setData("amount", value)}
                                                error={paymentForm.errors.amount}
                                            />
                                            <SelectInput
                                                label="Method"
                                                value={paymentForm.data.method}
                                                onChange={(value) => paymentForm.setData("method", value)}
                                                options={[
                                                    { value: "bank_transfer", label: "Bank Transfer" },
                                                    { value: "upi", label: "UPI" },
                                                    { value: "cash", label: "Cash" },
                                                    { value: "cheque", label: "Cheque" },
                                                    { value: "card", label: "Card" },
                                                    { value: "other", label: "Other" },
                                                ]}
                                                error={paymentForm.errors.method}
                                            />
                                            <TextInput
                                                label="Received At"
                                                type="datetime-local"
                                                value={paymentForm.data.received_at}
                                                onChange={(value) => paymentForm.setData("received_at", value)}
                                                error={paymentForm.errors.received_at}
                                            />
                                            <TextInput
                                                label="Reference No"
                                                value={paymentForm.data.reference_no}
                                                onChange={(value) => paymentForm.setData("reference_no", value)}
                                                error={paymentForm.errors.reference_no}
                                            />
                                        </div>
                                        <TextAreaInput
                                            label="Notes"
                                            value={paymentForm.data.notes}
                                            onChange={(value) => paymentForm.setData("notes", value)}
                                            error={paymentForm.errors.notes}
                                        />
                                        <PrimaryButton processing={paymentForm.processing} label="Record Payment" />
                                    </form>
                                </SectionCard>
                            ) : null}
                        </div>
                    ) : null}

                    {/* Invoices & Payments Lists */}
                    <div className="grid gap-6 xl:grid-cols-2">
                        <SectionCard title="Recent Invoices" description="Latest invoices created for accessible projects.">
                            {invoices.length === 0 ? (
                                <EmptyState title="No invoices yet." description="Create an invoice to start tracking billing." />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full text-sm">
                                        <thead>
                                            <tr className="text-left text-slate-500 dark:text-slate-300">
                                                <th className="py-2 pr-4">Project</th>
                                                <th className="py-2 pr-4">Invoice</th>
                                                <th className="py-2 pr-4">Date</th>
                                                <th className="py-2 pr-4">Total</th>
                                                <th className="py-2 pr-4">Paid</th>
                                                <th className="py-2 pr-4">Balance</th>
                                                <th className="py-2 pr-4">Status</th>
                                                <th className="py-2 pr-4">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                            {invoices.map((invoice) => (
                                                <tr key={invoice.id}>
                                                    <td className="py-3 pr-4">
                                                        <p className="font-medium text-slate-900 dark:text-white">
                                                            {invoice.project?.project_code || `#${invoice.project_id}`}
                                                        </p>
                                                    </td>
                                                    <td className="py-3 pr-4">
                                                        <p className="font-medium text-slate-900 dark:text-white">{invoice.invoice_code}</p>
                                                        <p className="text-xs text-slate-500 dark:text-slate-300">
                                                            {invoice.tax_type === "inter" ? "IGST" : "CGST+SGST"}
                                                        </p>
                                                    </td>
                                                    <td className="py-3 pr-4 text-slate-700 dark:text-slate-200">
                                                        {formatDate(invoice.invoice_date)}
                                                    </td>
                                                    <td className="py-3 pr-4 text-slate-700 dark:text-slate-200">
                                                        ₹{Number(invoice.total_amount || 0).toFixed(2)}
                                                    </td>
                                                    <td className="py-3 pr-4 text-slate-700 dark:text-slate-200">
                                                        ₹{Number(invoice.paid_amount || 0).toFixed(2)}
                                                    </td>
                                                    <td className="py-3 pr-4 text-slate-700 dark:text-slate-200">
                                                        ₹{Number(invoice.balance_amount || 0).toFixed(2)}
                                                    </td>
                                                    <td className="py-3 pr-4">
                                                        <StatusBadge value={invoice.status} />
                                                    </td>
                                                    <td className="py-3 pr-4">
                                                        {canEditInvoice && invoice.status === "draft" ? (
                                                            <button
                                                                type="button"
                                                                onClick={() => openEditInvoiceModal(invoice)}
                                                                className="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                                                            >
                                                                Edit
                                                            </button>
                                                        ) : null}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </SectionCard>

                        <SectionCard title="Recent Payments" description="Latest payment receipts booked against invoices.">
                            {payments.length === 0 ? (
                                <EmptyState title="No payments yet." description="Record a payment to reduce invoice outstanding." />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full text-sm">
                                        <thead>
                                            <tr className="text-left text-slate-500 dark:text-slate-300">
                                                <th className="py-2 pr-4">Project</th>
                                                <th className="py-2 pr-4">Payment</th>
                                                <th className="py-2 pr-4">Invoice</th>
                                                <th className="py-2 pr-4">Amount</th>
                                                <th className="py-2 pr-4">Method</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                            {payments.map((payment) => (
                                                <tr key={payment.id}>
                                                    <td className="py-3 pr-4">
                                                        <p className="font-medium text-slate-900 dark:text-white">
                                                            {payment.project?.project_code || `#${payment.project_id}`}
                                                        </p>
                                                    </td>
                                                    <td className="py-3 pr-4">
                                                        <p className="font-medium text-slate-900 dark:text-white">{payment.payment_code}</p>
                                                        {payment.reference_no ? (
                                                            <p className="text-xs text-slate-500 dark:text-slate-300">{payment.reference_no}</p>
                                                        ) : null}
                                                    </td>
                                                    <td className="py-3 pr-4 text-slate-700 dark:text-slate-200">
                                                        {payment.invoice?.invoice_code || `#${payment.invoice_id}`}
                                                    </td>
                                                    <td className="py-3 pr-4 text-slate-700 dark:text-slate-200">
                                                        ₹{Number(payment.amount || 0).toFixed(2)}
                                                    </td>
                                                    <td className="py-3 pr-4 text-slate-700 dark:text-slate-200">
                                                        {String(payment.method || "").replaceAll("_", " ")}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </SectionCard>
                    </div>
                </div>
            ) : null}

            {/* MODAL 1: PROJECT & CLIENT DETAILS MODAL */}
            <Modal show={selectedProject !== null} onClose={() => setSelectedProject(null)} maxWidth="4xl">
                {selectedProject ? (
                    <div className="flex flex-col max-h-[85vh]">
                        {/* Modal Header */}
                        <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                            <div className="flex items-center gap-3">
                                <span className="rounded-lg bg-indigo-50 px-2.5 py-1 font-mono text-sm font-bold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                    {selectedProject.project_code}
                                </span>
                                <div>
                                    <h3 className="text-lg font-bold text-slate-900 dark:text-white">
                                        {selectedProject.name}
                                    </h3>
                                    <p className="text-xs text-slate-500 dark:text-slate-400">
                                        Lifecycle Stage: <span className="font-medium capitalize text-slate-700 dark:text-slate-300">{selectedProject.current_stage || "Planning"}</span>
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    onClick={() => handleDownloadCsv([selectedProject])}
                                    className="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-500"
                                >
                                    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    Export CSV
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setSelectedProject(null)}
                                    className="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                                >
                                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        {/* Modal Body */}
                        <div className="overflow-y-auto p-6 space-y-6">
                            {/* Grid 1: Client Information & Land Details */}
                            <div className="grid gap-4 md:grid-cols-2">
                                {/* Client Info Card */}
                                <div className="rounded-2xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-900/40">
                                    <div className="flex items-center gap-2 border-b border-slate-200/60 pb-2.5 dark:border-slate-800">
                                        <svg className="h-4 w-4 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        <h4 className="text-sm font-bold text-slate-900 dark:text-white">Client Information</h4>
                                    </div>
                                    <dl className="mt-3 space-y-2 text-xs">
                                        <div className="flex justify-between">
                                            <dt className="text-slate-500">Client Name:</dt>
                                            <dd className="font-semibold text-slate-900 dark:text-white">{selectedProject.client?.name || "N/A"}</dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-slate-500">Client Code:</dt>
                                            <dd className="font-mono text-slate-700 dark:text-slate-300">{selectedProject.client?.client_code || "—"}</dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-slate-500">Company Name:</dt>
                                            <dd className="text-slate-700 dark:text-slate-300">{selectedProject.client?.company_name || "—"}</dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-slate-500">Contact Person:</dt>
                                            <dd className="text-slate-700 dark:text-slate-300">{selectedProject.client?.contact_person || "—"}</dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-slate-500">Phone / Mobile:</dt>
                                            <dd className="font-medium text-slate-900 dark:text-white">{selectedProject.client?.phone || "—"}</dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-slate-500">Email Address:</dt>
                                            <dd className="text-slate-700 dark:text-slate-300">{selectedProject.client?.email || "—"}</dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-slate-500">GST Number:</dt>
                                            <dd className="font-mono text-slate-700 dark:text-slate-300">{selectedProject.client?.gst_number || "—"}</dd>
                                        </div>
                                        {selectedProject.client?.billing_address && selectedProject.client.billing_address !== "—" ? (
                                            <div className="border-t border-slate-200/40 pt-2 dark:border-slate-800">
                                                <dt className="text-slate-500 mb-1">Billing Address:</dt>
                                                <dd className="text-slate-700 dark:text-slate-300">{selectedProject.client.billing_address}</dd>
                                            </div>
                                        ) : null}
                                    </dl>
                                </div>

                                {/* Land & Khasra Details Card */}
                                <div className="rounded-2xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-900/40">
                                    <div className="flex items-center gap-2 border-b border-slate-200/60 pb-2.5 dark:border-slate-800">
                                        <svg className="h-4 w-4 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <h4 className="text-sm font-bold text-slate-900 dark:text-white">Land & Site Details</h4>
                                    </div>
                                    <dl className="mt-3 space-y-2 text-xs">
                                        <div className="flex justify-between items-center">
                                            <dt className="text-slate-500">Khasra Number:</dt>
                                            <dd className="rounded-lg bg-amber-100 px-2.5 py-0.5 font-bold text-amber-900 dark:bg-amber-950 dark:text-amber-300">
                                                {selectedProject.khasra_number || "—"}
                                            </dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-slate-500">Site Area:</dt>
                                            <dd className="font-semibold text-slate-800 dark:text-slate-200">{selectedProject.site_area || "—"}</dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-slate-500">Location / City:</dt>
                                            <dd className="text-slate-700 dark:text-slate-300">{selectedProject.location_name || "—"}</dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-slate-500">Planned Start:</dt>
                                            <dd className="text-slate-700 dark:text-slate-300">{formatDate(selectedProject.start_date) || "—"}</dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-slate-500">Expected End:</dt>
                                            <dd className="text-slate-700 dark:text-slate-300">{formatDate(selectedProject.expected_end_date) || "—"}</dd>
                                        </div>
                                        {selectedProject.project_address ? (
                                            <div className="border-t border-slate-200/40 pt-2 dark:border-slate-800">
                                                <dt className="text-slate-500 mb-1">Project Site Address:</dt>
                                                <dd className="text-slate-700 dark:text-slate-300">{selectedProject.project_address}</dd>
                                            </div>
                                        ) : null}
                                        {selectedProject.scope_of_work ? (
                                            <div className="border-t border-slate-200/40 pt-2 dark:border-slate-800">
                                                <dt className="text-slate-500 mb-1">Scope of Work:</dt>
                                                <dd className="text-slate-700 dark:text-slate-300 line-clamp-2">{selectedProject.scope_of_work}</dd>
                                            </div>
                                        ) : null}
                                    </dl>
                                </div>
                            </div>

                            {/* Grid 2: Task Execution & Checklist Breakdown */}
                            <div className="grid gap-4 md:grid-cols-2">
                                {/* Task Execution Status Card */}
                                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                                    <div className="flex items-center justify-between border-b border-slate-100 pb-2.5 dark:border-slate-800">
                                        <h4 className="text-sm font-bold text-slate-900 dark:text-white">Task Execution Breakdown</h4>
                                        <span className="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                            {selectedProject.task_counts?.progress_percent || 0}% Completed
                                        </span>
                                    </div>
                                    <div className="mt-3 grid grid-cols-4 gap-2 text-center">
                                        <div className="rounded-xl bg-slate-50 p-2.5 dark:bg-slate-950">
                                            <p className="text-xs text-slate-500">Total</p>
                                            <p className="text-base font-bold text-slate-900 dark:text-white">{selectedProject.task_counts?.total ?? 0}</p>
                                        </div>
                                        <div className="rounded-xl bg-emerald-50 p-2.5 dark:bg-emerald-950/40">
                                            <p className="text-xs text-emerald-700 dark:text-emerald-300">Completed</p>
                                            <p className="text-base font-bold text-emerald-600 dark:text-emerald-400">{selectedProject.task_counts?.completed ?? 0}</p>
                                        </div>
                                        <div className="rounded-xl bg-amber-50 p-2.5 dark:bg-amber-950/40">
                                            <p className="text-xs text-amber-700 dark:text-amber-300">In Progress</p>
                                            <p className="text-base font-bold text-amber-600 dark:text-amber-400">{selectedProject.task_counts?.in_progress ?? 0}</p>
                                        </div>
                                        <div className="rounded-xl bg-slate-100 p-2.5 dark:bg-slate-800">
                                            <p className="text-xs text-slate-600 dark:text-slate-400">Pending</p>
                                            <p className="text-base font-bold text-slate-700 dark:text-slate-300">{selectedProject.task_counts?.pending ?? 0}</p>
                                        </div>
                                    </div>
                                    <div className="mt-3">
                                        <div className="h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                            <div
                                                className="h-full bg-emerald-500 transition-all duration-300"
                                                style={{ width: `${selectedProject.task_counts?.progress_percent || 0}%` }}
                                            />
                                        </div>
                                    </div>
                                </div>

                                {/* Checklist Status Card */}
                                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                                    <div className="flex items-center justify-between border-b border-slate-100 pb-2.5 dark:border-slate-800">
                                        <h4 className="text-sm font-bold text-slate-900 dark:text-white">Checklist Execution Status</h4>
                                        <span className="text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                                            {selectedProject.checklist_counts?.progress_percent || 0}% Done
                                        </span>
                                    </div>
                                    <div className="mt-3 grid grid-cols-3 gap-2 text-center">
                                        <div className="rounded-xl bg-slate-50 p-2.5 dark:bg-slate-950">
                                            <p className="text-xs text-slate-500">Checklists</p>
                                            <p className="text-base font-bold text-slate-900 dark:text-white">{selectedProject.checklist_counts?.total ?? 0}</p>
                                        </div>
                                        <div className="rounded-xl bg-indigo-50 p-2.5 dark:bg-indigo-950/40">
                                            <p className="text-xs text-indigo-700 dark:text-indigo-300">Completed</p>
                                            <p className="text-base font-bold text-indigo-600 dark:text-indigo-400">{selectedProject.checklist_counts?.completed ?? 0}</p>
                                        </div>
                                        <div className="rounded-xl bg-slate-100 p-2.5 dark:bg-slate-800">
                                            <p className="text-xs text-slate-600 dark:text-slate-400">Remaining</p>
                                            <p className="text-base font-bold text-slate-700 dark:text-slate-300">{selectedProject.checklist_counts?.pending ?? 0}</p>
                                        </div>
                                    </div>
                                    <div className="mt-3">
                                        <div className="h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                            <div
                                                className="h-full bg-indigo-500 transition-all duration-300"
                                                style={{ width: `${selectedProject.checklist_counts?.progress_percent || 0}%` }}
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Section 3: Financial & Billing Summary */}
                            <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                                <div className="flex items-center justify-between border-b border-slate-100 pb-2.5 dark:border-slate-800">
                                    <h4 className="text-sm font-bold text-slate-900 dark:text-white">Financial & Billing Overview</h4>
                                    <span className="text-xs text-slate-500">
                                        {selectedProject.billing_summary?.invoices_count ?? 0} Invoices Raised
                                    </span>
                                </div>
                                <div className="mt-3 grid grid-cols-3 gap-3 text-center">
                                    <div className="rounded-xl bg-slate-50 p-3 dark:bg-slate-950">
                                        <p className="text-xs text-slate-500">Total Invoiced</p>
                                        <p className="mt-1 text-base font-bold text-slate-900 dark:text-white">
                                            ₹{Number(selectedProject.billing_summary?.total_billed || 0).toFixed(2)}
                                        </p>
                                    </div>
                                    <div className="rounded-xl bg-emerald-50 p-3 dark:bg-emerald-950/40">
                                        <p className="text-xs text-emerald-700 dark:text-emerald-300">Total Received</p>
                                        <p className="mt-1 text-base font-bold text-emerald-600 dark:text-emerald-400">
                                            ₹{Number(selectedProject.billing_summary?.total_paid || 0).toFixed(2)}
                                        </p>
                                    </div>
                                    <div className="rounded-xl bg-rose-50 p-3 dark:bg-rose-950/40">
                                        <p className="text-xs text-rose-700 dark:text-rose-300">Balance Outstanding</p>
                                        <p className="mt-1 text-base font-bold text-rose-600 dark:text-rose-400">
                                            ₹{Number(selectedProject.billing_summary?.outstanding || 0).toFixed(2)}
                                        </p>
                                    </div>
                                </div>

                                {/* Project Invoices Table */}
                                {Array.isArray(selectedProject.invoices) && selectedProject.invoices.length > 0 ? (
                                    <div className="mt-4 overflow-x-auto">
                                        <table className="min-w-full text-xs">
                                            <thead>
                                                <tr className="border-b border-slate-100 text-left text-slate-400 dark:border-slate-800">
                                                    <th className="py-2 pr-3">Invoice</th>
                                                    <th className="py-2 pr-3">Date</th>
                                                    <th className="py-2 pr-3">Total</th>
                                                    <th className="py-2 pr-3">Paid</th>
                                                    <th className="py-2 pr-3">Balance</th>
                                                    <th className="py-2 pr-3">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                                {selectedProject.invoices.map((inv) => (
                                                    <tr key={inv.id}>
                                                        <td className="py-2 pr-3 font-semibold text-slate-900 dark:text-white">{inv.invoice_code}</td>
                                                        <td className="py-2 pr-3 text-slate-600 dark:text-slate-400">{formatDate(inv.invoice_date)}</td>
                                                        <td className="py-2 pr-3 font-medium text-slate-900 dark:text-white">₹{Number(inv.total_amount).toFixed(2)}</td>
                                                        <td className="py-2 pr-3 text-emerald-600 dark:text-emerald-400">₹{Number(inv.paid_amount).toFixed(2)}</td>
                                                        <td className="py-2 pr-3 font-medium text-rose-600 dark:text-rose-400">₹{Number(inv.balance_amount).toFixed(2)}</td>
                                                        <td className="py-2 pr-3"><StatusBadge value={inv.status} /></td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                ) : null}
                            </div>
                        </div>

                        {/* Modal Footer */}
                        <div className="flex items-center justify-end gap-3 border-t border-slate-100 px-6 py-4 dark:border-slate-800">
                            <button
                                type="button"
                                onClick={() => setSelectedProject(null)}
                                className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                ) : null}
            </Modal>

            {/* MODAL 2: EDIT DRAFT INVOICE MODAL */}
            <Modal show={editingInvoice !== null} onClose={closeEditInvoiceModal} maxWidth="3xl">
                <div className="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h3 className="text-lg font-semibold text-slate-900 dark:text-white">Edit Draft Invoice</h3>
                    <p className="mt-1 text-sm text-slate-500">Only draft invoices can be edited. Issued invoices are immutable.</p>
                </div>
                <form onSubmit={submitEditInvoice} className="space-y-4 p-5">
                    <div className="grid gap-4 md:grid-cols-2">
                        <TextInput
                            label="Invoice Date"
                            type="date"
                            value={editInvoiceForm.data.invoice_date}
                            onChange={(value) => editInvoiceForm.setData("invoice_date", value)}
                            error={editInvoiceForm.errors.invoice_date}
                        />
                        <TextInput
                            label="Due Date"
                            type="date"
                            value={editInvoiceForm.data.due_date}
                            onChange={(value) => editInvoiceForm.setData("due_date", value)}
                            error={editInvoiceForm.errors.due_date}
                        />
                        <SelectInput
                            label="Tax Type"
                            value={editInvoiceForm.data.tax_type}
                            onChange={(value) => editInvoiceForm.setData("tax_type", value)}
                            options={[
                                { value: "intra", label: "Intra-state (CGST+SGST)" },
                                { value: "inter", label: "Inter-state (IGST)" },
                            ]}
                            error={editInvoiceForm.errors.tax_type}
                        />
                    </div>
                    <LineItemsEditor items={editInvoiceItems} onChange={setEditInvoiceItems} errors={editInvoiceForm.errors} />
                    <TextAreaInput
                        label="Notes"
                        value={editInvoiceForm.data.notes}
                        onChange={(value) => editInvoiceForm.setData("notes", value)}
                        error={editInvoiceForm.errors.notes}
                    />
                    <div className="flex items-center justify-end gap-3 pt-2">
                        <button
                            type="button"
                            onClick={closeEditInvoiceModal}
                            disabled={editInvoiceForm.processing}
                            className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={editInvoiceForm.processing}
                            className="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-500 disabled:opacity-60"
                        >
                            {editInvoiceForm.processing ? "Updating..." : "Update Draft Invoice"}
                        </button>
                    </div>
                </form>
            </Modal>
        </ConstructionShell>
    );
}

function formatDate(dateString) {
    if (!dateString) return null;
    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) return dateString;
    return date.toLocaleDateString("en-IN", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    });
}

function TextInput({ label, error, value, onChange, type = "text" }) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</span>
            <input
                type={type}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-indigo-400 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
            />
            {error ? <p className="mt-2 text-xs text-rose-600">{error}</p> : null}
        </label>
    );
}

function TextAreaInput({ label, error, value, onChange }) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</span>
            <textarea
                value={value}
                onChange={(event) => onChange(event.target.value)}
                rows={3}
                className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-indigo-400 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
            />
            {error ? <p className="mt-2 text-xs text-rose-600">{error}</p> : null}
        </label>
    );
}

function SelectInput({ label, error, value, onChange, options }) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</span>
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-indigo-400 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
            >
                {options.map((option) => (
                    <option key={`${option.value}-${option.label}`} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            {error ? <p className="mt-2 text-xs text-rose-600">{error}</p> : null}
        </label>
    );
}

function LineItemsEditor({ items, onChange, errors }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
            <div className="flex items-center justify-between gap-3">
                <p className="text-sm font-semibold text-slate-900 dark:text-white">Invoice Items</p>
                <button
                    type="button"
                    className="rounded-xl bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    onClick={() => onChange([...items, { description: "", quantity: "1", unit: "", rate: "", gst_percent: "18" }])}
                >
                    Add Item
                </button>
            </div>
            <div className="mt-4 space-y-4">
                {items.map((item, index) => (
                    <div key={index} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                        <div className="grid gap-4 md:grid-cols-2">
                            <TextInput
                                label="Description"
                                value={item.description}
                                onChange={(value) => {
                                    const next = [...items];
                                    next[index] = { ...next[index], description: value };
                                    onChange(next);
                                }}
                                error={errors?.[`items.${index}.description`]}
                            />
                            <TextInput
                                label="Quantity"
                                value={item.quantity}
                                onChange={(value) => {
                                    const next = [...items];
                                    next[index] = { ...next[index], quantity: value };
                                    onChange(next);
                                }}
                                error={errors?.[`items.${index}.quantity`]}
                            />
                            <TextInput
                                label="Unit"
                                value={item.unit}
                                onChange={(value) => {
                                    const next = [...items];
                                    next[index] = { ...next[index], unit: value };
                                    onChange(next);
                                }}
                                error={errors?.[`items.${index}.unit`]}
                            />
                            <TextInput
                                label="Rate"
                                value={item.rate}
                                onChange={(value) => {
                                    const next = [...items];
                                    next[index] = { ...next[index], rate: value };
                                    onChange(next);
                                }}
                                error={errors?.[`items.${index}.rate`]}
                            />
                            <TextInput
                                label="GST %"
                                value={item.gst_percent}
                                onChange={(value) => {
                                    const next = [...items];
                                    next[index] = { ...next[index], gst_percent: value };
                                    onChange(next);
                                }}
                                error={errors?.[`items.${index}.gst_percent`]}
                            />
                        </div>
                        <div className="mt-3 flex justify-end">
                            <button
                                type="button"
                                className="rounded-xl bg-rose-600 px-3 py-2 text-sm font-medium text-white hover:bg-rose-500"
                                onClick={() => {
                                    const next = items.filter((_, i) => i !== index);
                                    onChange(next.length ? next : [{ description: "", quantity: "1", unit: "", rate: "", gst_percent: "18" }]);
                                }}
                            >
                                Remove
                            </button>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

function PrimaryButton({ processing, label }) {
    return (
        <button
            type="submit"
            disabled={processing}
            className="w-full rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:bg-indigo-400"
        >
            {processing ? "Saving..." : label}
        </button>
    );
}
