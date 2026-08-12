import { Link, useForm, router } from "@inertiajs/react";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import Modal from "@/Components/Modal";
import { useMemo, useState } from "react";
import {
    FaProjectDiagram,
    FaPlayCircle,
    FaCheckCircle,
    FaHourglassHalf,
    FaLayerGroup,
    FaSearch,
    FaToolbox,
    FaBuilding,
    FaRulerCombined,
    FaHandshake,
    FaFlagCheckered,
    FaPlus,
    FaEye,
    FaEdit,
    FaTrashAlt,
} from "react-icons/fa";

const projectStatusFlow = [
    { value: "planning", label: "Planning", color: "indigo", icon: FaLayerGroup, description: "Setup, budget, team" },
    { value: "survey", label: "Survey", color: "sky", icon: FaSearch, description: "Site survey & GPS" },
    { value: "foundation", label: "Foundation", color: "amber", icon: FaToolbox, description: "Footing & excavation" },
    { value: "structure", label: "Structure", color: "violet", icon: FaBuilding, description: "Columns, beams, slabs" },
    { value: "finishing", label: "Finishing", color: "fuchsia", icon: FaRulerCombined, description: "Paint, floor, fixtures" },
    { value: "handover", label: "Handover", color: "rose", icon: FaHandshake, description: "Inspection & handover" },
    { value: "completed", label: "Completed", color: "emerald", icon: FaFlagCheckered, description: "Project closed" },
];

