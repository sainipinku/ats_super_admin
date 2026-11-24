import { useForm, usePage } from "@inertiajs/react";
import { useMemo, useState } from "react";
import {
    AlertCircle,
    CalendarDays,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Download,
    Eye,
    FileText,
    FolderKanban,
    Percent,
    RotateCcw,
    Scale,
    Search,
    Wallet,
    UsersRound,
} from "lucide-react";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
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
    const [periodMonths, setPeriodMonths] = useState(6);
    const [projectFilter, setProjectFilter] = useState("all");
    const [clientFilter, setClientFilter] = useState("all");
    const [paymentStatusFilter, setPaymentStatusFilter] = useState("all");
    const [appliedFilters, setAppliedFilters] = useState({ project: "all", client: "all", paymentStatus: "all" });
    const [currentPage, setCurrentPage] = useState(1);
    const [pageSize, setPageSize] = useState(10);

    // Modal state for project details
    const [selectedProject, setSelectedProject] = useState(null);

    // Download Report with Date Range Modal state
    const [isDownloadModalOpen, setIsDownloadModalOpen] = useState(false);
    const [downloadStartDate, setDownloadStartDate] = useState(() => {
        const d = new Date();
        d.setDate(1);
        return d.toISOString().slice(0, 10);
    });
    const [downloadEndDate, setDownloadEndDate] = useState(() => new Date().toISOString().slice(0, 10));
    const [downloadReportType, setDownloadReportType] = useState("directory");
    const [activeDatePreset, setActiveDatePreset] = useState("this_month");

    // Global Stats calculation
    const stats = useMemo(() => {
        const totalBilled = projects.reduce((sum, project) => sum + Number(project.billing_summary?.total_billed || 0), 0);
        const totalReceived = projects.reduce((sum, project) => sum + Number(project.billing_summary?.total_paid || 0), 0);
        const outstanding = projects.reduce((sum, project) => sum + Number(project.billing_summary?.outstanding || 0), 0);

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
            if (appliedFilters.project !== "all" && String(project.id) !== appliedFilters.project) {
                return false;
            }

            const clientId = project.client?.id ? String(project.client.id) : "";
            if (appliedFilters.client !== "all" && clientId !== appliedFilters.client) {
                return false;
            }

            const billing = project.billing_summary || {};
            const billed = Number(billing.total_billed || 0);
            const outstanding = Number(billing.outstanding || 0);
            const paymentStatus = billed > 0 && outstanding <= 0 ? "paid" : "pending";
            if (appliedFilters.paymentStatus !== "all" && paymentStatus !== appliedFilters.paymentStatus) {
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
    }, [projects, searchQuery, appliedFilters]);

    const billingChartData = useMemo(() => {
        const now = new Date();
        const firstMonth = new Date(now.getFullYear(), now.getMonth() - periodMonths + 1, 1);
        const monthRows = Array.from({ length: periodMonths }, (_, index) => {
            const date = new Date(firstMonth.getFullYear(), firstMonth.getMonth() + index, 1);
            return {
                key: `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}`,
                month: date.toLocaleDateString("en-IN", { month: "short" }),
                billed: 0,
                received: 0,
            };
        });
        const rowByMonth = new Map(monthRows.map((row) => [row.key, row]));

        invoices.forEach((invoice) => {
            const date = parseBillingDate(invoice.invoice_date);
            const row = date ? rowByMonth.get(monthKey(date)) : null;
            if (row) row.billed += Number(invoice.total_amount || 0);
        });

        payments.forEach((payment) => {
            const date = parseBillingDate(payment.received_at);
            const row = date ? rowByMonth.get(monthKey(date)) : null;
            if (row) row.received += Number(payment.amount || 0);
        });

        const totalBilled = monthRows.reduce((sum, row) => sum + row.billed, 0);
        const totalReceived = monthRows.reduce((sum, row) => sum + row.received, 0);
        return monthRows.map((row) => ({
            ...row,
            billedPercent: percentageValue(row.billed, totalBilled),
            receivedPercent: percentageValue(row.received, totalReceived),
        }));
    }, [invoices, payments, periodMonths]);

    const projectBillingChartData = useMemo(() => {
        const rows = projects
            .map((project) => ({
                name: project.name || project.project_code || "Project",
                billed: Number(project.billing_summary?.total_billed || 0),
                received: Number(project.billing_summary?.total_paid || 0),
            }))
            .sort((a, b) => b.billed - a.billed);
        const topProjects = rows.slice(0, 4);
        const otherProjects = rows.slice(4);
        if (otherProjects.length > 0) {
            topProjects.push({
                name: "Others",
                billed: otherProjects.reduce((sum, project) => sum + project.billed, 0),
                received: otherProjects.reduce((sum, project) => sum + project.received, 0),
            });
        }
        const totalBilled = topProjects.reduce((sum, project) => sum + project.billed, 0);
        return topProjects.map((project) => ({
            ...project,
            sharePercent: percentageValue(project.billed, totalBilled),
        }));
    }, [projects]);

    const paymentStatusData = useMemo(() => {
        const totalBilled = stats.totalBilled;
        return [
            { name: "Paid", value: percentageValue(stats.totalReceived, totalBilled), color: "#10b981" },
            { name: "Pending", value: percentageValue(stats.outstanding, totalBilled), color: "#f59e0b" },
        ];
    }, [stats]);

    const clientOptions = useMemo(() => {
        const clients = new Map();
        projects.forEach((project) => {
            if (project.client?.id) {
                clients.set(String(project.client.id), project.client.name || "Client");
            }
        });
        return [...clients.entries()].sort((a, b) => a[1].localeCompare(b[1]));
    }, [projects]);

    const pageCount = Math.max(1, Math.ceil(filteredProjects.length / pageSize));
    const visibleProjects = filteredProjects.slice((currentPage - 1) * pageSize, currentPage * pageSize);

    // Date preset helper for the Download Report Modal
    const handleSetDatePreset = (preset) => {
        setActiveDatePreset(preset);
        const today = new Date();
        const formatDate = (date) => date.toISOString().slice(0, 10);

        if (preset === "today") {
            const todayStr = formatDate(today);
            setDownloadStartDate(todayStr);
            setDownloadEndDate(todayStr);
        } else if (preset === "this_week") {
            const dayOfWeek = today.getDay();
            const start = new Date(today);
            start.setDate(today.getDate() - (dayOfWeek === 0 ? 6 : dayOfWeek - 1));
            setDownloadStartDate(formatDate(start));
            setDownloadEndDate(formatDate(today));
        } else if (preset === "this_month") {
            const start = new Date(today.getFullYear(), today.getMonth(), 1);
            setDownloadStartDate(formatDate(start));
            setDownloadEndDate(formatDate(today));
        } else if (preset === "last_30_days") {
            const start = new Date(today);
            start.setDate(today.getDate() - 30);
            setDownloadStartDate(formatDate(start));
            setDownloadEndDate(formatDate(today));
        } else if (preset === "this_year") {
            const start = new Date(today.getFullYear(), 0, 1);
            setDownloadStartDate(formatDate(start));
            setDownloadEndDate(formatDate(today));
        } else if (preset === "all_time") {
            setDownloadStartDate("");
            setDownloadEndDate("");
        }
    };

    // Filter projects strictly matching the selected date range
    const filteredProjectsByDateRange = useMemo(() => {
        if (!downloadStartDate && !downloadEndDate) {
            return filteredProjects;
        }
        return filteredProjects.filter((p) => {
            const pStart = p.start_date || (p.created_at ? String(p.created_at).slice(0, 10) : null);
            const pEnd = p.expected_end_date || p.start_date || (p.created_at ? String(p.created_at).slice(0, 10) : null);
            const pCreated = p.created_at ? String(p.created_at).slice(0, 10) : null;

            // Check if any invoice of this project falls in the date range
            const hasInvoiceInRange = Array.isArray(p.invoices) && p.invoices.some((inv) => {
                const invDate = inv.invoice_date ? String(inv.invoice_date).slice(0, 10) : null;
                if (!invDate) return false;
                if (downloadStartDate && invDate < downloadStartDate) return false;
                if (downloadEndDate && invDate > downloadEndDate) return false;
                return true;
            });

            if (hasInvoiceInRange) return true;

            if (downloadStartDate && downloadEndDate) {
                if (pStart && pStart >= downloadStartDate && pStart <= downloadEndDate) return true;
                if (pEnd && pEnd >= downloadStartDate && pEnd <= downloadEndDate) return true;
                if (pCreated && pCreated >= downloadStartDate && pCreated <= downloadEndDate) return true;
                if (pStart && pEnd && pStart <= downloadStartDate && pEnd >= downloadEndDate) return true;
                return false;
            } else if (downloadStartDate) {
                return (pEnd && pEnd >= downloadStartDate) || (pStart && pStart >= downloadStartDate) || (pCreated && pCreated >= downloadStartDate);
            } else if (downloadEndDate) {
                return (pStart && pStart <= downloadEndDate) || (pCreated && pCreated <= downloadEndDate);
            }
            return true;
        });
    }, [filteredProjects, downloadStartDate, downloadEndDate]);

    // Filter invoices strictly matching the selected date range
    const filteredInvoicesByDateRange = useMemo(() => {
        if (!downloadStartDate && !downloadEndDate) {
            return invoices;
        }
        return invoices.filter((inv) => {
            const invDate = inv.invoice_date ? String(inv.invoice_date).slice(0, 10) : (inv.created_at ? String(inv.created_at).slice(0, 10) : null);
            if (!invDate) return true;
            if (downloadStartDate && invDate < downloadStartDate) return false;
            if (downloadEndDate && invDate > downloadEndDate) return false;
            return true;
        });
    }, [invoices, downloadStartDate, downloadEndDate]);

    // Export handler for filtered date-range data
    const handleDownloadReportWithDateRange = () => {
        const startStr = downloadStartDate || "all";
        const endStr = downloadEndDate || "all";
        const rangeLabel = downloadStartDate && downloadEndDate 
            ? `${downloadStartDate} to ${downloadEndDate}` 
            : (downloadStartDate ? `From ${downloadStartDate}` : (downloadEndDate ? `Until ${downloadEndDate}` : "All Time"));

        const escapeCsv = (val) => {
            if (val === null || val === undefined) return '""';
            const str = String(val).replace(/"/g, '""');
            return `"${str}"`;
        };

        let csvContent = "\uFEFF";
        let filename = "";

        if (downloadReportType === "invoices") {
            filename = `invoices_billing_report_${startStr}_to_${endStr}.csv`;
            const invoiceHeaders = [
                "Invoice Code",
                "Project Code",
                "Project Name",
                "Client Name",
                "Invoice Date",
                "Due Date",
                "Total Amount (INR)",
                "Paid Amount (INR)",
                "Balance Amount (INR)",
                "Status",
                "Tax Type",
                "Notes",
            ];

            const metaRows = [
                `"# REPORT TYPE","Invoices & Payments Statement"`,
                `"# DATE RANGE","${rangeLabel}"`,
                `"# GENERATED AT","${new Date().toLocaleString()}"`,
                `"# MATCHING RECORDS","${filteredInvoicesByDateRange.length}"`,
                `""`,
            ];

            const dataRows = filteredInvoicesByDateRange.map((inv) => [
                escapeCsv(inv.invoice_code || ""),
                escapeCsv(inv.project?.project_code || ""),
                escapeCsv(inv.project?.name || ""),
                escapeCsv(inv.project?.client?.name || inv.client?.name || ""),
                escapeCsv(inv.invoice_date || ""),
                escapeCsv(inv.due_date || ""),
                escapeCsv(inv.total_amount ?? 0),
                escapeCsv(inv.paid_amount ?? 0),
                escapeCsv(inv.balance_amount ?? 0),
                escapeCsv(inv.status || ""),
                escapeCsv(inv.tax_type || ""),
                escapeCsv(inv.notes || ""),
            ]);

            csvContent += [...metaRows, invoiceHeaders.join(","), ...dataRows.map((r) => r.join(","))].join("\r\n");
        } else {
            filename = `projects_billing_report_${startStr}_to_${endStr}.csv`;
            const headers = [
                "Project Code",
                "Project Name",
                "Stage",
                "Status",
                "Priority",
                "Start Date",
                "Expected End Date",
                "Created Date",
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
                "Total Billed (INR)",
                "Total Paid (INR)",
                "Outstanding (INR)",
                "Collected (%)",
                "Outstanding (%)",
            ];

            const metaRows = [
                `"# REPORT TYPE","Projects & Clients Billing Directory"`,
                `"# DATE RANGE","${rangeLabel}"`,
                `"# GENERATED AT","${new Date().toLocaleString()}"`,
                `"# MATCHING PROJECTS","${filteredProjectsByDateRange.length}"`,
                `""`,
            ];

            const dataRows = filteredProjectsByDateRange.map((p) => [
                escapeCsv(p.project_code || ""),
                escapeCsv(p.name || ""),
                escapeCsv(p.current_stage || ""),
                escapeCsv(p.status || ""),
                escapeCsv(p.priority || ""),
                escapeCsv(p.start_date || ""),
                escapeCsv(p.expected_end_date || ""),
                escapeCsv(p.created_at || ""),
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
                escapeCsv(p.billing_summary?.total_billed ?? 0),
                escapeCsv(p.billing_summary?.total_paid ?? 0),
                escapeCsv(p.billing_summary?.outstanding ?? 0),
                escapeCsv(formatPercentage(p.billing_summary?.total_paid, p.billing_summary?.total_billed)),
                escapeCsv(formatPercentage(p.billing_summary?.outstanding, p.billing_summary?.total_billed)),
            ]);

            csvContent += [...metaRows, headers.join(","), ...dataRows.map((r) => r.join(","))].join("\r\n");
        }

        const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.setAttribute("href", url);
        link.setAttribute("download", filename);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        setIsDownloadModalOpen(false);
    };

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
            "Collected (%)",
            "Outstanding (%)",
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
            escapeCsv(formatPercentage(p.billing_summary?.total_paid, p.billing_summary?.total_billed)),
            escapeCsv(formatPercentage(p.billing_summary?.outstanding, p.billing_summary?.total_billed)),
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
            <section className="space-y-4">
                <div className="flex items-center justify-end">
                    <label className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-600 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
                        <CalendarDays className="h-4 w-4 text-indigo-500" />
                        <select
                            aria-label="Chart date range"
                            value={periodMonths}
                            onChange={(event) => setPeriodMonths(Number(event.target.value))}
                            className="border-0 bg-transparent py-0 pl-0 pr-6 text-xs font-semibold text-slate-700 outline-none focus:ring-0 dark:text-slate-200"
                        >
                            <option value={3}>Last 3 Months</option>
                            <option value={6}>Last 6 Months</option>
                            <option value={12}>Last 12 Months</option>
                        </select>
                    </label>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    <BillingMetricCard label="Total Projects" value={stats.projects} icon={FolderKanban} color="violet" />
                    <BillingMetricCard label="Registered Clients" value={stats.clients} icon={UsersRound} color="sky" />
                    <BillingMetricCard label="Execution Tasks" value={`${stats.completedTasks} / ${stats.totalTasks}`} icon={CheckCircle2} color="emerald" />
                    <BillingMetricCard
                        label="Projects Billed"
                        value={formatPercentage(projects.filter((project) => Number(project.billing_summary?.total_billed || 0) > 0).length, stats.projects)}
                        hint="of all projects"
                        icon={Percent}
                        color="orange"
                    />
                    <BillingMetricCard label="Collected Rate" value={formatPercentage(stats.totalReceived, stats.totalBilled)} hint="of billed value" icon={Wallet} color="teal" />
                    <BillingMetricCard label="Outstanding Rate" value={formatPercentage(stats.outstanding, stats.totalBilled)} hint="of billed value" icon={Scale} color="blue" />
                </div>

                <div className="grid gap-3 xl:grid-cols-3">
                    <ChartCard title="Monthly Billing Share" icon={Percent} accent="violet">
                        <BillingBarChart data={billingChartData} />
                        <ChartLegend items={[{ label: "Billed share", color: "#6d36e8" }, { label: "Received share", color: "#3b82f6" }]} />
                    </ChartCard>

                    <ChartCard title="Project-wise Billing" icon={FolderKanban} accent="blue">
                        <div className="flex h-44 items-center justify-center gap-3">
                            <DonutChart
                                data={projectBillingChartData.map((project, index) => ({
                                    name: project.name,
                                    value: project.sharePercent,
                                    color: ["#6d36e8", "#3b82f6", "#14b8a6", "#f59e0b", "#a78bfa"][index % 5],
                                }))}
                                centerValue={projectBillingChartData.some((project) => project.billed > 0) ? "100%" : "—"}
                                centerLabel="of billed value"
                            />
                            <div className="min-w-0 flex-1 space-y-2">
                                {projectBillingChartData.slice(0, 4).map((project, index) => (
                                    <div key={`${project.name}-${index}`} className="flex items-center gap-2 text-[10px]">
                                        <span className="h-2 w-2 shrink-0 rounded-full" style={{ backgroundColor: ["#6d36e8", "#3b82f6", "#14b8a6", "#f59e0b", "#a78bfa"][index % 5] }} />
                                        <span className="min-w-0 flex-1 truncate text-slate-600 dark:text-slate-300">{project.name}</span>
                                        <span className="shrink-0 font-semibold text-slate-700 dark:text-slate-200">{formatPercentage(project.sharePercent, 100)}</span>
                                    </div>
                                ))}
                                {projectBillingChartData.length === 0 ? <p className="text-xs text-slate-400">No project billing data yet.</p> : null}
                            </div>
                        </div>
                        <p className="text-center text-[10px] text-slate-500">Project share of billed value</p>
                    </ChartCard>

                    <ChartCard title="Payment Status" icon={CheckCircle2} accent="emerald">
                        <div className="flex h-44 items-center justify-center gap-3">
                            <DonutChart
                                data={paymentStatusData}
                                centerValue={stats.totalBilled > 0 ? `${Math.round((stats.totalReceived / stats.totalBilled) * 100)}%` : "—"}
                                centerLabel={stats.totalBilled > 0 ? "Received" : "No billing"}
                            />
                            <div className="space-y-3 text-[10px]">
                                {paymentStatusData.map((entry) => (
                                    <div key={entry.name} className="flex items-center gap-2">
                                        <span className="h-2 w-2 rounded-full" style={{ backgroundColor: entry.color }} />
                                        <span className="text-slate-600 dark:text-slate-300">{entry.name}</span>
                                        <span className="ml-1 font-semibold text-slate-700 dark:text-slate-200">{stats.totalBilled > 0 ? formatPercentage(entry.value, 100) : "—"}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </ChartCard>
                </div>
            </section>

            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        onClick={() => setActiveTab("directory")}
                        className={`flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-semibold transition sm:text-sm ${
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
                        className={`flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-semibold transition sm:text-sm ${
                            activeTab === "invoices"
                                ? "bg-indigo-600 text-white shadow-sm"
                                : "bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                        }`}
                    >
                        <FileText className="h-4 w-4" />
                        Invoices & Payments Manager
                        <span className={`ml-1.5 rounded-full px-2 py-0.5 text-xs ${activeTab === "invoices" ? "bg-indigo-500 text-white" : "bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300"}`}>
                            {invoices.length}
                        </span>
                    </button>
                </div>

                <div className="flex items-center gap-3">
                    <button
                        type="button"
                        onClick={() => {
                            setDownloadReportType(activeTab === "invoices" ? "invoices" : "directory");
                            setIsDownloadModalOpen(true);
                        }}
                        className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-500 sm:text-sm"
                    >
                        <Download className="h-4 w-4" />
                        Download Report
                    </button>
                </div>
            </div>

            {/* TAB 1: PROJECTS & CLIENTS DIRECTORY */}
            {activeTab === "directory" ? (
                <div className="space-y-4">
                    {/* Filter and Search Bar */}
                    <div className="grid gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900 xl:grid-cols-[minmax(240px,1.5fr)_repeat(3,minmax(140px,0.6fr))_auto_auto]">
                        <label className="relative block">
                            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                type="search"
                                value={searchQuery}
                                onChange={(event) => {
                                    setSearchQuery(event.target.value);
                                    setCurrentPage(1);
                                }}
                                placeholder="Search by project, client, khasra no, phone..."
                                className="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-9 pr-3 text-xs text-slate-900 outline-none transition focus:border-indigo-500 focus:bg-white dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                            />
                        </label>
                        <select aria-label="Filter by project" value={projectFilter} onChange={(event) => setProjectFilter(event.target.value)} className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs text-slate-700 outline-none focus:border-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                            <option value="all">All Projects</option>
                            {projects.map((project) => <option key={project.id} value={String(project.id)}>{project.project_code} • {project.name}</option>)}
                        </select>
                        <select aria-label="Filter by client" value={clientFilter} onChange={(event) => setClientFilter(event.target.value)} className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs text-slate-700 outline-none focus:border-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                            <option value="all">All Clients</option>
                            {clientOptions.map(([id, name]) => <option key={id} value={id}>{name}</option>)}
                        </select>
                        <select aria-label="Filter by payment status" value={paymentStatusFilter} onChange={(event) => setPaymentStatusFilter(event.target.value)} className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs text-slate-700 outline-none focus:border-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                            <option value="all">All Status</option>
                            <option value="paid">Paid</option>
                            <option value="pending">Pending</option>
                        </select>
                        <button
                            type="button"
                            onClick={() => {
                                setAppliedFilters({ project: projectFilter, client: clientFilter, paymentStatus: paymentStatusFilter });
                                setCurrentPage(1);
                            }}
                            className="rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-indigo-500"
                        >
                            Apply Filter
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                setSearchQuery("");
                                setProjectFilter("all");
                                setClientFilter("all");
                                setPaymentStatusFilter("all");
                                setAppliedFilters({ project: "all", client: "all", paymentStatus: "all" });
                                setCurrentPage(1);
                            }}
                            className="inline-flex items-center justify-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                        >
                            <RotateCcw className="h-3.5 w-3.5" />
                            Reset
                        </button>
                    </div>

                    {/* Listing Table */}
                    <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        {filteredProjects.length === 0 ? (
                            <div className="py-12">
                                <EmptyState
                                    title="No projects match your filter."
                                    description="Try changing your search or filters to view project records."
                                />
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-slate-200 text-left text-sm dark:divide-slate-800">
                                    <thead className="bg-indigo-50/70 text-[10px] font-semibold uppercase tracking-wide text-slate-600 dark:bg-slate-950 dark:text-slate-400">
                                        <tr>
                                            <th className="px-3 py-3">#</th>
                                            <th className="px-3 py-3">Project Details</th>
                                            <th className="px-3 py-3">Client Details</th>
                                            <th className="px-3 py-3">Khasra / Site Area</th>
                                            <th className="px-3 py-3">Billing Info</th>
                                            <th className="px-3 py-3">Payment Status</th>
                                            <th className="px-3 py-3 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
                                        {visibleProjects.map((project, index) => {
                                            const billing = project.billing_summary || {};
                                            const client = project.client || {};
                                            const paid = Number(billing.total_paid || 0);
                                            const due = Number(billing.outstanding || 0);
                                            const billed = Number(billing.total_billed || 0);
                                            const isPaid = billed > 0 && due <= 0;

                                            return (
                                                <tr key={project.id} className="transition hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                                    <td className="px-3 py-3 text-xs text-slate-500">{(currentPage - 1) * pageSize + index + 1}</td>
                                                    {/* Project */}
                                                    <td className="px-3 py-3">
                                                        <div className="flex items-center gap-2.5">
                                                            <span className="flex h-9 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300"><FolderKanban className="h-4 w-4" /></span>
                                                            <div className="min-w-0">
                                                                <p className="truncate text-xs font-semibold text-slate-900 dark:text-white">{project.name}</p>
                                                                <p className="mt-0.5 text-[10px] text-slate-500">{project.location_name || project.project_address || project.project_code}</p>
                                                                <span className="mt-1 inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-[9px] font-semibold capitalize text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">{project.current_stage || "Planning"}</span>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    {/* Client Details */}
                                                    <td className="px-3 py-3">
                                                        <p className="text-xs font-semibold text-slate-800 dark:text-slate-100">{client.name || "N/A"}</p>
                                                        {client.phone && client.phone !== "—" ? <p className="mt-0.5 text-[10px] text-slate-500">{client.phone}</p> : null}
                                                        {client.email && client.email !== "—" ? <p className="text-[10px] text-slate-400">{client.email}</p> : null}
                                                    </td>

                                                    {/* Khasra / Site Area */}
                                                    <td className="px-3 py-3">
                                                        <span className="inline-flex rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-[10px] font-medium text-amber-800 dark:border-amber-800/50 dark:bg-amber-950/40 dark:text-amber-300">Khasra: {project.khasra_number || "—"}</span>
                                                        {project.site_area && project.site_area !== "—" ? (
                                                            <p className="mt-1 text-[10px] text-slate-500">Area: {project.site_area}</p>
                                                        ) : null}
                                                    </td>

                                                    {/* Billing Snapshot */}
                                                    <td className="px-3 py-3">
                                                        <p className="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">Collected: {formatPercentage(paid, billed)}</p>
                                                        <p className={`text-[10px] font-medium ${due > 0 ? "text-rose-600 dark:text-rose-400" : "text-slate-400"}`}>Outstanding: {formatPercentage(due, billed)}</p>
                                                    </td>
                                                    <td className="px-3 py-3">
                                                        <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-semibold ${isPaid ? "bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300" : "bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300"}`}>
                                                            {isPaid ? <CheckCircle2 className="h-3 w-3" /> : <AlertCircle className="h-3 w-3" />}
                                                            {isPaid ? "Paid" : "Pending"}
                                                        </span>
                                                    </td>

                                                    {/* Action */}
                                                    <td className="px-3 py-3 text-right">
                                                        <button
                                                            type="button"
                                                            onClick={() => setSelectedProject(project)}
                                                            className="inline-flex items-center gap-1 rounded-lg border border-indigo-200 bg-white px-2.5 py-1.5 text-[10px] font-semibold text-indigo-700 transition hover:bg-indigo-50 dark:border-indigo-800 dark:bg-slate-900 dark:text-indigo-300 dark:hover:bg-indigo-950/50"
                                                        >
                                                            <Eye className="h-3 w-3" />
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
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-3 py-2.5 text-[10px] text-slate-500 dark:border-slate-800">
                            <span>Showing {filteredProjects.length ? (currentPage - 1) * pageSize + 1 : 0} to {Math.min(currentPage * pageSize, filteredProjects.length)} of {filteredProjects.length} projects</span>
                            <div className="flex items-center gap-2">
                                <button type="button" aria-label="Previous page" disabled={currentPage <= 1} onClick={() => setCurrentPage((page) => Math.max(1, page - 1))} className="rounded-md border border-slate-200 p-1 disabled:opacity-40 dark:border-slate-700"><ChevronLeft className="h-3.5 w-3.5" /></button>
                                <span className="rounded-md bg-indigo-600 px-2 py-1 font-semibold text-white">{currentPage}</span>
                                <button type="button" aria-label="Next page" disabled={currentPage >= pageCount} onClick={() => setCurrentPage((page) => Math.min(pageCount, page + 1))} className="rounded-md border border-slate-200 p-1 disabled:opacity-40 dark:border-slate-700"><ChevronRight className="h-3.5 w-3.5" /></button>
                                <label className="ml-1 inline-flex items-center gap-1.5">
                                    Show:
                                    <select aria-label="Rows per page" value={pageSize} onChange={(event) => { setPageSize(Number(event.target.value)); setCurrentPage(1); }} className="rounded-md border border-slate-200 bg-white px-2 py-1 text-[10px] dark:border-slate-700 dark:bg-slate-900">
                                        <option value={10}>10</option>
                                        <option value={25}>25</option>
                                        <option value={50}>50</option>
                                    </select>
                                </label>
                            </div>
                        </div>
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
                                                    label: `${invoice.invoice_code} • ${formatPercentage(invoice.balance_amount, invoice.total_amount)} outstanding`,
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
                                                <th className="py-2 pr-4">Paid %</th>
                                                <th className="py-2 pr-4">Balance %</th>
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
                                                    <td className="py-3 pr-4 text-emerald-600 dark:text-emerald-400">
                                                        {formatPercentage(invoice.paid_amount, invoice.total_amount)}
                                                    </td>
                                                    <td className="py-3 pr-4 text-amber-600 dark:text-amber-400">
                                                        {formatPercentage(invoice.balance_amount, invoice.total_amount)}
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
                                                <th className="py-2 pr-4">Invoice %</th>
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
                                                        {formatPercentage(payment.amount, payment.invoice?.total_amount)}
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
                                        <span className="sr-only">Task Breakdown</span>
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
                                        <span className="sr-only">Checklist Progress</span>
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
                                        <p className="text-xs text-slate-500">Invoice Coverage</p>
                                        <p className="mt-1 text-base font-bold text-slate-900 dark:text-white">
                                            {Number(selectedProject.billing_summary?.total_billed || 0) > 0 ? "100%" : "—"}
                                        </p>
                                    </div>
                                    <div className="rounded-xl bg-emerald-50 p-3 dark:bg-emerald-950/40">
                                        <p className="text-xs text-emerald-700 dark:text-emerald-300">Collected</p>
                                        <p className="mt-1 text-base font-bold text-emerald-600 dark:text-emerald-400">
                                            {formatPercentage(selectedProject.billing_summary?.total_paid, selectedProject.billing_summary?.total_billed)}
                                        </p>
                                    </div>
                                    <div className="rounded-xl bg-rose-50 p-3 dark:bg-rose-950/40">
                                        <p className="text-xs text-rose-700 dark:text-rose-300">Outstanding</p>
                                        <p className="mt-1 text-base font-bold text-rose-600 dark:text-rose-400">
                                            {formatPercentage(selectedProject.billing_summary?.outstanding, selectedProject.billing_summary?.total_billed)}
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
                                                    <th className="py-2 pr-3">Paid %</th>
                                                    <th className="py-2 pr-3">Outstanding %</th>
                                                    <th className="py-2 pr-3">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                                {selectedProject.invoices.map((inv) => (
                                                    <tr key={inv.id}>
                                                        <td className="py-2 pr-3 font-semibold text-slate-900 dark:text-white">{inv.invoice_code}</td>
                                                        <td className="py-2 pr-3 text-slate-600 dark:text-slate-400">{formatDate(inv.invoice_date)}</td>
                                                        <td className="py-2 pr-3 text-emerald-600 dark:text-emerald-400">{formatPercentage(inv.paid_amount, inv.total_amount)}</td>
                                                        <td className="py-2 pr-3 text-rose-600 dark:text-rose-400">{formatPercentage(inv.balance_amount, inv.total_amount)}</td>
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

            {/* DATE RANGE DOWNLOAD REPORT MODAL */}
            <Modal show={isDownloadModalOpen} onClose={() => setIsDownloadModalOpen(false)} maxWidth="2xl">
                <div className="flex flex-col">
                    {/* Modal Header */}
                    <div className="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                                <Download className="h-5 w-5" />
                            </div>
                            <div>
                                <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                    Download Report
                                </h3>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    Select date range to filter and download inner sheet data
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            onClick={() => setIsDownloadModalOpen(false)}
                            className="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                        >
                            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {/* Modal Body */}
                    <div className="space-y-5 px-6 py-5">
                        {/* 1. Quick Presets */}
                        <div>
                            <label className="mb-2 block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Quick Date Presets
                            </label>
                            <div className="flex flex-wrap gap-2">
                                {[
                                    { id: "today", label: "Today" },
                                    { id: "this_week", label: "This Week" },
                                    { id: "this_month", label: "This Month" },
                                    { id: "last_30_days", label: "Last 30 Days" },
                                    { id: "this_year", label: "This Year" },
                                    { id: "all_time", label: "All Time" },
                                ].map((preset) => (
                                    <button
                                        key={preset.id}
                                        type="button"
                                        onClick={() => handleSetDatePreset(preset.id)}
                                        className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
                                            activeDatePreset === preset.id
                                                ? "bg-emerald-600 text-white shadow-sm"
                                                : "bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                                        }`}
                                    >
                                        {preset.label}
                                    </button>
                                ))}
                            </div>
                        </div>

                        {/* 2. Custom Date Range Pickers */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    From Date (Start Date)
                                </label>
                                <input
                                    type="date"
                                    value={downloadStartDate}
                                    onChange={(e) => {
                                        setDownloadStartDate(e.target.value);
                                        setActiveDatePreset("custom");
                                    }}
                                    className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-xs text-slate-900 outline-none transition focus:border-emerald-500 focus:bg-white dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                />
                            </div>
                            <div>
                                <label className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    To Date (End Date)
                                </label>
                                <input
                                    type="date"
                                    value={downloadEndDate}
                                    onChange={(e) => {
                                        setDownloadEndDate(e.target.value);
                                        setActiveDatePreset("custom");
                                    }}
                                    className="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-xs text-slate-900 outline-none transition focus:border-emerald-500 focus:bg-white dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                />
                            </div>
                        </div>

                        {/* 3. Report Scope Selection */}
                        <div>
                            <label className="mb-2 block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Select Sheet Scope
                            </label>
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <button
                                    type="button"
                                    onClick={() => setDownloadReportType("directory")}
                                    className={`flex items-start gap-3 rounded-xl border p-3 text-left transition ${
                                        downloadReportType === "directory"
                                            ? "border-emerald-500 bg-emerald-50/50 dark:border-emerald-500/80 dark:bg-emerald-950/20"
                                            : "border-slate-200 hover:border-slate-300 dark:border-slate-800 dark:hover:border-slate-700"
                                    }`}
                                >
                                    <div className={`mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border ${
                                        downloadReportType === "directory" ? "border-emerald-600 bg-emerald-600" : "border-slate-300 dark:border-slate-600"
                                    }`}>
                                        {downloadReportType === "directory" ? <div className="h-1.5 w-1.5 rounded-full bg-white" /> : null}
                                    </div>
                                    <div>
                                        <p className="text-xs font-bold text-slate-900 dark:text-white">
                                            Projects & Directory Summary
                                        </p>
                                        <p className="text-[11px] text-slate-500 dark:text-slate-400">
                                            Project details, client info, site/khasra, tasks, checklists, and billing totals
                                        </p>
                                    </div>
                                </button>

                                <button
                                    type="button"
                                    onClick={() => setDownloadReportType("invoices")}
                                    className={`flex items-start gap-3 rounded-xl border p-3 text-left transition ${
                                        downloadReportType === "invoices"
                                            ? "border-emerald-500 bg-emerald-50/50 dark:border-emerald-500/80 dark:bg-emerald-950/20"
                                            : "border-slate-200 hover:border-slate-300 dark:border-slate-800 dark:hover:border-slate-700"
                                    }`}
                                >
                                    <div className={`mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border ${
                                        downloadReportType === "invoices" ? "border-emerald-600 bg-emerald-600" : "border-slate-300 dark:border-slate-600"
                                    }`}>
                                        {downloadReportType === "invoices" ? <div className="h-1.5 w-1.5 rounded-full bg-white" /> : null}
                                    </div>
                                    <div>
                                        <p className="text-xs font-bold text-slate-900 dark:text-white">
                                            Invoices & Payments Statement
                                        </p>
                                        <p className="text-[11px] text-slate-500 dark:text-slate-400">
                                            Detailed itemized invoices, invoice dates, amounts, status, and taxes
                                        </p>
                                    </div>
                                </button>
                            </div>
                        </div>

                        {/* 4. Live Matching Counter / Preview Box */}
                        <div className="rounded-xl border border-emerald-100 bg-emerald-50/60 p-3.5 dark:border-emerald-900/40 dark:bg-emerald-950/20">
                            <div className="flex items-center justify-between text-xs">
                                <span className="font-medium text-emerald-800 dark:text-emerald-300">
                                    Records in selected date range:
                                </span>
                                <div className="flex items-center gap-2">
                                    <span className="rounded-md bg-emerald-200/70 px-2 py-0.5 font-bold text-emerald-900 dark:bg-emerald-900/60 dark:text-emerald-200">
                                        {downloadReportType === "invoices" ? `${filteredInvoicesByDateRange.length} Invoices` : `${filteredProjectsByDateRange.length} Projects`}
                                    </span>
                                </div>
                            </div>
                            <p className="mt-1 text-[11px] text-emerald-700 dark:text-emerald-400">
                                Period: {downloadStartDate && downloadEndDate ? `${downloadStartDate} to ${downloadEndDate}` : (downloadStartDate ? `From ${downloadStartDate}` : (downloadEndDate ? `Until ${downloadEndDate}` : "All Time"))}
                            </p>
                        </div>
                    </div>

                    {/* Modal Footer */}
                    <div className="flex items-center justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4 dark:border-slate-800 dark:bg-slate-900/50">
                        <button
                            type="button"
                            onClick={() => setIsDownloadModalOpen(false)}
                            className="rounded-xl border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 sm:text-sm"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            onClick={handleDownloadReportWithDateRange}
                            className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-500 sm:text-sm"
                        >
                            <Download className="h-4 w-4" />
                            Download CSV
                        </button>
                    </div>
                </div>
            </Modal>
        </ConstructionShell>
    );
}

function BillingMetricCard({ label, value, icon: Icon, color, hint }) {
    const colorClasses = {
        violet: "bg-violet-50 text-violet-600 dark:bg-violet-950/40 dark:text-violet-300",
        sky: "bg-sky-50 text-sky-600 dark:bg-sky-950/40 dark:text-sky-300",
        emerald: "bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-300",
        orange: "bg-orange-50 text-orange-600 dark:bg-orange-950/40 dark:text-orange-300",
        teal: "bg-teal-50 text-teal-600 dark:bg-teal-950/40 dark:text-teal-300",
        blue: "bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300",
    };

    return (
        <div className="relative min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div className="flex items-start gap-2.5">
                <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ${colorClasses[color]}`}><Icon className="h-4 w-4" /></span>
                <div className="min-w-0">
                    <p className="truncate text-[10px] font-medium text-slate-500 dark:text-slate-400">{label}</p>
                    <p className="mt-1 truncate text-base font-bold tracking-tight text-slate-900 dark:text-white">{value}</p>
                    <p className="mt-1 text-[9px] text-slate-400">{hint || "Current total"}</p>
                </div>
            </div>
            <div className="absolute -bottom-2 right-0 h-7 w-16 rounded-tl-full bg-gradient-to-t from-indigo-100/70 to-transparent dark:from-indigo-950/50" />
        </div>
    );
}

function ChartCard({ title, icon: Icon, accent, children }) {
    const accentClasses = {
        violet: "bg-violet-100 text-violet-600 dark:bg-violet-950/50 dark:text-violet-300",
        blue: "bg-blue-100 text-blue-600 dark:bg-blue-950/50 dark:text-blue-300",
        emerald: "bg-emerald-100 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300",
    };

    return (
        <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div className="flex items-center gap-2 border-b border-slate-100 px-3.5 py-2.5 dark:border-slate-800">
                <span className={`flex h-6 w-6 items-center justify-center rounded-md ${accentClasses[accent]}`}><Icon className="h-3.5 w-3.5" /></span>
                <h2 className="text-xs font-semibold text-slate-800 dark:text-slate-100">{title}</h2>
            </div>
            <div className="p-3">{children}</div>
        </section>
    );
}

function ChartLegend({ items }) {
    return (
        <div className="flex items-center justify-center gap-4">
            {items.map((item) => (
                <span key={item.label} className="inline-flex items-center gap-1.5 text-[10px] text-slate-500">
                    <span className="h-2 w-2 rounded-full" style={{ backgroundColor: item.color }} />
                    {item.label}
                </span>
            ))}
        </div>
    );
}

function BillingBarChart({ data }) {
    const values = data.flatMap((row) => [Number(row.billedPercent || 0), Number(row.receivedPercent || 0)]);
    const maxValue = Math.max(0, ...values);
    const plotLeft = 44;
    const plotTop = 10;
    const plotWidth = 400;
    const plotHeight = 126;
    const slotWidth = plotWidth / Math.max(data.length, 1);
    const barWidth = Math.min(10, slotWidth * 0.28);
    const axisMax = 100;
    const ticks = [0, 1 / 3, 2 / 3, 1];

    return (
        <div className="h-44 w-full min-w-0">
            <svg viewBox="0 0 460 178" preserveAspectRatio="none" className="h-full w-full" role="img" aria-label="Monthly billed and received percentages">
                <title>Monthly billed and received percentages</title>
                {ticks.map((fraction) => {
                    const y = plotTop + plotHeight * (1 - fraction);
                    return (
                        <g key={fraction}>
                            <line x1={plotLeft} y1={y} x2={plotLeft + plotWidth} y2={y} stroke="#e2e8f0" strokeDasharray="3 4" />
                            <text x={plotLeft - 5} y={y + 3} textAnchor="end" fill="#64748b" fontSize="9">
                                {formatPercentage(axisMax * fraction, 100)}
                            </text>
                        </g>
                    );
                })}
                {data.map((row, index) => {
                    const center = plotLeft + (index + 0.5) * slotWidth;
                    const billed = Math.max(0, Number(row.billedPercent || 0));
                    const received = Math.max(0, Number(row.receivedPercent || 0));
                    const billedHeight = (billed / axisMax) * plotHeight;
                    const receivedHeight = (received / axisMax) * plotHeight;
                    return (
                        <g key={row.key}>
                            {billed > 0 ? (
                                <rect x={center - barWidth - 1} y={plotTop + plotHeight - billedHeight} width={barWidth} height={billedHeight} rx="2" fill="#6d36e8">
                                    <title>{`${row.month} billed share: ${formatPercentage(billed, 100)}`}</title>
                                </rect>
                            ) : null}
                            {received > 0 ? (
                                <rect x={center + 1} y={plotTop + plotHeight - receivedHeight} width={barWidth} height={receivedHeight} rx="2" fill="#3b82f6">
                                    <title>{`${row.month} received share: ${formatPercentage(received, 100)}`}</title>
                                </rect>
                            ) : null}
                            <text x={center} y="160" textAnchor="middle" fill="#64748b" fontSize="9">{row.month}</text>
                        </g>
                    );
                })}
                {maxValue === 0 ? (
                    <text x="244" y="76" textAnchor="middle" fill="#94a3b8" fontSize="11">No billing activity in this period</text>
                ) : null}
            </svg>
        </div>
    );
}

function DonutChart({ data, centerValue, centerLabel }) {
    const values = data.map((item) => Number(item.value || 0));
    const total = values.reduce((sum, value) => sum + Math.max(0, value), 0);
    let angle = 0;
    const gradient = total > 0
        ? `conic-gradient(${data.map((item, index) => {
            const value = Math.max(0, Number(item.value || 0));
            const start = angle;
            angle += (value / total) * 360;
            return `${item.color} ${start}deg ${angle}deg`;
        }).join(", ")})`
        : "conic-gradient(#e2e8f0 0deg 360deg)";

    return (
        <div
            role="img"
            aria-label={`${centerLabel}: ${centerValue}`}
            className="h-36 w-36 shrink-0 rounded-full p-[10px]"
            style={{ backgroundImage: gradient }}
        >
            <div className="flex h-full w-full flex-col items-center justify-center rounded-full bg-white px-2 text-center dark:bg-slate-900">
                <span className="max-w-full truncate text-sm font-bold text-slate-900 dark:text-white">{centerValue}</span>
                <span className="mt-0.5 text-[9px] text-slate-500">{total === 0 ? "No data" : centerLabel}</span>
            </div>
        </div>
    );
}

function formatCurrency(value) {
    const amount = Number(value || 0);
    return `₹${amount.toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function percentageValue(value, total) {
    const denominator = Number(total || 0);
    if (denominator <= 0) return 0;
    return Math.max(0, (Number(value || 0) / denominator) * 100);
}

function formatPercentage(value, total) {
    if (Number(total || 0) <= 0) return "—";
    return `${percentageValue(value, total).toFixed(0)}%`;
}

function parseBillingDate(value) {
    if (!value) return null;
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
}

function monthKey(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}`;
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
