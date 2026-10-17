import React, { useState, useMemo } from "react";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import {
    Activity,
    CheckCircle2,
    ListTodo,
    Users,
    FolderKanban,
    ArrowLeftRight,
    Clock,
    Search,
    Calendar,
    LayoutGrid,
    List,
    X,
    Tag,
    FileText,
    Sparkles,
    Shield,
    CheckSquare,
    AlertCircle,
    UserCheck,
    Coins,
    MapPin,
    PenTool
} from "lucide-react";

const MODULE_DEFINITIONS = {
    execution_task: {
        label: "Execution Task",
        icon: ListTodo,
        category: "tasks",
        colorClass: "text-indigo-600 bg-indigo-50 border-indigo-200 dark:bg-indigo-950/60 dark:text-indigo-300 dark:border-indigo-800",
    },
    task: {
        label: "Project Task",
        icon: ListTodo,
        category: "tasks",
        colorClass: "text-blue-600 bg-blue-50 border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800",
    },
    task_checklist: {
        label: "Task Checklist",
        icon: CheckSquare,
        category: "checklists",
        colorClass: "text-emerald-600 bg-emerald-50 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800",
    },
    task_assignment: {
        label: "Task Assignment",
        icon: UserCheck,
        category: "team",
        colorClass: "text-purple-600 bg-purple-50 border-purple-200 dark:bg-purple-950/60 dark:text-purple-300 dark:border-purple-800",
    },
    project_team: {
        label: "Project Team",
        icon: Users,
        category: "team",
        colorClass: "text-violet-600 bg-violet-50 border-violet-200 dark:bg-violet-950/60 dark:text-violet-300 dark:border-violet-800",
    },
    project: {
        label: "Project Workflow",
        icon: FolderKanban,
        category: "project",
        colorClass: "text-sky-600 bg-sky-50 border-sky-200 dark:bg-sky-950/60 dark:text-sky-300 dark:border-sky-800",
    },
    project_budget: {
        label: "Project Budget",
        icon: Coins,
        category: "budget",
        colorClass: "text-amber-600 bg-amber-50 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800",
    },
    survey_plan: {
        label: "Survey Plan",
        icon: MapPin,
        category: "survey",
        colorClass: "text-teal-600 bg-teal-50 border-teal-200 dark:bg-teal-950/60 dark:text-teal-300 dark:border-teal-800",
    },
    survey_submission: {
        label: "Survey Submission",
        icon: FileText,
        category: "survey",
        colorClass: "text-cyan-600 bg-cyan-50 border-cyan-200 dark:bg-cyan-950/60 dark:text-cyan-300 dark:border-cyan-800",
    },
    drafting_job: {
        label: "Drafting CAD Job",
        icon: PenTool,
        category: "drafting",
        colorClass: "text-fuchsia-600 bg-fuchsia-50 border-fuchsia-200 dark:bg-fuchsia-950/60 dark:text-fuchsia-300 dark:border-fuchsia-800",
    },
    drawing_approval: {
        label: "Drawing Approval",
        icon: CheckCircle2,
        category: "approvals",
        colorClass: "text-emerald-600 bg-emerald-50 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800",
    },
};

function formatModuleName(rawModule) {
    if (!rawModule) return "General Activity";
    const modKey = String(rawModule).toLowerCase();
    if (MODULE_DEFINITIONS[modKey]) {
        return MODULE_DEFINITIONS[modKey].label;
    }
    return String(rawModule)
        .replace(/[_-]+/g, " ")
        .replace(/\b\w/g, (c) => c.toUpperCase());
}

function getModuleMeta(rawModule) {
    const modKey = String(rawModule || "").toLowerCase();
    return MODULE_DEFINITIONS[modKey] || {
        label: formatModuleName(rawModule),
        icon: Activity,
        category: "other",
        colorClass: "text-slate-600 bg-slate-100 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700",
    };
}

function getRelativeTime(dateString) {
    if (!dateString) return "-";
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;

    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffSec = Math.floor(diffMs / 1000);
    const diffMin = Math.floor(diffSec / 60);
    const diffHour = Math.floor(diffMin / 60);
    const diffDay = Math.floor(diffHour / 24);

    if (diffDay > 30) {
        return date.toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" });
    }
    if (diffDay > 1) return `${diffDay} days ago`;
    if (diffDay === 1) return "Yesterday";
    if (diffHour > 0) return `${diffHour}h ago`;
    if (diffMin > 0) return `${diffMin}m ago`;
    return "Just now";
}

function formatFullDate(dateString) {
    if (!dateString) return "-";
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;
    return date.toLocaleString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
}