export default function ProjectsIndex({ projects, companies, clients }) {
    const form = useForm({
        company_id: companies[0]?.id || "",
        client_id: clients[0]?.id || "",
        name: "",
        category: "",
        description: "",
        project_address: "",
        latitude: "",
        longitude: "",
        start_date: "",
        expected_end_date: "",
        priority: "medium",
    });

    const [editingProject, setEditingProject] = useState(null);

    const editForm = useForm({
        company_id: "",
        client_id: "",
        name: "",
        category: "",
        description: "",
        project_address: "",
        latitude: "",
        longitude: "",
        start_date: "",
        expected_end_date: "",
        priority: "medium",
    });

    const [stageFilter, setStageFilter] = useState("all");
    const [search, setSearch] = useState("");

    const openEditModal = (project) => {
        editForm.clearErrors();
        editForm.setData({
            company_id: project.company_id || "",
            client_id: project.client_id || "",
            name: project.name || "",
            category: project.category || "",
            description: project.description || "",
            project_address: project.project_address || "",
            latitude: project.latitude ?? "",
            longitude: project.longitude ?? "",
            start_date: project.start_date || "",
            expected_end_date: project.expected_end_date || "",
            priority: project.priority || "medium",
        });
        setEditingProject(project);
    };

    const closeEditModal = () => {
        editForm.clearErrors();
        editForm.reset();
        setEditingProject(null);
    };

    const submitEdit = (e) => {
        e.preventDefault();
        editForm.put(route("super.construction.projects.update", editingProject.id), {
            preserveScroll: true,
            onSuccess: () => closeEditModal(),
        });
    };

    const filteredClients = useMemo(
        () => clients.filter((client) => String(client.company_id) === String(form.data.company_id)),
        [clients, form.data.company_id]
    );

    const editFilteredClients = useMemo(
        () => clients.filter((client) => String(client.company_id) === String(editForm.data.company_id)),
        [clients, editForm.data.company_id]
    );

    const projectList = useMemo(() => projects || [], [projects]);

    const counts = useMemo(() => {
        const out = { total: 0, running: 0, completed: 0, pending: 0 };
        const c = {};
        projectStatusFlow.forEach((st) => (c[st.value] = 0));
        for (const p of projectList) {
            out.total += 1;
            if (projectStatusFlow.find((s) => s.value === p.current_stage)) c[p.current_stage] = (c[p.current_stage] || 0) + 1;
            if (p.current_stage === "completed" || p.current_stage === "closed") out.completed += 1;
            else if (p.current_stage === "budget_pending" || p.current_stage === "draft") out.pending += 1;
            else out.running += 1;
        }
        return { ...out, stages: c };
    }, [projectList]);

    const filteredProjects = useMemo(() => {
        let list = projectList;
        if (stageFilter !== "all") list = list.filter((p) => p.current_stage === stageFilter);
        if (search.trim()) {
            const q = search.trim().toLowerCase();
            list = list.filter(
                (p) =>
                    (p.name || "").toLowerCase().includes(q) ||
                    (p.project_code || "").toLowerCase().includes(q) ||
                    (p.client?.name || "").toLowerCase().includes(q) ||
                    (p.company?.name || "").toLowerCase().includes(q)
            );
        }
        return list;
    }, [projectList, stageFilter, search]);

    return (
        <ConstructionShell title="Projects" description="Every department works around project records. Phase 1 starts here and Phase 2 continues from here." variant="super">
            <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 className="text-[22px] font-bold text-slate-900 dark:text-white tracking-tight">Projects & Budget</h1>
                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Create projects, approve budgets, assign teams, and advance through the 7-stage lifecycle.
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative">
                        <FaSearch className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={13} />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search projects…"
                            className="rounded-xl border border-slate-200 bg-white pl-9 pr-3 py-2 text-[13px] text-slate-700 placeholder:text-slate-400 focus:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:focus:border-indigo-700 dark:focus:ring-indigo-900 w-64"
                        />
                    </div>
                </div>
            </div>

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4 mb-6">
                <StatCard label="Total Projects" value={counts.total} hint="All-time registered" icon={FaProjectDiagram} color="indigo" />
                <StatCard label="Running Projects" value={counts.running} hint="In any active stage" icon={FaPlayCircle} color="emerald" />
                <StatCard label="Completed Projects" value={counts.completed} hint="Handed over & closed" icon={FaCheckCircle} color="sky" />
                <StatCard label="Pending Projects" value={counts.pending} hint="Draft / awaiting budget" icon={FaHourglassHalf} color="amber" />
            </div>

            <SectionCard
                title="Project Lifecycle Stages"
                description="Click any stage to filter the register below. Every project moves through this linear flow."
            >
                <div className="grid gap-3 md:grid-cols-4 xl:grid-cols-7">
                    <button
                        onClick={() => setStageFilter("all")}
                        className={`text-left rounded-2xl border p-3.5 transition-all duration-200 ${
                            stageFilter === "all"
                                ? "border-slate-900 bg-slate-900 text-white shadow-md dark:border-white dark:bg-white dark:text-slate-900"
                                : "border-slate-200 bg-white hover:-translate-y-0.5 hover:shadow-md dark:border-slate-800 dark:bg-slate-900"
                        }`}
                    >
                        <div className="flex items-center justify-between">
                            <p className={`text-[11px] font-semibold uppercase tracking-wider ${stageFilter === "all" ? "opacity-80" : "text-slate-500 dark:text-slate-400"}`}>
                                View
                            </p>
                            <span className={`text-lg font-bold ${stageFilter === "all" ? "" : "text-slate-900 dark:text-white"}`}>{counts.total}</span>
                        </div>
                        <p className={`mt-2 text-sm font-semibold ${stageFilter === "all" ? "" : "text-slate-900 dark:text-white"}`}>All Projects</p>
                        <p className={`mt-0.5 text-[11px] ${stageFilter === "all" ? "opacity-80" : "text-slate-500 dark:text-slate-400"}`}>
                            No filter applied
                        </p>
                    </button>
                    {projectStatusFlow.map((st) => {
                        const Icon = st.icon;
                        const count = counts.stages[st.value] || 0;
                        return (
                            <button
                                key={st.value}
                                onClick={() => setStageFilter(stageFilter === st.value ? "all" : st.value)}
                                className={`text-left rounded-2xl border p-3.5 transition-all duration-200 ${
                                    stageFilter === st.value
                                        ? "border-indigo-500 bg-indigo-50 shadow-md shadow-indigo-100 dark:border-indigo-500 dark:bg-indigo-950/60 dark:shadow-none"
                                        : "border-slate-200 bg-white hover:-translate-y-0.5 hover:shadow-md dark:border-slate-800 dark:bg-slate-900"
                                }`}
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div
                                        className={`w-8 h-8 rounded-lg flex items-center justify-center text-white ${
                                            {
                                                indigo: "bg-indigo-600",
                                                sky: "bg-sky-600",
                                                amber: "bg-amber-500",
                                                violet: "bg-violet-600",
                                                fuchsia: "bg-fuchsia-600",
                                                rose: "bg-rose-600",
                                                emerald: "bg-emerald-600",
                                            }[st.color]
                                        }`}
                                    >
                                        <Icon size={14} />
                                    </div>
                                    <span className="text-lg font-bold text-slate-900 dark:text-white">{count}</span>
                                </div>
                                <p className="mt-2.5 text-sm font-semibold text-slate-900 dark:text-white">{st.label}</p>
                                <p className="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400 leading-snug">{st.description}</p>
                            </button>
                        );
                    })}
                </div>
            </SectionCard>

            <div className="mt-6 grid gap-6 xl:grid-cols-[440px,1fr]">
                <SectionCard title="Create Project" description="Register the central project record and set the initial Phase 1 metadata.">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route("super.construction.projects.store"), {
                                preserveScroll: true,
                                onSuccess: () => form.reset("name", "category", "description", "project_address", "latitude", "longitude", "start_date", "expected_end_date"),
                            });
                        }}
                        className="space-y-4"
                    >
                        <div className="flex items-center gap-2 pb-2 border-b border-slate-200 dark:border-slate-800">
                            <div className="w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center shrink-0">
                                <FaPlus size={13} />
                            </div>
                            <p className="text-sm font-semibold text-slate-900 dark:text-white">New Project Registration</p>
                        </div>
                        <SelectField form={form} name="company_id" label="Company" options={companies.map((company) => ({ value: company.id, label: company.name }))} />
                        <SelectField
                            form={form}
                            name="client_id"
                            label="Client"
                            options={filteredClients.length > 0 ? filteredClients.map((client) => ({ value: client.id, label: client.name })) : [{ value: "", label: "— No clients for this company —" }]}
                        />
                        <InputField form={form} name="name" label="Project Name" />
                        <InputField form={form} name="category" label="Category (Residential / Commercial / Road / Bridge)" />
                        <TextAreaField form={form} name="description" label="Description" rows={3} />
                        <TextAreaField form={form} name="project_address" label="Project Address" rows={2} />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <InputField form={form} name="latitude" label="Latitude" type="number" step="any" />
                            <InputField form={form} name="longitude" label="Longitude" type="number" step="any" />
                            <InputField form={form} name="start_date" label="Start Date" type="date" />
                            <InputField form={form} name="expected_end_date" label="Expected End Date" type="date" />
                        </div>
                        <SelectField
                            form={form}
                            name="priority"
                            label="Priority"
                            options={[
                                { value: "low", label: "Low" },
                                { value: "medium", label: "Medium" },
                                { value: "high", label: "High" },
                                { value: "critical", label: "Critical" },
                            ]}
                        />
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-3 font-medium text-white shadow-sm hover:from-indigo-500 hover:to-violet-500 disabled:opacity-60"
                        >
                            {form.processing ? (
                                <>Saving…</>
                            ) : (
                                <>
                                    <FaPlus size={13} />
                                    Create Project
                                </>
                            )}
                        </button>
                    </form>
                </SectionCard>

                <SectionCard
                    title={`Project Register (${filteredProjects.length})`}
                    description="Track where each project sits in the lifecycle. Click View / Edit / Delete actions to manage."
                >
                    {filteredProjects.length ? (
                        <div className="overflow-x-auto -mx-2">
                            <table className="min-w-full text-left text-sm">
                                <thead className="text-slate-500 dark:text-slate-400 bg-slate-50/60 dark:bg-slate-900/40">
                                    <tr>
                                        <th className="px-3 py-2.5 text-[11px] font-semibold uppercase tracking-wider">Project</th>
                                        <th className="px-3 py-2.5 text-[11px] font-semibold uppercase tracking-wider">Company · Client</th>
                                        <th className="px-3 py-2.5 text-[11px] font-semibold uppercase tracking-wider">Stage</th>
                                        <th className="px-3 py-2.5 text-[11px] font-semibold uppercase tracking-wider">Budget</th>
                                        <th className="px-3 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {filteredProjects.map((project) => (
                                        <tr key={project.id} className="hover:bg-slate-50/40 dark:hover:bg-slate-900/40 transition-colors">
                                            <td className="px-3 py-3">
                                                <div className="font-semibold text-slate-900 dark:text-white">{project.name}</div>
                                                <div className="text-[11px] text-slate-500 dark:text-slate-400">{project.project_code || "—"} · {project.priority || "medium"}</div>
                                            </td>
                                            <td className="px-3 py-3 text-[13px] text-slate-600 dark:text-slate-300">
                                                <div className="font-medium text-slate-700 dark:text-slate-200">{project.company?.name || "-"}</div>
                                                <div className="text-[11px] text-slate-500 dark:text-slate-400">{project.client?.name || "-"}</div>
                                            </td>
                                            <td className="px-3 py-3">
                                                <StatusBadge value={project.current_stage} />
                                            </td>
                                            <td className="px-3 py-3 text-[13px] font-medium text-slate-700 dark:text-slate-200">
                                                {formatCurrency(
                                                    project.latest_budget?.approved_amount ||
                                                        project.latest_budget?.estimated_amount ||
                                                        0
                                                )}
                                            </td>
                                            <td className="px-3 py-3">
                                                <div className="flex items-center justify-end gap-1">
                                                    <RowAction href={route("super.construction.projects.show", project.id)} icon={FaEye} color="indigo" label="View details" />
                                                    <RowAction icon={FaEdit} color="sky" label="Edit" onClick={() => openEditModal(project)} />
                                                    <RowAction
                                                        color="rose"
                                                        icon={FaTrashAlt}
                                                        label="Delete"
                                                        onClick={() => {
                                                            if (confirm(`Delete project "${project.name}"? All survey, execution, material, billing and handover data tied to this project will be removed and cannot be restored.`)) {
                                                                router.delete(route("super.construction.projects.destroy", project.id), {
                                                                    preserveScroll: true,
                                                                    onError: (errs) => {
                                                                        if (typeof window !== "undefined" && typeof window.alert === "function") {
                                                                            window.alert(Object.values(errs).find(Boolean) || "Failed to delete project.");
                                                                        }
                                                                    },
                                                                });
                                                            }
                                                        }}
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <EmptyState
                            title="No projects match this filter."
                            description="Clear the stage filter or create the first project to start the ERP lifecycle."
                        />
                    )}
                </SectionCard>
            </div>

            <Modal show={editingProject !== null} onClose={closeEditModal} maxWidth="3xl">
                <div className="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h3 className="text-lg font-semibold text-slate-900 dark:text-white">Edit Project</h3>
                    <p className="mt-1 text-sm text-slate-500">Update the project master data. Lifecycle stage and status are managed through the workflow.</p>
                </div>
                <form onSubmit={submitEdit} className="space-y-4 p-5">
                    <div className="grid gap-4 md:grid-cols-2">
                        <SelectField form={editForm} name="company_id" label="Company" options={companies.map((company) => ({ value: company.id, label: company.name }))} />
                        <SelectField
                            form={editForm}
                            name="client_id"
                            label="Client"
                            options={editFilteredClients.length > 0 ? editFilteredClients.map((client) => ({ value: client.id, label: client.name })) : [{ value: "", label: "— No clients for this company —" }]}
                        />
                    </div>
                    <InputField form={editForm} name="name" label="Project Name" />
                    <InputField form={editForm} name="category" label="Category (Residential / Commercial / Road / Bridge)" />
                    <TextAreaField form={editForm} name="description" label="Description" rows={3} />
                    <TextAreaField form={editForm} name="project_address" label="Project Address" rows={2} />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <InputField form={editForm} name="latitude" label="Latitude" type="number" step="any" />
                        <InputField form={editForm} name="longitude" label="Longitude" type="number" step="any" />
                        <InputField form={editForm} name="start_date" label="Start Date" type="date" />
                        <InputField form={editForm} name="expected_end_date" label="Expected End Date" type="date" />
                    </div>
                    <SelectField
                        form={editForm}
                        name="priority"
                        label="Priority"
                        options={[
                            { value: "low", label: "Low" },
                            { value: "medium", label: "Medium" },
                            { value: "high", label: "High" },
                            { value: "critical", label: "Critical" },
                        ]}
                    />
                    <div className="flex items-center justify-end gap-3 pt-2">
                        <button
                            type="button"
                            onClick={closeEditModal}
                            disabled={editForm.processing}
                            className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={editForm.processing}
                            className="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-500 disabled:opacity-60"
                        >
                            {editForm.processing ? "Updating..." : "Update Project"}
                        </button>
                    </div>
                </form>
            </Modal>
        </ConstructionShell>
    );
}

