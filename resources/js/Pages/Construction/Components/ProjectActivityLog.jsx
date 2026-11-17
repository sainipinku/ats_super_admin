import React, { useState, useMemo, useRef, useEffect } from "react";
import gsap from "gsap";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import Modal from "@/Components/Modal";
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
    PenTool,
    Info,
    ExternalLink,
    Zap,
    UserMinus,
    ArrowRightLeft,
    RefreshCw
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

function isActivityRecent(dateString) {
    if (!dateString) return false;
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return false;
    const now = new Date();
    const diffHours = (now.getTime() - date.getTime()) / (1000 * 60 * 60);
    return diffHours >= 0 && diffHours <= 24;
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
        if (action === "deleted") {
            return `Deleted checklist item ${itemTitle}`.trim();
        }
    }

    if (module === "execution_task" || module === "task") {
        const taskTitle = meta.task_title || meta.title ? `"${meta.task_title || meta.title}"` : "";
        if (action === "task_transferred" || action === "transferred") {
            return `Task transferred to new assignee ${taskTitle}`.trim();
        }
        if (action === "unassigned" || action === "task_unassigned") {
            return `Task unassigned from team member ${taskTitle}`.trim();
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

function renderActionPill(action) {
    const act = String(action || "").toLowerCase().replace(/[\s-]+/g, "_");

    if (act.includes("transfer")) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-full border border-purple-200/80 bg-purple-50/90 px-2.5 py-0.5 text-[11px] font-semibold text-purple-700 shadow-2xs dark:border-purple-800/80 dark:bg-purple-950/60 dark:text-purple-300">
                <ArrowRightLeft className="h-3 w-3 text-purple-500" />
                <span>Task Transferred</span>
            </span>
        );
    }

    if (act.includes("unassign") || act.includes("removed") || act.includes("revoked")) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-full border border-rose-200/80 bg-rose-50/90 px-2.5 py-0.5 text-[11px] font-semibold text-rose-700 shadow-2xs dark:border-rose-800/80 dark:bg-rose-950/60 dark:text-rose-300">
                <UserMinus className="h-3 w-3 text-rose-500" />
                <span>{act.includes("unassign") ? "Task Unassigned" : "Member Removed"}</span>
            </span>
        );
    }

    if (act.includes("completed") || act.includes("approved")) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-200/80 bg-emerald-50/90 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 shadow-2xs dark:border-emerald-800/80 dark:bg-emerald-950/60 dark:text-emerald-300">
                <CheckCircle2 className="h-3 w-3 text-emerald-500" />
                <span>Completed</span>
            </span>
        );
    }

    if (act.includes("create") || act.includes("added")) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-full border border-sky-200/80 bg-sky-50/90 px-2.5 py-0.5 text-[11px] font-semibold text-sky-700 shadow-2xs dark:border-sky-800/80 dark:bg-sky-950/60 dark:text-sky-300">
                <Sparkles className="h-3 w-3 text-sky-500" />
                <span>Created</span>
            </span>
        );
    }

    if (act.includes("delete")) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-full border border-rose-200/80 bg-rose-50/90 px-2.5 py-0.5 text-[11px] font-semibold text-rose-700 shadow-2xs dark:border-rose-800/80 dark:bg-rose-950/60 dark:text-rose-300">
                <AlertCircle className="h-3 w-3 text-rose-500" />
                <span>Deleted</span>
            </span>
        );
    }

    if (act.includes("update") || act.includes("changed")) {
        return (
            <span className="inline-flex items-center gap-1.5 rounded-full border border-indigo-200/80 bg-indigo-50/90 px-2.5 py-0.5 text-[11px] font-semibold text-indigo-700 shadow-2xs dark:border-indigo-800/80 dark:bg-indigo-950/60 dark:text-indigo-300">
                <RefreshCw className="h-3 w-3 text-indigo-500" />
                <span>Updated</span>
            </span>
        );
    }

    return <StatusBadge value={action} />;
}