function getActionNarrative(item) {
    const meta = item.meta || {};
    const module = (item.module || "").toLowerCase();
    const action = (item.action || "").toLowerCase().replace(/[\s-]+/g, "_");

    if (module === "task_checklist") {
        const itemTitle = meta.item_title ? `"${meta.item_title}"` : "";
        const dayInfo = meta.day_number ? `Day ${meta.day_number}` : "";

        if (action === "completed") {
            return `Checklist item completed ${dayInfo ? `for ${dayInfo}` : ""} ${itemTitle}`.trim();
        }
        if (action === "reopened") {
            return `Checklist item reopened ${dayInfo ? `for ${dayInfo}` : ""} ${itemTitle}`.trim();
        }
        if (action === "created") {
            return `Added new checklist item ${itemTitle} ${dayInfo ? `to ${dayInfo}` : ""}`.trim();
        }
        if (action === "updated") {
            return `Updated checklist item ${itemTitle} ${dayInfo ? `(${dayInfo})` : ""}`.trim();
        }
    }

    if (module === "execution_task" || module === "task") {
        const taskTitle = meta.task_title || meta.title ? `"${meta.task_title || meta.title}"` : "";
        if (action === "task_transferred" || action === "transferred") {
            return `Task transferred to new assignee ${taskTitle}`.trim();
        }
        if (action === "status_changed") {
            return `Status changed ${meta.from ? `from ${meta.from}` : ""} ${meta.to ? `to ${meta.to}` : ""}`.trim();
        }
        if (action === "created") {
            return `Created execution task ${taskTitle}`.trim();
        }
        if (action === "completed") {
            return `Execution task marked completed ${taskTitle}`.trim();
        }
    }

    if (module === "project_team") {
        if (action === "assigned") {
            return meta.role ? `Assigned team member to role: ${meta.role}` : "Assigned member to project team";
        }
        if (action === "removed" || action === "revoked") {
            return "Removed member from project team";
        }
    }

    if (module === "project") {
        if (action === "updated") return "Updated project parameters and workflow status";
        if (action === "created") return "Project record created and initialized";
    }

    if (module === "project_budget") {
        if (action === "approved") return `Budget approved ${meta.version_no ? `(Version ${meta.version_no})` : ""}`.trim();
        if (action === "created") return "New budget version drafted";
    }

    if (meta.item_title) return `Item: "${meta.item_title}"`;
    if (meta.task_title || meta.title) return `Task: "${meta.task_title || meta.title}"`;
    if (meta.from && meta.to) return `Status transition: ${meta.from} → ${meta.to}`;
    if (meta.notes) return meta.notes;

    // Fallback based on action
    const readableAction = (item.action || "Activity recorded")
        .replace(/[_-]+/g, " ")
        .replace(/\b\w/g, (c) => c.toUpperCase());
    return `${readableAction} on ${formatModuleName(item.module)}`;
}

function getAvatarColor(name = "System") {
    const colors = [
        "from-indigo-500 to-purple-600 text-white",
        "from-blue-500 to-cyan-600 text-white",
        "from-emerald-500 to-teal-600 text-white",
        "from-amber-500 to-orange-600 text-white",
        "from-rose-500 to-pink-600 text-white",
        "from-violet-500 to-indigo-600 text-white",
    ];
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    return colors[Math.abs(hash) % colors.length];
}

function getInitials(name = "System") {
    if (!name) return "S";
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return name.slice(0, 2).toUpperCase();
}