function formatCurrency(value) {
    const num = Number(value) || 0;
    if (num >= 10000000) return "₹" + (num / 10000000).toFixed(2) + " Cr";
    if (num >= 100000) return "₹" + (num / 100000).toFixed(2) + " L";
    if (num >= 1000) return "₹" + (num / 1000).toFixed(1) + "K";
    return "₹" + num.toFixed(0);
}

function InputField({ form, name, label, type = "text", step }) {
    return (
        <div>
            <label className="mb-1 block text-[13px] font-medium text-slate-700 dark:text-slate-200">{label}</label>
            <input
                type={type}
                step={step}
                value={form.data[name]}
                onChange={(e) => form.setData(name, e.target.value)}
                className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-[14px] dark:border-slate-700 dark:bg-slate-900 dark:text-white focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:focus:border-indigo-700 dark:focus:ring-indigo-900/60"
            />
            {form.errors[name] ? <p className="mt-1 text-xs text-rose-600">{form.errors[name]}</p> : null}
        </div>
    );
}

function TextAreaField({ form, name, label, rows = 4 }) {
    return (
        <div>
            <label className="mb-1 block text-[13px] font-medium text-slate-700 dark:text-slate-200">{label}</label>
            <textarea
                rows={rows}
                value={form.data[name]}
                onChange={(e) => form.setData(name, e.target.value)}
                className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-[14px] dark:border-slate-700 dark:bg-slate-900 dark:text-white focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:focus:border-indigo-700 dark:focus:ring-indigo-900/60"
            />
            {form.errors[name] ? <p className="mt-1 text-xs text-rose-600">{form.errors[name]}</p> : null}
        </div>
    );
}