export default function ProjectActivityLog({ activityLog = [], className = "" }) {
    const [searchQuery, setSearchQuery] = useState("");
    const [selectedCategory, setSelectedCategory] = useState("all");
    const [viewMode, setViewMode] = useState("grid"); // "grid" | "timeline"
    const [selectedActivity, setSelectedActivity] = useState(null);
    const [showRawJson, setShowRawJson] = useState(false);

    const cardsContainerRef = useRef(null);

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

    // GSAP Entrance and Stagger Animations
    useEffect(() => {
        if (!cardsContainerRef.current) return;

        const ctx = gsap.context(() => {
            const cards = cardsContainerRef.current?.querySelectorAll(".activity-log-card");
            if (cards && cards.length > 0) {
                gsap.fromTo(
                    cards,
                    {
                        opacity: 0,
                        y: 18,
                        scale: 0.97,
                    },
                    {
                        opacity: 1,
                        y: 0,
                        scale: 1,
                        duration: 0.42,
                        stagger: 0.04,
                        ease: "power2.out",
                        clearProps: "transform,opacity",
                    }
                );
            }
        }, cardsContainerRef);

        return () => ctx.revert();
    }, [filteredLogs, viewMode, selectedCategory]);

    const handleCardMouseEnter = (e) => {
        const card = e.currentTarget;
        gsap.to(card, {
            y: -3,
            duration: 0.22,
            ease: "power1.out",
            overwrite: "auto",
        });
    };

    const handleCardMouseLeave = (e) => {
        const card = e.currentTarget;
        gsap.to(card, {
            y: 0,
            duration: 0.22,
            ease: "power1.out",
            overwrite: "auto",
        });
    };

    if (!activityLog || activityLog.length === 0) {
        return (
            <div className="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-xs dark:border-slate-800 dark:bg-slate-950">
                <div className="flex items-center gap-3 border-b border-slate-100 pb-4 dark:border-slate-800">
                    <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400">
                        <Activity className="h-5 w-5" />
                    </div>
                    <div>
                        <h2 className="text-lg font-bold text-slate-900 dark:text-white">Activity Log</h2>
                        <p className="text-xs text-slate-500 dark:text-slate-400">
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
        <section className={`rounded-3xl border border-slate-200/90 bg-white/90 p-5 backdrop-blur-xs sm:p-6 shadow-sm dark:border-slate-800/80 dark:bg-slate-950/90 ${className}`.trim()}>
            {/* Header with Title, Live Blinking Badge, Search, and View Controls */}
            <div className="flex flex-col gap-4 border-b border-slate-100 pb-5 dark:border-slate-800/80 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex items-start gap-3.5">
                    <div className="relative flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 via-indigo-600 to-violet-700 text-white shadow-md shadow-indigo-500/20">
                        <Activity className="h-6 w-6" />
                        <span className="absolute -bottom-0.5 -right-0.5 flex h-3 w-3">
                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                            <span className="relative inline-flex h-3 w-3 rounded-full border-2 border-white bg-emerald-500 dark:border-slate-950"></span>
                        </span>
                    </div>
                    <div>
                        <div className="flex flex-wrap items-center gap-2.5">
                            <h2 className="text-lg font-bold tracking-tight text-slate-900 dark:text-white">
                                Activity Log
                            </h2>
                            <span className="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-600/10 dark:bg-indigo-950/50 dark:text-indigo-300 dark:ring-indigo-400/20">
                                {activityLog.length} events
                            </span>

                            {/* Blinking Live Indicator */}
                            <div className="flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-bold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-950/60 dark:text-emerald-300">
                                <span className="relative flex h-2 w-2">
                                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                    <span className="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                                </span>
                                <span>LIVE</span>
                            </div>
                        </div>
                        <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
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
                                        ? "bg-indigo-600 text-white shadow-xs shadow-indigo-600/20 dark:bg-indigo-600"
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

            {/* Content Area with GSAP Targeting Container */}
            <div ref={cardsContainerRef} className="mt-4">
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
                    /* Responsive 2-Column Grid Layout with GSAP staggered cards */
                    <div className="max-h-[640px] overflow-y-auto pr-1.5 scrollbar-thin">
                        <div className="grid gap-3.5 sm:grid-cols-1 md:grid-cols-2">
                            {filteredLogs.map((item) => {
                                const modMeta = getModuleMeta(item.module);
                                const ModuleIcon = modMeta.icon;
                                const actorName = item.actor?.name || item.actor?.email || "System";
                                const actorPhoto = item.actor?.profile_photo_url || item.actor?.profile_image;
                                const narrative = getActionNarrative(item);
                                const meta = item.meta || {};
                                const isRecent = isActivityRecent(item.created_at);

                                return (
                                    <div
                                        key={item.id}
                                        onMouseEnter={handleCardMouseEnter}
                                        onMouseLeave={handleCardMouseLeave}
                                        onClick={() => setSelectedActivity(item)}
                                        className="activity-log-card group relative flex cursor-pointer flex-col justify-between rounded-2xl border border-slate-200/80 bg-white/80 p-4.5 shadow-2xs transition-all duration-200 hover:border-indigo-300/80 hover:bg-white hover:shadow-md hover:shadow-indigo-500/5 dark:border-slate-800/80 dark:bg-slate-900/70 dark:hover:border-indigo-500/40 dark:hover:bg-slate-900"
                                    >
                                        <div>
                                            {/* Top Row: Module Badge + Live Ping + Action Pill */}
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <div className="flex items-center gap-2">
                                                    <span
                                                        className={`inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-semibold ${modMeta.colorClass}`}
                                                    >
                                                        <ModuleIcon className="h-3.5 w-3.5 shrink-0" />
                                                        <span>{modMeta.label}</span>
                                                    </span>

                                                    {/* Blinking Live dot for Recent event */}
                                                    {isRecent && (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 ring-1 ring-inset ring-emerald-500/20 dark:bg-emerald-950/50 dark:text-emerald-300">
                                                            <span className="relative flex h-1.5 w-1.5">
                                                                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                                                <span className="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                            </span>
                                                            LIVE
                                                        </span>
                                                    )}
                                                </div>

                                                <div>{renderActionPill(item.action)}</div>
                                            </div>

                                            {/* Narrative Description */}
                                            <p className="mt-3 text-sm font-semibold leading-snug text-slate-900 dark:text-slate-100">
                                                {narrative}
                                            </p>

                                            {/* Metadata chips if present */}
                                            {(meta.day_number || meta.project_code || meta.from || meta.scope || meta.task_code) && (
                                                <div className="mt-2.5 flex flex-wrap items-center gap-1.5">
                                                    {meta.day_number && (
                                                        <span className="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                            <Calendar className="h-3 w-3 text-slate-400" />
                                                            Day {meta.day_number}
                                                        </span>
                                                    )}
                                                    {(meta.project_code || meta.project_id) && (
                                                        <span className="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-2 py-0.5 text-[11px] font-medium text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">
                                                            <Tag className="h-3 w-3 text-indigo-400" />
                                                            {meta.project_code || `PRJ-${meta.project_id}`}
                                                        </span>
                                                    )}
                                                    {meta.task_code && (
                                                        <span className="inline-flex items-center gap-1 rounded-md bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">
                                                            <ListTodo className="h-3 w-3 text-blue-400" />
                                                            {meta.task_code}
                                                        </span>
                                                    )}
                                                    {meta.scope && (
                                                        <span className="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                            Scope: {meta.scope}
                                                        </span>
                                                    )}
                                                    {meta.from && meta.to && (
                                                        <span className="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                            <ArrowLeftRight className="h-3 w-3 text-slate-400" />
                                                            {meta.from} → {meta.to}
                                                        </span>
                                                    )}
                                                </div>
                                            )}

                                            {/* Notes / Remarks quote if present */}
                                            {meta.notes && (
                                                <div className="mt-2.5 rounded-xl border border-slate-200/80 bg-slate-50/70 p-2.5 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300">
                                                    <p className="line-clamp-2 italic">"{meta.notes}"</p>
                                                </div>
                                            )}
                                        </div>

                                        {/* Bottom Row: Actor & Timestamp */}
                                        <div className="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 dark:border-slate-800/80">
                                            <div className="flex items-center gap-2.5 min-w-0">
                                                {actorPhoto ? (
                                                    <img
                                                        src={actorPhoto}
                                                        alt={actorName}
                                                        className="h-7 w-7 shrink-0 rounded-full object-cover ring-2 ring-slate-100 dark:ring-slate-800"
                                                    />
                                                ) : (
                                                    <div
                                                        className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-[11px] font-bold shadow-2xs ring-2 ring-slate-100 dark:ring-slate-800 ${getAvatarColor(
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

                                            <div className="flex items-center gap-2">
                                                <div
                                                    className="shrink-0 text-right text-[11px] text-slate-500 dark:text-slate-400"
                                                    title={formatFullDate(item.created_at)}
                                                >
                                                    <div className="flex items-center gap-1">
                                                        <Clock className="h-3 w-3 text-slate-400" />
                                                        <span>{getRelativeTime(item.created_at)}</span>
                                                    </div>
                                                </div>
                                                <span className="text-[10px] font-medium text-indigo-600 opacity-0 transition-opacity group-hover:opacity-100 dark:text-indigo-400">
                                                    Inspect →
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                ) : (
                    /* Timeline View with GSAP target cards */
                    <div className="max-h-[640px] overflow-y-auto pl-4 pr-1.5 scrollbar-thin">
                        <div className="relative border-l-2 border-slate-200/80 dark:border-slate-800 ml-2 space-y-6 pl-6 py-2">
                            {filteredLogs.map((item) => {
                                const modMeta = getModuleMeta(item.module);
                                const ModuleIcon = modMeta.icon;
                                const actorName = item.actor?.name || item.actor?.email || "System";
                                const narrative = getActionNarrative(item);
                                const meta = item.meta || {};
                                const isRecent = isActivityRecent(item.created_at);

                                return (
                                    <div
                                        key={item.id}
                                        onMouseEnter={handleCardMouseEnter}
                                        onMouseLeave={handleCardMouseLeave}
                                        onClick={() => setSelectedActivity(item)}
                                        className="activity-log-card relative group cursor-pointer"
                                    >
                                        {/* Timeline Dot Icon */}
                                        <span className="absolute -left-[35px] top-2 flex h-7 w-7 items-center justify-center rounded-full bg-white dark:bg-slate-950 ring-4 ring-slate-100 dark:ring-slate-900 shadow-xs border border-slate-200 dark:border-slate-800 text-indigo-600 dark:text-indigo-400">
                                            <ModuleIcon className="h-3.5 w-3.5" />
                                        </span>

                                        <div className="rounded-2xl border border-slate-200/80 bg-white/90 p-4.5 shadow-2xs transition-all hover:border-indigo-300 hover:bg-white hover:shadow-md dark:border-slate-800 dark:bg-slate-900/80 dark:hover:border-indigo-500/40 dark:hover:bg-slate-900">
                                            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2.5 dark:border-slate-800/60">
                                                <div className="flex items-center gap-2">
                                                    <span className={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-semibold border ${modMeta.colorClass}`}>
                                                        {modMeta.label}
                                                    </span>
                                                    {isRecent && (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 ring-1 ring-inset ring-emerald-500/20 dark:bg-emerald-950/50 dark:text-emerald-300">
                                                            <span className="relative flex h-1.5 w-1.5">
                                                                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                                                <span className="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                            </span>
                                                            LIVE
                                                        </span>
                                                    )}
                                                    <div>{renderActionPill(item.action)}</div>
                                                </div>
                                                <span
                                                    className="flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400"
                                                    title={formatFullDate(item.created_at)}
                                                >
                                                    <Clock className="h-3 w-3 text-slate-400" />
                                                    {getRelativeTime(item.created_at)} • {formatFullDate(item.created_at)}
                                                </span>
                                            </div>

                                            <p className="mt-2.5 text-sm font-semibold text-slate-900 dark:text-slate-100">
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

                                                {(meta.day_number || meta.project_code || meta.task_code) && (
                                                    <div className="flex items-center gap-2">
                                                        {meta.day_number && (
                                                            <span className="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                                Day {meta.day_number}
                                                            </span>
                                                        )}
                                                        {meta.project_code && (
                                                            <span className="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">
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

            {/* Interactive Activity Detail Modal */}
            <Modal
                show={selectedActivity !== null}
                onClose={() => {
                    setSelectedActivity(null);
                    setShowRawJson(false);
                }}
                maxWidth="2xl"
                topCloseButton={true}
            >
                {selectedActivity && (
                    <div className="p-6">
                        {/* Modal Header */}
                        <div className="flex items-center justify-between border-b border-slate-100 pb-4 dark:border-slate-800">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400">
                                    <Activity className="h-5 w-5" />
                                </div>
                                <div>
                                    <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                        Activity Event Details
                                    </h3>
                                    <p className="text-xs text-slate-500 dark:text-slate-400">
                                        Event ID #{selectedActivity.id} • {formatFullDate(selectedActivity.created_at)}
                                    </p>
                                </div>
                            </div>
                            <div>{renderActionPill(selectedActivity.action)}</div>
                        </div>

                        {/* Modal Body */}
                        <div className="mt-5 space-y-4">
                            {/* Narrative Quote */}
                            <div className="rounded-2xl border border-indigo-100 bg-indigo-50/50 p-4 dark:border-indigo-900/60 dark:bg-indigo-950/40">
                                <p className="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                                    Action Summary
                                </p>
                                <p className="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                                    {getActionNarrative(selectedActivity)}
                                </p>
                            </div>

                            {/* Core Metadata Grid */}
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                <div className="rounded-xl border border-slate-100 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-900">
                                    <p className="text-[11px] font-medium text-slate-500 dark:text-slate-400">Module</p>
                                    <p className="mt-0.5 text-xs font-bold text-slate-900 dark:text-white">
                                        {formatModuleName(selectedActivity.module)}
                                    </p>
                                </div>

                                <div className="rounded-xl border border-slate-100 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-900">
                                    <p className="text-[11px] font-medium text-slate-500 dark:text-slate-400">Performed By</p>
                                    <p className="mt-0.5 truncate text-xs font-bold text-slate-900 dark:text-white">
                                        {selectedActivity.actor?.name || selectedActivity.actor?.email || "System"}
                                    </p>
                                </div>

                                <div className="rounded-xl border border-slate-100 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-900">
                                    <p className="text-[11px] font-medium text-slate-500 dark:text-slate-400">Timestamp</p>
                                    <p className="mt-0.5 text-xs font-bold text-slate-900 dark:text-white">
                                        {getRelativeTime(selectedActivity.created_at)}
                                    </p>
                                </div>
                            </div>

                            {/* Extended Meta Tags if present */}
                            {selectedActivity.meta && Object.keys(selectedActivity.meta).length > 0 && (
                                <div className="rounded-2xl border border-slate-200/90 bg-white p-4 shadow-2xs dark:border-slate-800 dark:bg-slate-900">
                                    <div className="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
                                        <p className="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                            Event Parameters
                                        </p>
                                        <button
                                            type="button"
                                            onClick={() => setShowRawJson(!showRawJson)}
                                            className="text-[11px] font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                                        >
                                            {showRawJson ? "Show Formatted" : "View Raw JSON"}
                                        </button>
                                    </div>

                                    {showRawJson ? (
                                        <pre className="mt-3 max-h-48 overflow-auto rounded-xl bg-slate-950 p-3 text-[11px] text-emerald-400 font-mono">
                                            {JSON.stringify(selectedActivity.meta, null, 2)}
                                        </pre>
                                    ) : (
                                        <div className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                            {Object.entries(selectedActivity.meta).map(([key, val]) => (
                                                <div key={key} className="rounded-lg bg-slate-50 p-2 text-xs dark:bg-slate-800/60">
                                                    <span className="font-semibold text-slate-500 dark:text-slate-400">
                                                        {key.replace(/_/g, " ")}:{" "}
                                                    </span>
                                                    <span className="font-bold text-slate-900 dark:text-white">
                                                        {typeof val === "object" ? JSON.stringify(val) : String(val)}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>

                        {/* Modal Footer */}
                        <div className="mt-6 flex justify-end border-t border-slate-100 pt-4 dark:border-slate-800">
                            <button
                                type="button"
                                onClick={() => {
                                    setSelectedActivity(null);
                                    setShowRawJson(false);
                                }}
                                className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                )}
            </Modal>
        </section>
    );
}