export default function ProjectActivityLog({ activityLog = [], className = "" }) {
    const [searchQuery, setSearchQuery] = useState("");
    const [selectedCategory, setSelectedCategory] = useState("all");
    const [viewMode, setViewMode] = useState("grid"); // "grid" | "timeline"

    // Derive category counts
    const categoryCounts = useMemo(() => {
        const counts = { all: activityLog.length };
        activityLog.forEach((item) => {
            const meta = getModuleMeta(item.module);
            counts[meta.category] = (counts[meta.category] || 0) + 1;
        });
        return counts;
    }, [activityLog]);

    // Available categories that actually have items
    const availableCategories = useMemo(() => {
        const cats = [{ id: "all", label: "All Activities", count: activityLog.length }];
        const catMap = {
            tasks: "Tasks",
            checklists: "Checklists",
            team: "Team",
            project: "Project",
            survey: "Survey",
            drafting: "Drafting",
            budget: "Budget",
        };

        Object.keys(catMap).forEach((catKey) => {
            if (categoryCounts[catKey]) {
                cats.push({
                    id: catKey,
                    label: catMap[catKey],
                    count: categoryCounts[catKey],
                });
            }
        });
        return cats;
    }, [activityLog, categoryCounts]);

    // Filtered list based on search and category
    const filteredLogs = useMemo(() => {
        return activityLog.filter((item) => {
            const modMeta = getModuleMeta(item.module);
            if (selectedCategory !== "all" && modMeta.category !== selectedCategory) {
                return false;
            }

            if (!searchQuery.trim()) return true;

            const query = searchQuery.toLowerCase().trim();
            const actorName = (item.actor?.name || item.actor?.email || "System").toLowerCase();
            const moduleName = formatModuleName(item.module).toLowerCase();
            const action = (item.action || "").toLowerCase().replace(/[_-]+/g, " ");
            const narrative = getActionNarrative(item).toLowerCase();
            const metaStr = item.meta ? JSON.stringify(item.meta).toLowerCase() : "";

            return (
                actorName.includes(query) ||
                moduleName.includes(query) ||
                action.includes(query) ||
                narrative.includes(query) ||
                metaStr.includes(query)
            );
        });
    }, [activityLog, selectedCategory, searchQuery]);

    if (!activityLog || activityLog.length === 0) {
        return (
            <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                <div className="flex items-center gap-3 border-b border-slate-100 pb-4 dark:border-slate-800">
                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400">
                        <Activity className="h-5 w-5" />
                    </div>
                    <div>
                        <h2 className="text-lg font-semibold text-slate-900 dark:text-white">Activity Log</h2>
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            Every major action in the project workflow should show up here.
                        </p>
                    </div>
                </div>
                <div className="mt-6">
                    <EmptyState
                        title="No project activity yet."
                        description="Once actions are performed, the workflow audit trail will appear here."
                    />
                </div>
            </div>
        );
    }

    return (
        <section className={`rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950 ${className}`.trim()}>
            {/* Header with Title, Search, and View Controls */}
            <div className="flex flex-col gap-4 border-b border-slate-100 pb-5 dark:border-slate-800/80 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex items-start gap-3">
                    <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-white shadow-md shadow-indigo-500/20">
                        <Activity className="h-5 w-5" />
                    </div>
                    <div>
                        <div className="flex items-center gap-2.5">
                            <h2 className="text-lg font-bold text-slate-900 dark:text-white">
                                Activity Log
                            </h2>
                            <span className="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-600/10 dark:bg-indigo-950/50 dark:text-indigo-300 dark:ring-indigo-400/20">
                                {activityLog.length} events
                            </span>
                        </div>
                        <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                            Every major action in the project workflow should show up here.
                        </p>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    {/* Search Bar */}
                    <div className="relative min-w-[220px] flex-1 sm:w-64 sm:flex-initial">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Search activity..."
                            className="w-full rounded-xl border border-slate-200 bg-slate-50/80 py-2 pl-9 pr-8 text-xs font-medium text-slate-900 placeholder-slate-400 transition-all focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100 dark:placeholder-slate-500 dark:focus:border-indigo-500 dark:focus:bg-slate-950"
                        />
                        {searchQuery && (
                            <button
                                type="button"
                                onClick={() => setSearchQuery("")}
                                className="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            >
                                <X className="h-3.5 w-3.5" />
                            </button>
                        )}
                    </div>

                    {/* View Mode Toggle */}
                    <div className="flex items-center rounded-xl border border-slate-200 bg-slate-100/70 p-1 dark:border-slate-800 dark:bg-slate-900">
                        <button
                            type="button"
                            onClick={() => setViewMode("grid")}
                            title="Grid View"
                            className={`flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-semibold transition ${
                                viewMode === "grid"
                                    ? "bg-white text-slate-900 shadow-xs dark:bg-slate-800 dark:text-white"
                                    : "text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
                            }`}
                        >
                            <LayoutGrid className="h-3.5 w-3.5" />
                            <span className="hidden sm:inline">Grid</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setViewMode("timeline")}
                            title="Timeline View"
                            className={`flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-semibold transition ${
                                viewMode === "timeline"
                                    ? "bg-white text-slate-900 shadow-xs dark:bg-slate-800 dark:text-white"
                                    : "text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
                            }`}
                        >
                            <List className="h-3.5 w-3.5" />
                            <span className="hidden sm:inline">Timeline</span>
                        </button>
                    </div>
                </div>
            </div>

            {/* Category Filter Chips */}
            {availableCategories.length > 1 && (
                <div className="mt-4 flex flex-wrap items-center gap-1.5 pb-1">
                    {availableCategories.map((cat) => {
                        const isActive = selectedCategory === cat.id;
                        return (
                            <button
                                key={cat.id}
                                type="button"
                                onClick={() => setSelectedCategory(cat.id)}
                                className={`inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-semibold transition-all ${
                                    isActive
                                        ? "bg-indigo-600 text-white shadow-xs dark:bg-indigo-600"
                                        : "bg-slate-100 text-slate-600 hover:bg-slate-200/80 hover:text-slate-900 dark:bg-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                                }`}
                            >
                                <span>{cat.label}</span>
                                <span
                                    className={`rounded-full px-1.5 py-0.2 text-[10px] font-bold ${
                                        isActive
                                            ? "bg-white/20 text-white"
                                            : "bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                    }`}
                                >
                                    {cat.count}
                                </span>
                            </button>
                        );
                    })}
                </div>
            )}

            {/* Content Area */}
            <div className="mt-4">
                {filteredLogs.length === 0 ? (
                    <div className="rounded-2xl border border-dashed border-slate-200 py-12 text-center dark:border-slate-800">
                        <Activity className="mx-auto h-8 w-8 text-slate-400" />
                        <p className="mt-2 text-sm font-semibold text-slate-900 dark:text-white">
                            No matching activities found
                        </p>
                        <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {searchQuery
                                ? `No events match "${searchQuery}". Try modifying your search.`
                                : "No activities in this category."}
                        </p>
                        {(searchQuery || selectedCategory !== "all") && (
                            <button
                                type="button"
                                onClick={() => {
                                    setSearchQuery("");
                                    setSelectedCategory("all");
                                }}
                                className="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:text-indigo-300"
                            >
                                Reset filters
                            </button>
                        )}
                    </div>
                ) : viewMode === "grid" ? (
                    /* Responsive 2-Column Grid Layout */
                    <div className="max-h-[620px] overflow-y-auto pr-1.5 scrollbar-thin">
                        <div className="grid gap-3.5 sm:grid-cols-1 md:grid-cols-2">
                            {filteredLogs.map((item) => {
                                const modMeta = getModuleMeta(item.module);
                                const ModuleIcon = modMeta.icon;
                                const actorName = item.actor?.name || item.actor?.email || "System";
                                const actorPhoto = item.actor?.profile_photo_url || item.actor?.profile_image;
                                const narrative = getActionNarrative(item);
                                const meta = item.meta || {};

                                return (
                                    <div
                                        key={item.id}
                                        className="group relative flex flex-col justify-between rounded-2xl border border-slate-200/90 bg-slate-50/60 p-4 transition-all duration-200 hover:border-slate-300 hover:bg-white hover:shadow-md dark:border-slate-800/80 dark:bg-slate-900/60 dark:hover:border-slate-700 dark:hover:bg-slate-900"
                                    >
                                        <div>
                                            {/* Top Row: Module Badge + Action Badge */}
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <span
                                                    className={`inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-semibold ${modMeta.colorClass}`}
                                                >
                                                    <ModuleIcon className="h-3.5 w-3.5 shrink-0" />
                                                    <span>{modMeta.label}</span>
                                                </span>
                                                <StatusBadge value={item.action} />
                                            </div>

                                            {/* Narrative Description */}
                                            <p className="mt-3 text-sm font-medium text-slate-800 dark:text-slate-100">
                                                {narrative}
                                            </p>

                                            {/* Metadata chips if present */}
                                            {(meta.day_number || meta.project_code || meta.from || meta.scope) && (
                                                <div className="mt-2.5 flex flex-wrap items-center gap-1.5">
                                                    {meta.day_number && (
                                                        <span className="inline-flex items-center gap-1 rounded-md bg-slate-200/70 px-2 py-0.5 text-[11px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                            <Calendar className="h-3 w-3 text-slate-400" />
                                                            Day {meta.day_number}
                                                        </span>
                                                    )}
                                                    {meta.project_code && (
                                                        <span className="inline-flex items-center gap-1 rounded-md bg-slate-200/70 px-2 py-0.5 text-[11px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                            <Tag className="h-3 w-3 text-slate-400" />
                                                            {meta.project_code}
                                                        </span>
                                                    )}
                                                    {meta.scope && (
                                                        <span className="inline-flex items-center gap-1 rounded-md bg-slate-200/70 px-2 py-0.5 text-[11px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                            Scope: {meta.scope}
                                                        </span>
                                                    )}
                                                    {meta.from && meta.to && (
                                                        <span className="inline-flex items-center gap-1 rounded-md bg-slate-200/70 px-2 py-0.5 text-[11px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                            <ArrowLeftRight className="h-3 w-3 text-slate-400" />
                                                            {meta.from} → {meta.to}
                                                        </span>
                                                    )}
                                                </div>
                                            )}

                                            {/* Notes / Remarks quote if present */}
                                            {meta.notes && (
                                                <div className="mt-2.5 rounded-xl border border-slate-200 bg-white p-2.5 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300">
                                                    <p className="line-clamp-2 italic">"{meta.notes}"</p>
                                                </div>
                                            )}
                                        </div>

                                        {/* Bottom Row: Actor & Timestamp */}
                                        <div className="mt-4 flex items-center justify-between border-t border-slate-200/60 pt-3 dark:border-slate-800/60">
                                            <div className="flex items-center gap-2.5 min-w-0">
                                                {actorPhoto ? (
                                                    <img
                                                        src={actorPhoto}
                                                        alt={actorName}
                                                        className="h-7 w-7 shrink-0 rounded-full object-cover ring-2 ring-slate-200 dark:ring-slate-700"
                                                    />
                                                ) : (
                                                    <div
                                                        className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-[11px] font-bold ring-2 ring-slate-200 dark:ring-slate-700 ${getAvatarColor(
                                                            actorName
                                                        )}`}
                                                    >
                                                        {getInitials(actorName)}
                                                    </div>
                                                )}
                                                <div className="min-w-0">
                                                    <p className="truncate text-xs font-semibold text-slate-900 dark:text-white">
                                                        {actorName}
                                                    </p>
                                                </div>
                                            </div>

                                            <div
                                                className="shrink-0 text-right text-[11px] text-slate-500 dark:text-slate-400"
                                                title={formatFullDate(item.created_at)}
                                            >
                                                <div className="flex items-center gap-1">
                                                    <Clock className="h-3 w-3 text-slate-400" />
                                                    <span>{getRelativeTime(item.created_at)}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                ) : (
                    /* Timeline View */
                    <div className="max-h-[620px] overflow-y-auto pl-4 pr-1.5 scrollbar-thin">
                        <div className="relative border-l-2 border-slate-200/80 dark:border-slate-800 ml-2 space-y-6 pl-6 py-2">
                            {filteredLogs.map((item) => {
                                const modMeta = getModuleMeta(item.module);
                                const ModuleIcon = modMeta.icon;
                                const actorName = item.actor?.name || item.actor?.email || "System";
                                const narrative = getActionNarrative(item);
                                const meta = item.meta || {};

                                return (
                                    <div key={item.id} className="relative group">
                                        {/* Timeline Dot Icon */}
                                        <span className="absolute -left-[35px] top-1.5 flex h-7 w-7 items-center justify-center rounded-full bg-white dark:bg-slate-950 ring-4 ring-slate-100 dark:ring-slate-900 shadow-xs border border-slate-200 dark:border-slate-800 text-indigo-600 dark:text-indigo-400">
                                            <ModuleIcon className="h-3.5 w-3.5" />
                                        </span>

                                        <div className="rounded-2xl border border-slate-200/90 bg-slate-50/70 p-4 transition-all hover:border-slate-300 hover:bg-white hover:shadow-xs dark:border-slate-800 dark:bg-slate-900/70 dark:hover:border-slate-700 dark:hover:bg-slate-900">
                                            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/60 pb-2.5 dark:border-slate-800/60">
                                                <div className="flex items-center gap-2">
                                                    <span className={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-semibold border ${modMeta.colorClass}`}>
                                                        {modMeta.label}
                                                    </span>
                                                    <StatusBadge value={item.action} />
                                                </div>
                                                <span
                                                    className="flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400"
                                                    title={formatFullDate(item.created_at)}
                                                >
                                                    <Clock className="h-3 w-3 text-slate-400" />
                                                    {getRelativeTime(item.created_at)} • {formatFullDate(item.created_at)}
                                                </span>
                                            </div>

                                            <p className="mt-2.5 text-sm font-medium text-slate-900 dark:text-slate-100">
                                                {narrative}
                                            </p>

                                            <div className="mt-3 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-semibold text-slate-700 dark:text-slate-300">
                                                        By:
                                                    </span>
                                                    <span className="font-medium text-slate-900 dark:text-white">
                                                        {actorName}
                                                    </span>
                                                </div>

                                                {(meta.day_number || meta.project_code) && (
                                                    <div className="flex items-center gap-2">
                                                        {meta.day_number && (
                                                            <span className="rounded-md bg-slate-200/80 px-2 py-0.5 text-[11px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                                Day {meta.day_number}
                                                            </span>
                                                        )}
                                                        {meta.project_code && (
                                                            <span className="rounded-md bg-slate-200/80 px-2 py-0.5 text-[11px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                                {meta.project_code}
                                                            </span>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}
            </div>
        </section>
    );
}