function SelectField({ form, name, label, options }) {
    return (
        <div>
            <label className="mb-1 block text-[13px] font-medium text-slate-700 dark:text-slate-200">{label}</label>
            <select
                value={form.data[name]}
                onChange={(e) => form.setData(name, e.target.value)}
                className="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-[14px] dark:border-slate-700 dark:bg-slate-900 dark:text-white focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:focus:border-indigo-700 dark:focus:ring-indigo-900/60"
            >
                {options.map((option) => (
                    <option key={String(option.value)} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            {form.errors[name] ? <p className="mt-1 text-xs text-rose-600">{form.errors[name]}</p> : null}
        </div>
    );
}

function RowAction({ icon, color = "indigo", href, onClick, label }) {
    const Icon = icon;
    const colors = {
        indigo: "text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-950/60",
        sky: "text-sky-600 hover:bg-sky-50 dark:text-sky-400 dark:hover:bg-sky-950/60",
        rose: "text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/60",
        emerald: "text-emerald-600 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-950/60",
        amber: "text-amber-600 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-950/60",
    };
    const cls = `inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors ${colors[color] || colors.indigo}`;
    if (href) {
        return (
            <Link href={href} title={label} className={cls}>
                <Icon size={14} />
            </Link>
        );
    }
    return (
        <button type="button" title={label} onClick={onClick} className={cls}>
            <Icon size={14} />
        </button>
    );
}
