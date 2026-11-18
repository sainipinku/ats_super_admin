import { useState, useEffect, useRef, useCallback, useMemo } from "react";
import gsap from "gsap";
import { usePage } from "@inertiajs/react";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import WorkflowTracker from "@/Pages/Construction/Components/WorkflowTracker";
import DynamicChecklistManager from "@/Pages/Construction/Components/DynamicChecklistManager";
import FieldNotesDisplay from "@/Pages/Construction/Components/FieldNotesDisplay";
import Modal from "@/Components/Modal";
import ProjectActivityLog from "@/Pages/Construction/Components/ProjectActivityLog";
import AssignToDraftsmanButton from "@/Pages/Construction/Components/AssignToDraftsmanButton";

const TASKS_PER_PAGE = 10;
const EXECUTION_TASKS_TITLE_WORDS = ["Execution", "Tasks"];

const prefersReducedMotion = () =>
    typeof window !== "undefined" &&
    typeof window.matchMedia === "function" &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches;

export default function ProjectShow({ project, activityLog, workflowSummary, companyDraftsmen = [] }) {
    const { auth } = usePage().props;
    const userRole = (auth?.user?.role || "").toLowerCase();
    const activeRole = auth?.active_role;
    const activeRoleSlug = String(activeRole?.slug || activeRole || "").toLowerCase();
    const isRestrictedRole = Boolean(
        activeRoleSlug &&
        !['super_admin', 'superadmin', 'crm_super_admin', 'admin', 'project_admin'].includes(activeRoleSlug)
    );

    const isProjectOwner = !isRestrictedRole && Boolean(
        auth?.user?.is_project_owner == 1 ||
        auth?.user?.is_project_owner === true ||
        auth?.user?.id == 1 ||
        auth?.user?.id === '1' ||
        (!auth?.user?.company_id && auth?.guard === "superadmin")
    );

    const isSuperAdmin = !isRestrictedRole && (
        isProjectOwner ||
        auth?.guard === "superadmin" ||
        (Array.isArray(auth?.user?.roles) && (auth?.user?.roles.includes("super_admin") || auth?.user?.roles.includes("superadmin")))
    );

    const isRootSuperAdmin = isProjectOwner || isSuperAdmin;
    const userPermissions = Array.isArray(auth?.permissions) ? auth.permissions : [];

    const hasPerm = (perm) => {
        if (isProjectOwner || isSuperAdmin) return true;
        return userPermissions.includes(perm);
    };

    const canManageChecklist = hasPerm("execution.create") || hasPerm("execution.edit");
    const canAssignDraftsman = isRootSuperAdmin || hasPerm("execution.edit") || hasPerm("execution.manage");

    const projectDraftsmenList = getProjectDraftsmen(project.team_members).map((m) => ({
        ...m,
        is_project_member: true,
    }));
    const defaultDraftsmanId = projectDraftsmenList[0]?.member_id ? String(projectDraftsmenList[0].member_id) : "";
    const allAdminDraftsmen = (() => {
        const map = new Map();
        projectDraftsmenList.forEach((m) => map.set(String(m.member_id || m.id), m));
        (Array.isArray(companyDraftsmen) ? companyDraftsmen : []).forEach((m) => {
            const id = String(m.member_id || m.id || "");
            if (id && !map.has(id)) {
                map.set(id, { ...m, is_project_member: Boolean(m.is_project_member) });
            }
        });
        return Array.from(map.values());
    })();

    const metricsGridRef = useRef(null);

    useEffect(() => {
        if (!metricsGridRef.current) return;

        const ctx = gsap.context(() => {
            const cards = metricsGridRef.current.querySelectorAll(".stat-card-item");
            if (cards.length > 0) {
                gsap.fromTo(
                    cards,
                    { opacity: 0, y: 12, scale: 0.96 },
                    {
                        opacity: 1,
                        y: 0,
                        scale: 1,
                        duration: 0.34,
                        stagger: 0.03,
                        ease: "power2.out",
                        clearProps: "transform,opacity",
                    }
                );
            }
        }, metricsGridRef);

        return () => ctx.revert();
    }, [project.id]);

    const checklistCounts = workflowSummary?.checklist_counts ?? { total: 0, completed: 0 };
    const checklistCompletion = checklistCounts.total > 0
        ? Math.round((checklistCounts.completed / checklistCounts.total) * 100)
        : 0;

    // Execution Tasks pagination (10 per batch, 2 per row) + GSAP choreography
    const allAdminTasks = useMemo(() => project.execution_tasks || [], [project.execution_tasks]);
    const [visibleTaskCount, setVisibleTaskCount] = useState(TASKS_PER_PAGE);
    const [isLoadingMoreTasks, setIsLoadingMoreTasks] = useState(false);
    const [isExecutionInView, setIsExecutionInView] = useState(false);
    const executionTasksRef = useRef(null);
    const taskLoaderRef = useRef(null);
    const prevVisibleTaskCountRef = useRef(0);
    const loadMoreTasksTimerRef = useRef(null);

    const visibleAdminTasks = useMemo(
        () => allAdminTasks.slice(0, visibleTaskCount),
        [allAdminTasks, visibleTaskCount]
    );
    const remainingTasks = Math.max(0, allAdminTasks.length - visibleAdminTasks.length);
    const hasMoreTasks = remainingTasks > 0;

    useEffect(() => {
        if (loadMoreTasksTimerRef.current) {
            clearTimeout(loadMoreTasksTimerRef.current);
            loadMoreTasksTimerRef.current = null;
        }
        setIsLoadingMoreTasks(false);
        prevVisibleTaskCountRef.current = 0;
        setVisibleTaskCount(TASKS_PER_PAGE);
    }, [project.id]);

    useEffect(() => {
        return () => {
            if (loadMoreTasksTimerRef.current) {
                clearTimeout(loadMoreTasksTimerRef.current);
                loadMoreTasksTimerRef.current = null;
            }
        };
    }, []);

    useEffect(() => {
        const el = executionTasksRef.current;
        if (!el || typeof IntersectionObserver === "undefined") {
            setIsExecutionInView(true);
            return undefined;
        }
        const observer = new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    setIsExecutionInView(true);
                    observer.disconnect();
                }
            },
            { threshold: 0.05 }
        );
        observer.observe(el);
        return () => observer.disconnect();
    }, []);

    useEffect(() => {
        if (!isExecutionInView || !executionTasksRef.current || prefersReducedMotion()) return undefined;
        const ctx = gsap.context(() => {
            const tl = gsap.timeline();
            tl.fromTo(
                ".et-header-card",
                { opacity: 0, scale: 1.045, y: 14 },
                { opacity: 1, scale: 1, y: 0, duration: 0.52, ease: "power3.out", clearProps: "transform,opacity" }
            );
            tl.fromTo(
                ".et-title-word",
                { y: 20, opacity: 0, rotateX: -24, scale: 1.08 },
                { y: 0, opacity: 1, rotateX: 0, scale: 1, duration: 0.5, stagger: 0.085, ease: "back.out(1.7)", clearProps: "transform,opacity" },
                "-=0.35"
            );
        }, executionTasksRef);
        return () => ctx.revert();
    }, [isExecutionInView]);

    useEffect(() => {
        if (!isExecutionInView || !executionTasksRef.current || prefersReducedMotion()) {
            prevVisibleTaskCountRef.current = visibleAdminTasks.length;
            return undefined;
        }
        const startIdx = prevVisibleTaskCountRef.current;
        const allCards = Array.from(executionTasksRef.current.querySelectorAll(".et-task-card"));
        const targetCards = allCards.filter((card) => Number(card.getAttribute("data-et-index")) >= startIdx);
        if (!targetCards.length) {
            prevVisibleTaskCountRef.current = visibleAdminTasks.length;
            return undefined;
        }
        const ctx = gsap.context(() => {
            gsap.fromTo(
                targetCards,
                { opacity: 0, scale: 1.04, y: 16 },
                { opacity: 1, scale: 1, y: 0, duration: 0.44, stagger: 0.055, ease: "power3.out", clearProps: "transform,opacity" }
            );
        }, executionTasksRef);
        prevVisibleTaskCountRef.current = visibleAdminTasks.length;
        return () => ctx.revert();
    }, [isExecutionInView, visibleAdminTasks.length]);

    useEffect(() => {
        if (!isLoadingMoreTasks || !taskLoaderRef.current || prefersReducedMotion()) return undefined;
        const ctx = gsap.context(() => {
            gsap.fromTo(".et-load-more-loader", { opacity: 0, scale: 0.92 }, { opacity: 1, scale: 1, duration: 0.22, ease: "back.out(2)" });
            gsap.to(".et-loader-ring", { rotation: 360, duration: 0.72, repeat: -1, ease: "none", transformOrigin: "50% 50%" });
            gsap.to(".et-loader-dot", { y: -5, scale: 1.35, opacity: 1, duration: 0.34, stagger: 0.1, repeat: -1, yoyo: true, ease: "sine.inOut" });
        }, taskLoaderRef);
        return () => ctx.revert();
    }, [isLoadingMoreTasks]);

    const handleLoadMoreTasks = useCallback(() => {
        if (isLoadingMoreTasks || !hasMoreTasks) return;
        prevVisibleTaskCountRef.current = visibleAdminTasks.length;
        if (prefersReducedMotion()) {
            setVisibleTaskCount((prev) => Math.min(prev + TASKS_PER_PAGE, allAdminTasks.length));
            return;
        }
        setIsLoadingMoreTasks(true);
        loadMoreTasksTimerRef.current = setTimeout(() => {
            setVisibleTaskCount((prev) => Math.min(prev + TASKS_PER_PAGE, allAdminTasks.length));
            setIsLoadingMoreTasks(false);
            loadMoreTasksTimerRef.current = null;
        }, 460);
    }, [isLoadingMoreTasks, hasMoreTasks, visibleAdminTasks.length, allAdminTasks.length]);

    const handleShowLessTasks = useCallback(() => {
        if (loadMoreTasksTimerRef.current) {
            clearTimeout(loadMoreTasksTimerRef.current);
            loadMoreTasksTimerRef.current = null;
        }
        setIsLoadingMoreTasks(false);
        prevVisibleTaskCountRef.current = 0;
        setVisibleTaskCount(TASKS_PER_PAGE);
    }, []);

    const handleTaskPopEnter = useCallback((e, { scale = 1.014, y = -3 } = {}) => {
        if (prefersReducedMotion()) return;
        gsap.to(e.currentTarget, { scale, y, duration: 0.24, ease: "back.out(2.2)", overwrite: "auto" });
    }, []);

    const handleTaskPopLeave = useCallback((e) => {
        if (prefersReducedMotion()) return;
        gsap.to(e.currentTarget, { scale: 1, y: 0, duration: 0.24, ease: "power2.out", overwrite: "auto" });
    }, []);

    return (
        <ConstructionShell title={project.name} description={`${project.project_code} • ${project.company?.name || ""}`} variant="admin">
            <WorkflowTracker currentStage={project.current_stage} isLocked={project.is_locked} />

            <div ref={metricsGridRef} className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-6">
                <StatCard compact label="Team Members" value={project.team_members?.length || 0} />
                <StatCard compact label="Budget Versions" value={project.budgets?.length || 0} />
                <StatCard compact label="Survey Plans" value={project.survey_plans?.length || 0} />
                <StatCard compact label="Submissions" value={project.survey_submissions?.length || 0} />
                <StatCard compact label="Drafting Jobs" value={project.drafting_jobs?.length || 0} />
                <StatCard compact label="Approvals" value={project.drawing_approvals?.length || 0} />
            </div>

            <div ref={executionTasksRef} className="et-stage w-full space-y-3.5">
                <div className="et-header-card relative overflow-hidden rounded-2xl border border-slate-200/90 bg-white p-3.5 shadow-sm dark:border-slate-800/90 dark:bg-slate-900/95">
                    <div className="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-indigo-500 via-violet-500 to-sky-500 opacity-85" />
                    <div className="flex flex-wrap items-center justify-between gap-2.5">
                        <div className="flex items-center gap-2.5 min-w-0">
                            <span
                                onMouseEnter={(e) => handleTaskPopEnter(e, { scale: 1.08, y: -2 })}
                                onMouseLeave={handleTaskPopLeave}
                                className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500/15 to-sky-500/15 text-base text-indigo-700 ring-1 ring-indigo-500/20 dark:from-indigo-500/20 dark:to-sky-500/20 dark:text-indigo-300"
                            >
                                ⚡
                            </span>
                            <div className="min-w-0">
                                <h2
                                    aria-label="Execution Tasks"
                                    className="flex flex-wrap items-center gap-x-1.5 text-base font-extrabold tracking-tight text-slate-900 dark:text-white"
                                >
                                    {EXECUTION_TASKS_TITLE_WORDS.map((word) => (
                                        <span key={word} className="inline-flex overflow-hidden py-0.5">
                                            <span className="et-title-word inline-block bg-gradient-to-r from-slate-900 via-indigo-900 to-slate-900 bg-clip-text text-transparent dark:from-white dark:via-indigo-200 dark:to-white">
                                                {word}
                                            </span>
                                        </span>
                                    ))}
                                </h2>
                                <p className="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                    Monitor Surveyor task progress and assign completed Surveyor work to a Draftsman.
                                    {allAdminTasks.length > 0 && (
                                        <span className="ml-1.5 text-[10px] font-medium text-slate-400 dark:text-slate-500">
                                            • Showing {visibleAdminTasks.length} of {allAdminTasks.length}
                                        </span>
                                    )}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {visibleAdminTasks.length ? (
                    <div className="space-y-3">
                        <div className="grid gap-3 md:grid-cols-2">
                            {visibleAdminTasks.map((task, taskIndex) => (
                                <div
                                    key={task.id}
                                    data-et-index={taskIndex}
                                    onMouseEnter={(e) => handleTaskPopEnter(e, { scale: 1.014, y: -3 })}
                                    onMouseLeave={handleTaskPopLeave}
                                    className="et-task-card rounded-xl border border-slate-200/90 bg-white p-3 shadow-sm transition-colors hover:border-indigo-300/80 hover:shadow-md dark:border-slate-800 dark:bg-slate-900/95 dark:hover:border-indigo-500/40"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-1.5">
                                                <span className="inline-flex items-center rounded bg-indigo-50 px-1.5 py-0.5 font-mono text-[10px] font-bold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">
                                                    {task.task_code}
                                                </span>
                                                <div className="scale-90 origin-left">
                                                    <StatusBadge value={task.status} />
                                                </div>
                                            </div>
                                            <p className="et-task-title mt-1 truncate text-xs sm:text-sm font-bold text-slate-900 dark:text-white">
                                                {task.title}
                                            </p>
                                            <p className="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                                Surveyor: <span className="font-semibold text-slate-700 dark:text-slate-200">{task.supervisor?.name || "Unassigned"}</span>
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-1.5">
                                            <AssignToDraftsmanButton
                                                task={task}
                                                projectId={project.id}
                                                taskSource="execution"
                                                routeName="admin.construction.projects.tasks.assign-draftsman"
                                                draftsmen={allAdminDraftsmen}
                                                defaultDraftsmanId={defaultDraftsmanId}
                                                authorized={canAssignDraftsman}
                                            />
                                        </div>
                                    </div>
                                    {task.assignees?.length ? (
                                        <div className="mt-2 flex flex-wrap gap-1.5 border-t border-slate-100 pt-2 dark:border-slate-800">
                                            {task.assignees.map((assignment) => (
                                                <span key={assignment.id} className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                    {assignment.member?.name || "Employee"} • {assignment.assignment_role}
                                                </span>
                                            ))}
                                        </div>
                                    ) : null}
                                </div>
                            ))}
                        </div>

                        {(hasMoreTasks || visibleAdminTasks.length > TASKS_PER_PAGE) && (
                            <div
                                ref={taskLoaderRef}
                                className="flex flex-wrap items-center justify-center gap-3 rounded-xl border border-slate-200/80 bg-white/90 px-4 py-2.5 shadow-sm dark:border-slate-800 dark:bg-slate-900/80"
                            >
                                <span className="text-[11px] font-medium text-slate-500 dark:text-slate-400">
                                    Showing <strong className="font-bold text-slate-800 dark:text-slate-200">{visibleAdminTasks.length}</strong> of{" "}
                                    <strong className="font-bold text-slate-800 dark:text-slate-200">{allAdminTasks.length}</strong> tasks
                                </span>

                                {hasMoreTasks && (
                                    <button
                                        type="button"
                                        onClick={handleLoadMoreTasks}
                                        disabled={isLoadingMoreTasks}
                                        onMouseEnter={(e) => handleTaskPopEnter(e, { scale: 1.04, y: -1.5 })}
                                        onMouseLeave={handleTaskPopLeave}
                                        className="et-load-more-btn inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm shadow-indigo-500/20 transition hover:from-indigo-500 hover:to-violet-500 disabled:cursor-wait disabled:opacity-90"
                                    >
                                        {isLoadingMoreTasks ? (
                                            <span className="et-load-more-loader inline-flex items-center gap-2">
                                                <svg className="et-loader-ring h-3.5 w-3.5 text-white" viewBox="0 0 24 24" fill="none">
                                                    <circle cx="12" cy="12" r="9" stroke="currentColor" strokeOpacity="0.3" strokeWidth="3" />
                                                    <path d="M12 3a9 9 0 0 1 9 9" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
                                                </svg>
                                                <span>Loading next {Math.min(TASKS_PER_PAGE, remainingTasks)}</span>
                                                <span className="inline-flex items-center gap-1">
                                                    <span className="et-loader-dot h-1.5 w-1.5 rounded-full bg-white/80" />
                                                    <span className="et-loader-dot h-1.5 w-1.5 rounded-full bg-white/80" />
                                                    <span className="et-loader-dot h-1.5 w-1.5 rounded-full bg-white/80" />
                                                </span>
                                            </span>
                                        ) : (
                                            <>
                                                <span>Load More (+{Math.min(TASKS_PER_PAGE, remainingTasks)} of {remainingTasks} left)</span>
                                                <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </>
                                        )}
                                    </button>
                                )}

                                {visibleAdminTasks.length > TASKS_PER_PAGE && !isLoadingMoreTasks && (
                                    <button
                                        type="button"
                                        onClick={handleShowLessTasks}
                                        onMouseEnter={(e) => handleTaskPopEnter(e, { scale: 1.04, y: -1 })}
                                        onMouseLeave={handleTaskPopLeave}
                                        className="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                                    >
                                        Show Less
                                    </button>
                                )}
                            </div>
                        )}
                    </div>
                ) : (
                    <EmptyState title="No execution tasks yet." description="Tasks will appear after the project execution plan is created." />
                )}
            </div>

            <div className="grid gap-6 xl:grid-cols-[1.5fr,1fr]">
                <SectionCard title="Project Summary" description="Current project context for assigned admin execution.">
                    <div className="flex flex-wrap gap-3">
                        <StatusBadge value={project.status} />
                        <StatusBadge value={project.current_stage} />
                        <StatusBadge value={project.priority} />
                    </div>
                    <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                        <Field label="Client" value={project.client?.name} />
                        <Field label="Company" value={project.company?.name} />
                        <Field label="Start Date" value={formatDate(project.start_date)} />
                        <Field label="Expected End" value={formatDate(project.expected_end_date)} />
                        <LocationDisplay
                            className="sm:col-span-2"
                            locationName={project.location_name}
                            address={project.project_address}
                            lat={project.latitude}
                            lng={project.longitude}
                        />
                        <Field label="Description" value={project.description} span="sm:col-span-2" />
                    </dl>
                </SectionCard>

                <SectionCard title="Current Team" description="Assigned roles for project execution and reviews.">
                    {project.team_members?.length ? (
                        <div className="space-y-3">
                            {project.team_members.map((teamMember) => {
                                const memberId = teamMember.member_id;
                                const allLogs = [];

                                (project.attendance_records || []).forEach((r) => {
                                    if (Number(r.member_id) === Number(memberId)) {
                                        allLogs.push({
                                            check_in_at: r.check_in_at,
                                            check_out_at: r.check_out_at,
                                            duration_minutes: r.duration_minutes || null,
                                        });
                                    }
                                });

                                (project.survey_plans || []).forEach((plan) => {
                                    (plan.visits || []).forEach((v) => {
                                        const checkedInId = v.checked_in_by_member_id || v.checkedInBy?.id || v.checked_in_by?.id;
                                        if (Number(checkedInId) === Number(memberId)) {
                                            allLogs.push({
                                                check_in_at: v.check_in_at,
                                                check_out_at: v.check_out_at,
                                                duration_minutes: v.duration_minutes || null,
                                            });
                                        }
                                    });
                                });

                                if (Array.isArray(project.survey_visits)) {
                                    project.survey_visits.forEach((v) => {
                                        const checkedInId = v.checked_in_by_member_id || v.checkedInBy?.id || v.checked_in_by?.id;
                                        if (Number(checkedInId) === Number(memberId)) {
                                            allLogs.push({
                                                check_in_at: v.check_in_at,
                                                check_out_at: v.check_out_at,
                                                duration_minutes: v.duration_minutes || null,
                                            });
                                        }
                                    });
                                }

                                const map = new Map();
                                allLogs.forEach((log) => {
                                    if (!log.check_in_at) return;
                                    const key = new Date(log.check_in_at).getTime();
                                    if (!map.has(key)) {
                                        map.set(key, { ...log });
                                    } else {
                                        const existing = map.get(key);
                                        if (!existing.check_out_at && log.check_out_at) {
                                            existing.check_out_at = log.check_out_at;
                                        }
                                        if (!existing.duration_minutes && log.duration_minutes) {
                                            existing.duration_minutes = log.duration_minutes;
                                        }
                                    }
                                });

                                const sortedLogs = [...map.values()].sort((a, b) => {
                                    const at = a.check_in_at ? new Date(a.check_in_at).getTime() : 0;
                                    const bt = b.check_in_at ? new Date(b.check_in_at).getTime() : 0;
                                    return bt - at;
                                });

                                const latestLog = sortedLogs[0];

                                let totalMinutes = 0;
                                sortedLogs.forEach((r) => {
                                    if (r.check_in_at && r.check_out_at) {
                                        const diff = Math.max(0, (new Date(r.check_out_at).getTime() - new Date(r.check_in_at).getTime()) / 60000);
                                        totalMinutes += diff;
                                    } else if (r.duration_minutes) {
                                        totalMinutes += Number(r.duration_minutes);
                                    } else if (r.check_in_at && !r.check_out_at) {
                                        const diff = Math.max(0, (new Date().getTime() - new Date(r.check_in_at).getTime()) / 60000);
                                        totalMinutes += diff;
                                    }
                                });

                                const hrs = Math.floor(totalMinutes / 60);
                                const mins = Math.floor(totalMinutes % 60);
                                const workingHoursStr = totalMinutes > 0 ? `${hrs}h ${mins}m` : "0h 0m";

                                const formatTime = (ts) => {
                                    if (!ts) return "-";
                                    const d = new Date(ts);
                                    if (isNaN(d.getTime())) return "-";
                                    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                                };

                                const checkInTimeStr = latestLog?.check_in_at ? formatTime(latestLog.check_in_at) : "-";
                                const checkOutTimeStr = latestLog?.check_out_at ? formatTime(latestLog.check_out_at) : (latestLog?.check_in_at ? "Checked In (Active)" : "-");
                                const isCheckedIn = !!(latestLog?.check_in_at && !latestLog?.check_out_at);

                                return (
                                    <div key={teamMember.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800">
                                        <div className="flex items-center justify-between gap-3">
                                            <div>
                                                <p className="font-medium text-slate-900 dark:text-white">{teamMember.member?.name || "Unknown member"}</p>
                                                <p className="text-sm text-slate-500">{teamMember.role?.name || "No role assigned"}</p>
                                            </div>
                                            <StatusBadge value={teamMember.status} />
                                        </div>

                                        <div className="mt-3 grid gap-2 sm:grid-cols-3 rounded-xl border border-slate-200/80 bg-white p-3 dark:border-slate-800 dark:bg-slate-950">
                                            <div className="flex items-center gap-2">
                                                <span className="text-xs font-semibold text-slate-500">📥 Check-In:</span>
                                                <span className="text-xs font-bold text-slate-800 dark:text-slate-200">{checkInTimeStr}</span>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <span className="text-xs font-semibold text-slate-500">📤 Check-Out:</span>
                                                <span className={`text-xs font-bold ${isCheckedIn ? 'text-emerald-600 animate-pulse' : 'text-slate-800 dark:text-slate-200'}`}>
                                                    {checkOutTimeStr}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-2 sm:justify-end">
                                                <span className="text-xs font-semibold text-slate-500">⏱️ Working Hours:</span>
                                                <span className="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-bold text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                                                    {workingHoursStr}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <EmptyState title="No team members assigned." description="Ask super admin to complete Phase 1 role assignment before execution." />
                    )}
                </SectionCard>
            </div>

            <div className="grid gap-4 xl:grid-cols-2 items-stretch">
                <SectionCard
                    title="Survey Execution"
                    description="Plan status, submitted GPS/photos/reports, and field activity."
                    className="flex flex-col"
                    actions={
                        <span className="inline-flex items-center rounded-full border border-indigo-200/70 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-300">
                            {project.survey_plans?.length || 0} {(project.survey_plans?.length || 0) === 1 ? "Plan" : "Plans"}
                        </span>
                    }
                >
                    {project.survey_plans?.length ? (
                        <div className="space-y-4">
                            {project.survey_plans.map((plan) => (
                                <SurveyPlanCard key={plan.id} plan={plan} />
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No survey plans yet." description="Create the first survey plan from the survey module." />
                    )}
                </SectionCard>

                <SectionCard
                    title="Survey Submission Queue"
                    description="Submitted field-duty reports pending/approved before drafting."
                    className="flex flex-col"
                    actions={
                        <span className="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                            {project.survey_submissions?.length || 0} {(project.survey_submissions?.length || 0) === 1 ? "Submission" : "Submissions"}
                        </span>
                    }
                >
                    {project.survey_submissions?.length ? (
                        <div className="space-y-3">
                            {project.survey_submissions.map((submission) => (
                                <div key={submission.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex items-center justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="font-medium text-slate-900 dark:text-white">Visit #{submission.survey_visit_id}</p>
                                            <p className="mt-1 text-xs text-slate-500">
                                                Submitted by <span className="font-medium text-slate-700 dark:text-slate-200">{submission.submitted_by?.name || "Unknown"}</span>{" "}
                                                • {formatDateTime(submission.submitted_at) || "No timestamp"}
                                            </p>
                                        </div>
                                        <StatusBadge value={submission.status_key} />
                                    </div>
                                    <p className="mt-2 text-sm text-slate-500">
                                        Reviewed by{" "}
                                        {submission.reviewed_by?.name
                                            ? submission.reviewed_by.name
                                            : submission.reviewed_at
                                                ? "authorized reviewer"
                                                : "Pending review"}
                                    </p>
                                    <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
                                        {submission.review_notes || "No review notes yet."}
                                    </p>
                                    {submission.survey_visit ? (
                                        <div className="mt-4 grid gap-2 border-t border-slate-200 pt-3 text-xs text-slate-500 sm:grid-cols-2 dark:border-slate-700">
                                            <Metric label="Points" value={submission.survey_visit.total_points_captured} />
                                            <Metric label="Distance (m)" value={formatNumber(submission.survey_visit.distance_covered_m)} />
                                            <Metric label="Elevation (m)" value={formatNumber(submission.survey_visit.elevation_m)} />
                                            <Metric label="Day" value={submission.survey_visit.day_number} />
                                            <Metric label="Checked in" value={formatDateTime(submission.survey_visit.check_in_at)} span="sm:col-span-2" />
                                            <FileAttachment path={submission.survey_visit.file_path} span="sm:col-span-2" />
                                        </div>
                                    ) : null}
                                    {submission.survey_visit?.photos?.length ? (
                                        <PhotoGrid photos={submission.survey_visit.photos} className="mt-3" />
                                    ) : null}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No survey submissions yet." description="Submitted field-duty reports (from POST /survey-duty/submit-data) appear here with their GPS, photos, remarks, and captured file." />
                    )}
                </SectionCard>
            </div>

            <DynamicChecklistManager
                project={project}
                namespace="admin"
                variant="project-show"
                enableDeltaPoll
                canManage={canManageChecklist}
                initialCounts={{ total: checklistCounts.total, completed: checklistCounts.completed, completion: checklistCompletion }}
            />

            <ProjectActivityLog activityLog={activityLog} />
        </ConstructionShell>
    );
}

function getProjectDraftsmen(teamMembers = []) {
    const draftRoles = ["draft_person", "draftsman", "drafter", "draft_man"];

    return teamMembers
        .filter((teamMember) => {
            const roleText = [
                teamMember.role?.slug,
                teamMember.role?.name,
                teamMember.member?.role_names,
                teamMember.member?.assigned_role_names,
                teamMember.member?.designation,
            ].flat().join(" ")
                .toLowerCase()
                .replace(/[\s-]+/g, "_");
            return teamMember.status === "active" && draftRoles.some((role) => roleText.includes(role));
        })
        .map((teamMember) => ({
            member_id: teamMember.member_id,
            name: teamMember.member?.name || `Member #${teamMember.member_id}`,
            email: teamMember.member?.email || "",
        }));
}

function formatDate(dateString) {
    if (!dateString) return "-";
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;
    return date.toLocaleDateString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    });
}

function LocationDisplay({ locationName, address, lat, lng, className = "" }) {
    const hasCoords =
        lat != null && lng != null && lat !== "" && lng !== "" && Number.isFinite(Number(lat)) && Number.isFinite(Number(lng));
    const displayAddr = locationName || address;
    return (
        <div className={className}>
            <dt className="text-sm text-slate-500">📍 Location</dt>
            <dd className="mt-1 space-y-1 font-medium text-slate-900 dark:text-white">
                {displayAddr ? (
                    <div className="break-words leading-snug">{displayAddr}</div>
                ) : (
                    <div className="text-slate-400">Not set</div>
                )}
                {locationName && address && locationName !== address ? (
                    <div className="text-xs font-normal text-slate-500 break-words">
                        Raw address: {address}
                    </div>
                ) : null}
                {hasCoords ? (
                    <div className="flex flex-wrap items-center gap-3 text-xs font-normal text-slate-500">
                        <span>
                            Lat <span className="font-mono text-slate-700 dark:text-slate-300">{Number(lat).toFixed(6)}</span>
                            {" · "}
                            Lng <span className="font-mono text-slate-700 dark:text-slate-300">{Number(lng).toFixed(6)}</span>
                        </span>
                        <a
                            href={`https://www.google.com/maps?q=${encodeURIComponent(Number(lat))},${encodeURIComponent(Number(lng))}`}
                            target="_blank"
                            rel="noreferrer noopener"
                            className="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-indigo-600 transition hover:bg-indigo-50 hover:text-indigo-700 dark:border-slate-700 dark:bg-slate-900 dark:text-indigo-400 dark:hover:bg-slate-800"
                        >
                            Open in Maps
                        </a>
                    </div>
                ) : null}
            </dd>
        </div>
    );
}

function Field({ label, value, span = "" }) {
    return (
        <div className={span}>
            <dt className="text-sm text-slate-500">{label}</dt>
            <dd className="mt-1 font-medium text-slate-900 dark:text-white">{value || "-"}</dd>
        </div>
    );
}

function formatDateTime(value) {
    if (!value) return "";
    const d = new Date(value);
    if (isNaN(d.getTime())) return String(value);
    return d.toLocaleString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
}

function formatNumber(value) {
    if (value === null || value === undefined || value === "") return "-";
    const n = Number(value);
    if (!Number.isFinite(n)) return String(value);
    return new Intl.NumberFormat("en-IN", { maximumFractionDigits: 2 }).format(n);
}

function Metric({ label, value, span = "" }) {
    return (
        <div className={span}>
            <p className="text-[11px] uppercase tracking-wide text-slate-400">{label}</p>
            <p className="mt-0.5 font-semibold text-slate-800 dark:text-slate-100">{value ?? "-"}</p>
        </div>
    );
}

function FileAttachment({ path, span = "" }) {
    if (!path) return <div className={span} />;
    const name = String(path).split("/").pop() || "document";
    const href = /^https?:\/\//i.test(String(path)) ? path : String(path).startsWith("/") ? path : "/" + String(path).replace(/^\/+/, "");
    return (
        <div className={span}>
            <p className="text-[11px] uppercase tracking-wide text-slate-400">Report / File</p>
            <a
                href={href}
                target="_blank"
                rel="noreferrer noopener"
                className="mt-0.5 inline-flex items-center gap-1.5 rounded-md border border-indigo-200 bg-indigo-50 px-2 py-1 text-[11px] font-medium text-indigo-700 hover:bg-indigo-100 dark:border-indigo-900/40 dark:bg-indigo-950/30 dark:text-indigo-300 dark:hover:bg-indigo-950/60"
            >
                📄 <span className="truncate max-w-[220px]">{name}</span>
            </a>
        </div>
    );
}

function PhotoGrid({ photos = [], className = "" }) {
    const photoList = Array.isArray(photos) ? photos : [];
    if (!photoList.length) return null;
    return (
        <div className={`grid gap-2 sm:grid-cols-3 ${className}`}>
            {photoList.map((p, idx) => {
                const safe = typeof p === "string" ? p : p?.url || p?.path || "";
                if (!safe) return null;
                const href = /^https?:\/\//i.test(safe) ? safe : safe.startsWith("/") ? safe : "/" + safe.replace(/^\/+/, "");
                return (
                    <a
                        key={idx}
                        href={href}
                        target="_blank"
                        rel="noreferrer noopener"
                        className="group relative block aspect-video w-full overflow-hidden rounded-xl border border-slate-200 bg-slate-100 dark:border-slate-700 dark:bg-slate-800"
                    >
                        <img
                            src={href}
                            alt={`Survey photo ${idx + 1}`}
                            loading="lazy"
                            className="h-full w-full object-cover transition group-hover:scale-[1.03]"
                        />
                    </a>
                );
            })}
        </div>
    );
}

function SurveyVisitCard({ visit }) {
    const hasSubmitted = Boolean(visit.submission) || String(visit.status || "").toLowerCase() === "submitted";
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="font-semibold text-slate-900 dark:text-white">Visit #{visit.id}</p>
                    <p className="mt-1 text-xs text-slate-500">
                        Check-in by <span className="font-medium text-slate-700 dark:text-slate-200">{visit.checked_in_by?.name || visit.checkedInBy?.name || "Unknown"}</span>
                        {" • "}
                        {formatDateTime(visit.check_in_at) || "No timestamp"}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <StatusBadge value={visit.status_key || visit.status || (hasSubmitted ? "submitted" : "planned")} />
                    {visit.gps_verified ? (
                        <span className="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                            GPS Verified
                        </span>
                    ) : null}
                    {visit.day_number ? (
                        <span className="rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            Day {visit.day_number}
                        </span>
                    ) : null}
                </div>
            </div>

            {visit.submission ? (
                <div className="mt-3 rounded-xl bg-indigo-50/60 p-3 text-xs dark:bg-indigo-950/20">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="font-medium text-indigo-900 dark:text-indigo-100">Submission Status</p>
                        <StatusBadge value={visit.submission.status_key || visit.submission.status} />
                    </div>
                    <p className="mt-1 text-indigo-700/80 dark:text-indigo-200/80">
                        Submitted by {visit.submission.submitted_by?.name || visit.submission.submittedBy?.name || "Unknown"}
                        {" • "}
                        {formatDateTime(visit.submission.submitted_at) || "—"}
                    </p>
                </div>
            ) : null}

            <div className="mt-4 grid gap-2 text-xs sm:grid-cols-2">
                <Metric label="Total Points" value={visit.total_points_captured} />
                <Metric label="Distance (m)" value={formatNumber(visit.distance_covered_m)} />
                <Metric label="Elevation (m)" value={formatNumber(visit.elevation_m)} />
                <Metric label="Entries / Meas." value={`${visit.entries?.length || 0} / ${visit.measurements?.length || 0}`} />
                <Metric label="GPS Latitude" value={visit.check_in_latitude ? Number(visit.check_in_latitude).toFixed(6) : undefined} />
                <Metric label="GPS Longitude" value={visit.check_in_longitude ? Number(visit.check_in_longitude).toFixed(6) : undefined} />
                <FileAttachment path={visit.file_path} span="sm:col-span-2" />
            </div>

            {visit.remarks || visit.notes ? (
                <div className="mt-3 space-y-2">
                    {visit.remarks ? (
                        <div className="rounded-xl bg-amber-50/70 p-3 text-xs text-amber-900 dark:bg-amber-950/20 dark:text-amber-100">
                            <p className="font-semibold">Remarks</p>
                            <p className="mt-1 whitespace-pre-wrap break-words">{visit.remarks}</p>
                        </div>
                    ) : null}
                    {visit.notes ? (
                        <FieldNotesDisplay notes={visit.notes} />
                    ) : null}
                </div>
            ) : null}

            <PhotoGrid photos={visit.photos} className="mt-4" />
        </div>
    );
}

function SurveyPlanCard({ plan }) {
    const [showModal, setShowModal] = useState(false);
    const visits = plan.visits || [];

    return (
        <div className="rounded-2xl border border-slate-200/90 bg-slate-50/70 p-5 shadow-xs transition-all duration-200 hover:border-indigo-300/80 hover:bg-white hover:shadow-md dark:border-slate-800 dark:bg-slate-900/90 dark:hover:border-indigo-500/40">
            {/* Card Header: Code Badge, Title & Status */}
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0 flex-1 space-y-2">
                    <div className="flex flex-wrap items-center gap-2.5">
                        <span className="inline-flex items-center rounded-lg border border-indigo-200/70 bg-indigo-50 px-2.5 py-1 font-mono text-xs font-bold text-indigo-700 dark:border-indigo-500/30 dark:bg-indigo-500/15 dark:text-indigo-300">
                            {plan.survey_code}
                        </span>
                        <h4 className="text-sm sm:text-base font-bold text-slate-900 dark:text-white tracking-tight">
                            {plan.title}
                        </h4>
                    </div>

                    {/* Metadata Row: Planned Date & Site Address */}
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-slate-500 dark:text-slate-400">
                        <span className="inline-flex items-center gap-1.5">
                            <span>📅</span>
                            <span>Planned Date:</span>
                            <strong className="font-semibold text-slate-700 dark:text-slate-200">
                                {formatDate(plan.planned_date) || "Not scheduled"}
                            </strong>
                        </span>
                        <span className="inline-flex items-center gap-1.5">
                            <span>📍</span>
                            <span className="truncate max-w-md text-slate-600 dark:text-slate-300">
                                {plan.site_address || "No site address recorded"}
                            </span>
                        </span>
                    </div>
                </div>

                <div className="shrink-0 flex items-center gap-2">
                    <StatusBadge value={plan.status_key || plan.status} />
                </div>
            </div>

            {/* Assigned Survey Team Members */}
            {(plan.plan_members || []).length > 0 && (
                <div className="mt-4 flex flex-wrap items-center gap-2">
                    {plan.plan_members.map((member) => (
                        <span
                            key={member.id}
                            className="inline-flex items-center gap-1.5 rounded-full border border-slate-200/90 bg-white px-3 py-1 text-xs font-medium text-slate-700 shadow-2xs dark:border-slate-700/80 dark:bg-slate-800/90 dark:text-slate-200"
                        >
                            <span className="text-[11px]">👤</span>
                            <span className="font-semibold">{member.member?.name || "Unknown"}</span>
                            <span className="text-slate-300 dark:text-slate-600">•</span>
                            <span className="capitalize text-slate-500 dark:text-slate-400">{member.role_in_survey}</span>
                        </span>
                    ))}
                </div>
            )}

            {/* Bottom bar with Visits Summary & View More button */}
            <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200/70 pt-3.5 dark:border-slate-800">
                <div className="flex items-center gap-2.5 text-xs text-slate-500 dark:text-slate-400">
                    <span className="flex h-6 w-6 items-center justify-center rounded-full border border-indigo-200/60 bg-indigo-50 text-[11px] font-bold text-indigo-700 dark:border-indigo-500/30 dark:bg-indigo-500/15 dark:text-indigo-300">
                        {visits.length}
                    </span>
                    <span className="font-medium">
                        {visits.length === 0
                            ? "No field check-in visits yet"
                            : `${visits.length} field visit${visits.length > 1 ? "s" : ""} recorded`}
                    </span>
                </div>

                <button
                    type="button"
                    onClick={() => setShowModal(true)}
                    className="inline-flex items-center gap-2 rounded-xl border border-indigo-200 bg-indigo-50/90 px-4 py-2 text-xs font-semibold text-indigo-700 shadow-xs transition hover:border-indigo-300 hover:bg-indigo-100 dark:border-indigo-500/40 dark:bg-indigo-500/15 dark:text-indigo-200 dark:hover:border-indigo-400 dark:hover:bg-indigo-500/25"
                >
                    <span>👁️</span>
                    <span>View More Details</span>
                    {visits.length > 0 && (
                        <span className="inline-flex items-center justify-center rounded-full bg-indigo-600 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white dark:bg-indigo-500">
                            {visits.length}
                        </span>
                    )}
                    <span className="text-indigo-500 dark:text-indigo-300">↗</span>
                </button>
            </div>

            {/* Survey Plan Details Modal */}
            {showModal && (
                <Modal show={true} onClose={() => setShowModal(false)} maxWidth="5xl">
                    <div className="flex max-h-[88vh] flex-col overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-slate-900">
                        {/* Modal Header */}
                        <div className="flex items-center justify-between border-b border-slate-200/80 px-6 py-4 dark:border-slate-800">
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="rounded-md bg-indigo-50 px-2 py-0.5 font-mono text-xs font-bold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">
                                        {plan.survey_code}
                                    </span>
                                    <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                        {plan.title}
                                    </h3>
                                    <StatusBadge value={plan.status_key || plan.status} />
                                </div>
                                <p className="mt-0.5 text-xs text-slate-500">
                                    Planned Date: {formatDate(plan.planned_date) || "No planned date"}
                                    {plan.site_address ? ` • 📍 ${plan.site_address}` : ""}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowModal(false)}
                                className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            >
                                ✕
                            </button>
                        </div>

                        {/* Modal Body - Scrollable */}
                        <div className="flex-1 space-y-6 overflow-y-auto p-6">
                            {/* Plan Overview Card */}
                            <div className="rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 via-white to-indigo-50/20 p-5 shadow-2xs dark:border-slate-800 dark:from-slate-900 dark:to-slate-950">
                                <h4 className="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                    Survey Plan Overview
                                </h4>
                                <div className="mt-3 grid grid-cols-2 gap-4 text-xs sm:grid-cols-4">
                                    <div>
                                        <p className="text-slate-400">Survey Code</p>
                                        <p className="font-mono font-bold text-indigo-600 dark:text-indigo-400">{plan.survey_code}</p>
                                    </div>
                                    <div>
                                        <p className="text-slate-400">Planned Date</p>
                                        <p className="font-semibold text-slate-800 dark:text-slate-200">{formatDate(plan.planned_date) || "N/A"}</p>
                                    </div>
                                    <div>
                                        <p className="text-slate-400">Status</p>
                                        <p className="font-semibold text-slate-800 dark:text-slate-200">{plan.status_key || plan.status || "In Progress"}</p>
                                    </div>
                                    <div>
                                        <p className="text-slate-400">Field Visits</p>
                                        <p className="font-semibold text-slate-800 dark:text-slate-200">{visits.length} recorded</p>
                                    </div>
                                </div>
                                {plan.site_address && (
                                    <div className="mt-3 rounded-xl bg-slate-100/70 p-3 text-xs text-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
                                        <span className="font-semibold text-slate-900 dark:text-white">📍 Site Location: </span>
                                        {plan.site_address}
                                    </div>
                                )}
                                {(plan.plan_members || []).length > 0 && (
                                    <div className="mt-3 border-t border-slate-200/60 pt-3 dark:border-slate-800">
                                        <p className="text-[11px] font-medium text-slate-400 mb-1.5">Assigned Survey Team:</p>
                                        <div className="flex flex-wrap gap-1.5">
                                            {plan.plan_members.map((m) => (
                                                <span key={m.id} className="rounded-lg bg-white px-2.5 py-1 text-xs border border-slate-200 font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                                    👤 {m.member?.name || "Unknown"} ({m.role_in_survey})
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* Captured Field Visits */}
                            <div className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <h4 className="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 flex items-center gap-2">
                                        <span>📍</span>
                                        <span>Field Check-in Visits & Survey Reports ({visits.length})</span>
                                    </h4>
                                    {visits.length > 0 && (
                                        <span className="text-xs text-indigo-600 dark:text-indigo-400 font-semibold">
                                            Latest: Day {visits[0]?.day_number || 1}
                                        </span>
                                    )}
                                </div>

                                {visits.length > 0 ? (
                                    <div className="space-y-4">
                                        {visits.map((visit) => (
                                            <SurveyVisitCard key={visit.id} visit={visit} />
                                        ))}
                                    </div>
                                ) : (
                                    <div className="rounded-2xl border-2 border-dashed border-slate-200 p-8 text-center dark:border-slate-800">
                                        <span className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-2xl dark:bg-amber-950/40">
                                            📋
                                        </span>
                                        <h4 className="mt-3 text-sm font-bold text-slate-900 dark:text-white">
                                            No Field Visits Captured Yet
                                        </h4>
                                        <p className="mt-1 max-w-md mx-auto text-xs text-slate-500">
                                            Field surveyors have not submitted check-in visits for this survey plan yet.
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Modal Footer */}
                        <div className="flex items-center justify-end border-t border-slate-200/80 bg-slate-50/80 px-6 py-4 dark:border-slate-800 dark:bg-slate-950">
                            <button
                                type="button"
                                onClick={() => setShowModal(false)}
                                className="rounded-xl border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
}
