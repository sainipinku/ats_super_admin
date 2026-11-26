import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useForm, router } from "@inertiajs/react";
import { toast } from "react-hot-toast";
import gsap from "gsap";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import { mergeProjectExecutionTasks } from "@/lib/taskMerge";

const prefersReducedMotion = () =>
    typeof window !== "undefined" &&
    typeof window.matchMedia === "function" &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches;

const TITLE_WORDS = ["Dynamic", "Checklist", "Manager"];

const SLUG_SAFE = (s) =>
    String(s ?? "")
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/(^-|-$)/g, "");

const formatDateTime = (dateString) => {
    if (!dateString) return "-";
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return String(dateString);
    return date.toLocaleString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};

const normalizeItem = (raw, sourceKey, groupHeading) => {
    if (!raw) return null;
    const isDone = !!raw.is_completed || raw.status === 1 || raw.status === "completed";
    return {
        id: String(raw.id),
        source: sourceKey,
        groupHeading: groupHeading || raw.groupHeading || "",
        item_title: raw.item_title || raw.work_title || raw.title || "",
        assign_hours: raw.assign_hours != null && raw.assign_hours !== "" ? Number(raw.assign_hours) : null,
        notes: raw.notes || raw.note || raw.remarks || "",
        status: raw.status || (isDone ? "completed" : "pending"),
        image_url_1: raw.image_url_1 || null,
        image_url_2: raw.image_url_2 || null,
        is_completed: isDone,
        day_number: raw.day_number ?? 1,
        completed_at: raw.completed_at || null,
        completed_by: raw.completed_by
            ? {
                  name:
                      raw.completed_by.name ||
                      raw.completed_by.full_name ||
                      raw.completed_by.member?.name ||
                      "Unknown",
              }
            : null,
        sort_order: raw.sort_order ?? 0,
        scope: raw.scope || sourceKey,
        updated_at: raw.updated_at || raw.created_at || null,
        _raw: raw,
    };
};

const buildGroupsFromProject = (project) => {
    const entries = [];

    (project?.survey_plans || []).forEach((plan) => {
        const heading = `${plan.survey_code || "SP#" + plan.id} ${plan.title || ""}`.trim();
        (plan.checklists || []).forEach((it) => {
            const n = normalizeItem(it, "survey_plan", heading);
            if (n) entries.push({ groupKind: "Survey Plan", groupLabel: heading, item: n });
        });
        (plan.workChecklists || plan.checklist_works || []).forEach((it) => {
            const n = normalizeItem(
                { ...it, item_title: it.work_title, is_completed: it.status === 1, day_number: it.day_number ?? 1 },
                "survey_work",
                heading,
            );
            if (n) entries.push({ groupKind: "Survey Work", groupLabel: heading, item: n });
        });
    });

    mergeProjectExecutionTasks(project).forEach((task) => {
        const heading = `${task.task_code || "TASK#" + task.id} ${task.title || ""}`.trim();
        const isUnified = task._source === "unified";
        const groupKind = isUnified ? "Dynamic Task" : "Execution Task";
        const defaultScope = isUnified ? "unified_task" : "execution_task";
        (task.checklists || []).forEach((it) => {
            const itemScope = it.task_id ? "unified_task" : (it.execution_task_id ? "execution_task" : defaultScope);
            const n = normalizeItem(it, itemScope, heading);
            if (n) entries.push({ groupKind, groupLabel: heading, item: n });
        });
    });

    const byGroup = new Map();
    entries.forEach(({ groupKind, groupLabel, item }) => {
        const key = `${groupKind} · ${groupLabel}`;
        if (!byGroup.has(key)) {
            byGroup.set(key, {
                key,
                slug: SLUG_SAFE(key),
                kind: groupKind,
                heading: `${groupKind} · ${groupLabel}`,
                label: groupLabel,
                items: [],
                completed: 0,
                total: 0,
            });
        }
        const grp = byGroup.get(key);
        grp.items.push(item);
        grp.total += 1;
        if (item.is_completed) grp.completed += 1;
    });

    const groups = [...byGroup.values()].map((g) => ({
        ...g,
        items: [...g.items].sort((a, b) => {
            if ((a.day_number || 0) !== (b.day_number || 0))
                return (a.day_number || 0) - (b.day_number || 0);
            if ((a.sort_order || 0) !== (b.sort_order || 0))
                return (a.sort_order || 0) - (b.sort_order || 0);
            return Number(a.id) - Number(b.id);
        }),
    }));

    const byDay = new Map();
    entries.forEach(({ item }) => {
        const day = Number(item.day_number) > 0 ? Number(item.day_number) : 1;
        const key = `Survey Day ${day}`;
        if (!byDay.has(key)) {
            byDay.set(key, {
                key,
                slug: SLUG_SAFE(key),
                kind: "By Day",
                heading: key,
                label: key,
                items: [],
                completed: 0,
                total: 0,
            });
        }
        const grp = byDay.get(key);
        grp.items.push(item);
        grp.total += 1;
        if (item.is_completed) grp.completed += 1;
    });
    const dayGroups = [...byDay.values()].sort((a, b) => {
        const da = a.label.startsWith("Survey Day ") ? Number(a.label.slice(11)) : 999999;
        const db = b.label.startsWith("Survey Day ") ? Number(b.label.slice(11)) : 999999;
        return da - db;
    });

    const totals = entries.reduce(
        (acc, { item }) => {
            acc.total += 1;
            if (item.is_completed) acc.completed += 1;
            return acc;
        },
        { total: 0, completed: 0 },
    );

    return { groups, dayGroups, totals, itemCount: entries.length };
};

const DELTA_INTERVAL_MS = 15000;
const GROUPS_PER_PAGE = 10;

export default function DynamicChecklistManager({
    project,
    namespace = "super",
    variant = "project-show",
    canManage = true,
    enableDeltaPoll = false,
    initialActiveGroup = null,
    limitGroups = null,
    headerExtra = null,
}) {
    const routePrefix =
        namespace === "admin"
            ? "admin.construction.projects"
            : "super.construction.projects";

    const [groupingMode, setGroupingMode] = useState("group");
    const [activeGroup, setActiveGroup] = useState(initialActiveGroup || null);
    const [filterTaskKey, setFilterTaskKey] = useState("all");
    const [createTaskKey, setCreateTaskKey] = useState("");
    const [showAddForm, setShowAddForm] = useState(false);
    const [editing, setEditing] = useState(null);
    const [previewImage, setPreviewImage] = useState(null);
    const [visibleCount, setVisibleCount] = useState(GROUPS_PER_PAGE);
    const [isLoadingMore, setIsLoadingMore] = useState(false);

    // Form for creating new checklist item under selected task
    const createForm = useForm({
        scope: "execution_task",
        execution_task_id: "",
        survey_plan_id: "",
        unified_task_id: "",
        day_number: 1,
        item_title: "",
        assign_hours: "",
        notes: "",
        status: "pending",
        is_completed: false,
        image_1: null,
        image_2: null,
    });

    const editForm = useForm({
        day_number: 1,
        item_title: "",
        assign_hours: "",
        notes: "",
        status: "pending",
        is_completed: false,
        image_1: null,
        image_2: null,
        remove_image_1: false,
        remove_image_2: false,
    });

    const [img1Preview, setImg1Preview] = useState(null);
    const [img2Preview, setImg2Preview] = useState(null);
    const [editImg1Preview, setEditImg1Preview] = useState(null);
    const [editImg2Preview, setEditImg2Preview] = useState(null);

    // Extract all project tasks (deduplicated across execution_tasks & unified tasks, plus survey plans)
    const taskOptions = useMemo(() => {
        const list = [];

        mergeProjectExecutionTasks(project).forEach((t) => {
            const isUnified = t._source === "unified";
            const targetId = isUnified ? (t.unified_task_id || t.id) : (t.legacy_task_id || t.id);
            list.push({
                key: t._sourceKey || `${t._source}-${t.id}`,
                scope: isUnified ? "unified_task" : "execution_task",
                id: targetId,
                code: t.task_code || `TASK#${targetId}`,
                title: t.title,
                label: `[${isUnified ? "Dynamic Task" : "Execution Task"}] ${t.task_code || "TASK#" + targetId} - ${t.title}`,
                supervisor: t.supervisor?.name,
                status: t.status,
            });
        });

        (project?.survey_plans || []).forEach((sp) => {
            list.push({
                key: `survey-${sp.id}`,
                scope: "survey_plan",
                id: sp.id,
                code: sp.survey_code || `SP#${sp.id}`,
                title: sp.title,
                label: `[Survey Plan] ${sp.survey_code || "SP#" + sp.id} - ${sp.title}`,
                supervisor: null,
                status: sp.status,
            });
        });

        return list;
    }, [project]);

    // Preselect first task for the create form if not already selected
    useEffect(() => {
        if (taskOptions.length > 0 && (!createTaskKey || !taskOptions.some((t) => t.key === createTaskKey))) {
            const first = taskOptions[0];
            setCreateTaskKey(first.key);
            updateCreateFormScope(first);
        }
    }, [taskOptions, createTaskKey]);

    const updateCreateFormScope = (taskOpt) => {
        if (!taskOpt) return;
        if (taskOpt.scope === "execution_task") {
            createForm.setData((prev) => ({
                ...prev,
                scope: "execution_task",
                execution_task_id: taskOpt.id,
                survey_plan_id: "",
                unified_task_id: "",
            }));
        } else if (taskOpt.scope === "unified_task") {
            createForm.setData((prev) => ({
                ...prev,
                scope: "unified_task",
                unified_task_id: taskOpt.id,
                execution_task_id: "",
                survey_plan_id: "",
            }));
        } else if (taskOpt.scope === "survey_plan") {
            createForm.setData((prev) => ({
                ...prev,
                scope: "survey_plan",
                survey_plan_id: taskOpt.id,
                execution_task_id: "",
                unified_task_id: "",
            }));
        }
    };

    const handleCreateTaskChange = (e) => {
        const val = e.target.value;
        setCreateTaskKey(val);
        const opt = taskOptions.find((t) => t.key === val);
        if (opt) {
            updateCreateFormScope(opt);
        }
    };

    useEffect(() => {
        if (editing) {
            editForm.setData({
                day_number: editing.item?.day_number || 1,
                item_title: editing.item?.item_title || "",
                assign_hours: editing.item?.assign_hours ?? "",
                notes: editing.item?.notes || "",
                status: editing.item?.status || (editing.item?.is_completed ? "completed" : "pending"),
                is_completed: !!editing.item?.is_completed,
                image_1: null,
                image_2: null,
                remove_image_1: false,
                remove_image_2: false,
            });
            setEditImg1Preview(editing.item?.image_url_1 || null);
            setEditImg2Preview(editing.item?.image_url_2 || null);
        }
    }, [editing]);

    const built = useMemo(() => buildGroupsFromProject(project), [project]);
    const activeGroups = groupingMode === "day" ? built.dayGroups : built.groups;
    const filteredGroups = useMemo(() => {
        if (filterTaskKey === "all") return activeGroups;
        const opt = taskOptions.find((t) => t.key === filterTaskKey);
        if (!opt) return activeGroups;
        return activeGroups.filter((g) =>
            g.heading.toLowerCase().includes(opt.title.toLowerCase()) ||
            g.heading.toLowerCase().includes(opt.code.toLowerCase())
        );
    }, [activeGroups, filterTaskKey, taskOptions]);

    const baseGroups = useMemo(
        () => (typeof limitGroups === "number" ? filteredGroups.slice(0, limitGroups) : filteredGroups),
        [filteredGroups, limitGroups]
    );
    const slicedGroups = useMemo(
        () => (typeof limitGroups === "number" ? baseGroups : baseGroups.slice(0, visibleCount)),
        [baseGroups, limitGroups, visibleCount]
    );
    const remainingGroups = Math.max(0, baseGroups.length - slicedGroups.length);
    const hasMoreGroups = typeof limitGroups !== "number" && remainingGroups > 0;
    const completionPct = built.totals.total
        ? Math.round((built.totals.completed / built.totals.total) * 100)
        : 0;

    const handleImage1Change = (e, setForm, setPreview) => {
        const file = e.target.files?.[0];
        if (file) {
            setForm("image_1", file);
            setPreview(URL.createObjectURL(file));
        }
    };

    const handleImage2Change = (e, setForm, setPreview) => {
        const file = e.target.files?.[0];
        if (file) {
            setForm("image_2", file);
            setPreview(URL.createObjectURL(file));
        }
    };

    const removeImage1 = (setForm, setPreview, isEdit = false) => {
        setForm("image_1", null);
        if (isEdit) setForm("remove_image_1", true);
        setPreview(null);
    };

    const removeImage2 = (setForm, setPreview, isEdit = false) => {
        setForm("image_2", null);
        if (isEdit) setForm("remove_image_2", true);
        setPreview(null);
    };

    const submitCreate = (e) => {
        e.preventDefault();
        if (!createForm.data.item_title.trim()) {
            toast.error("Please enter a title for the checklist item.");
            return;
        }

        router.post(route(`${routePrefix}.checklists.store`, project.id), createForm.data, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: (page) => {
                if (page?.props?.flash?.error) {
                    return;
                }
                createForm.reset("item_title", "assign_hours", "notes", "image_1", "image_2");
                createForm.setData("status", "pending");
                setImg1Preview(null);
                setImg2Preview(null);
                setShowAddForm(false);
                toast.success("Checklist item created successfully.");
            },
            onError: (errors) => {
                const first = Object.values(errors)[0];
                if (first) toast.error(first);
                else toast.error("Failed to add checklist item.");
            },
        });
    };

    const submitEdit = (e) => {
        e.preventDefault();
        if (!editing?.item?.id) return;

        router.post(
            route(`${routePrefix}.checklists.update`, [project.id, editing.item.id]),
            {
                _method: "PUT",
                ...editForm.data,
            },
            {
                preserveScroll: true,
                forceFormData: true,
                onSuccess: (page) => {
                    if (page?.props?.flash?.error) {
                        return;
                    }
                    setEditing(null);
                    toast.success("Checklist item updated.");
                },
                onError: (errors) => {
                    const first = Object.values(errors)[0];
                    if (first) toast.error(first);
                    else toast.error("Failed to update checklist item.");
                },
            }
        );
    };

    const containerRef = useRef(null);
    const progressBarRef = useRef(null);
    const pctRef = useRef(null);
    const addFormRef = useRef(null);
    const loaderRef = useRef(null);
    const prevPctRef = useRef(0);
    const prevVisibleCountRef = useRef(0);
    const loadMoreTimerRef = useRef(null);
    const [isInView, setIsInView] = useState(false);

    // Reset pagination when grouping mode or task filter changes
    useEffect(() => {
        if (loadMoreTimerRef.current) {
            clearTimeout(loadMoreTimerRef.current);
            loadMoreTimerRef.current = null;
        }
        setIsLoadingMore(false);
        prevVisibleCountRef.current = 0;
        setVisibleCount(GROUPS_PER_PAGE);
    }, [groupingMode, filterTaskKey]);

    useEffect(() => {
        return () => {
            if (loadMoreTimerRef.current) {
                clearTimeout(loadMoreTimerRef.current);
                loadMoreTimerRef.current = null;
            }
        };
    }, []);

    const handleLoadMore = useCallback(() => {
        if (isLoadingMore || !hasMoreGroups) return;
        prevVisibleCountRef.current = slicedGroups.length;
        if (prefersReducedMotion()) {
            setVisibleCount((prev) => Math.min(prev + GROUPS_PER_PAGE, baseGroups.length));
            return;
        }
        setIsLoadingMore(true);
        loadMoreTimerRef.current = setTimeout(() => {
            setVisibleCount((prev) => Math.min(prev + GROUPS_PER_PAGE, baseGroups.length));
            setIsLoadingMore(false);
            loadMoreTimerRef.current = null;
        }, 460);
    }, [isLoadingMore, hasMoreGroups, slicedGroups.length, baseGroups.length]);

    const handleShowLess = useCallback(() => {
        if (loadMoreTimerRef.current) {
            clearTimeout(loadMoreTimerRef.current);
            loadMoreTimerRef.current = null;
        }
        setIsLoadingMore(false);
        prevVisibleCountRef.current = 0;
        setVisibleCount(GROUPS_PER_PAGE);
    }, []);

    // GSAP loading animation while fetching next 10 entries
    useEffect(() => {
        if (!isLoadingMore || !loaderRef.current || prefersReducedMotion()) return undefined;
        const ctx = gsap.context(() => {
            gsap.fromTo(
                ".dcm-load-more-loader",
                { opacity: 0, scale: 0.92 },
                { opacity: 1, scale: 1, duration: 0.22, ease: "back.out(2)" }
            );
            gsap.to(".dcm-loader-ring", {
                rotation: 360,
                duration: 0.72,
                repeat: -1,
                ease: "none",
                transformOrigin: "50% 50%",
            });
            gsap.to(".dcm-loader-dot", {
                y: -5,
                scale: 1.35,
                opacity: 1,
                duration: 0.34,
                stagger: 0.1,
                repeat: -1,
                yoyo: true,
                ease: "sine.inOut",
            });
        }, loaderRef);

        return () => ctx.revert();
    }, [isLoadingMore]);

    // Trigger screen zoom-out + inner cascade when section enters viewport
    useEffect(() => {
        const el = containerRef.current;
        if (!el || typeof IntersectionObserver === "undefined") {
            setIsInView(true);
            return undefined;
        }
        const observer = new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    setIsInView(true);
                    observer.disconnect();
                }
            },
            { threshold: 0.06 }
        );
        observer.observe(el);
        return () => observer.disconnect();
    }, []);

    // Deep-link auto-scroll and highlight when navigating with #dynamic-checklist-manager or ?focus=checklist
    useEffect(() => {
        if (typeof window === "undefined" || !containerRef.current) return undefined;

        const checkAndScroll = () => {
            const hash = window.location.hash || "";
            const searchParams = new URLSearchParams(window.location.search);
            const isChecklistFocus =
                searchParams.get("focus") === "checklist" ||
                hash === "#dynamic-checklist-manager" ||
                hash === "#checklists" ||
                hash === "#checklist" ||
                hash.startsWith("#chk-group");

            if (isChecklistFocus && containerRef.current) {
                const timer = setTimeout(() => {
                    containerRef.current?.scrollIntoView({
                        behavior: "smooth",
                        block: "start",
                    });

                    if (!prefersReducedMotion()) {
                        const ctx = gsap.context(() => {
                            gsap.fromTo(
                                containerRef.current,
                                {
                                    scale: 0.992,
                                    boxShadow: "0 0 0 0 rgba(99, 102, 241, 0)",
                                },
                                {
                                    scale: 1,
                                    boxShadow: "0 0 0 6px rgba(99, 102, 241, 0.4)",
                                    duration: 0.6,
                                    yoyo: true,
                                    repeat: 3,
                                    ease: "power2.out",
                                    clearProps: "scale,boxShadow",
                                }
                            );
                        }, containerRef);
                        return () => ctx.revert();
                    }
                }, 300);

                return () => clearTimeout(timer);
            }
            return undefined;
        };

        const cleanup = checkAndScroll();
        window.addEventListener("hashchange", checkAndScroll);
        return () => {
            if (typeof cleanup === "function") cleanup();
            window.removeEventListener("hashchange", checkAndScroll);
        };
    }, []);

    // Screen zoom-out entrance + kinetic title text effect
    useEffect(() => {
        if (!isInView || !containerRef.current || prefersReducedMotion()) return undefined;
        const ctx = gsap.context(() => {
            const tl = gsap.timeline();

            tl.fromTo(
                ".dcm-header-card",
                { opacity: 0, scale: 1.045, y: 16 },
                {
                    opacity: 1,
                    scale: 1,
                    y: 0,
                    duration: 0.56,
                    ease: "power3.out",
                    clearProps: "transform,opacity",
                }
            );

            tl.fromTo(
                ".dcm-title-word",
                { y: 22, opacity: 0, rotateX: -24, scale: 1.08 },
                {
                    y: 0,
                    opacity: 1,
                    rotateX: 0,
                    scale: 1,
                    duration: 0.55,
                    stagger: 0.085,
                    ease: "back.out(1.7)",
                    clearProps: "transform,opacity",
                },
                "-=0.38"
            );

            tl.fromTo(
                ".dcm-header-control",
                { opacity: 0, scale: 1.04, y: 8 },
                {
                    opacity: 1,
                    scale: 1,
                    y: 0,
                    duration: 0.36,
                    stagger: 0.05,
                    ease: "power2.out",
                    clearProps: "transform,opacity",
                },
                "-=0.35"
            );
        }, containerRef);

        return () => ctx.revert();
    }, [isInView]);

    // Smooth GSAP progress bar fill + animated percentage counter
    useEffect(() => {
        if (!isInView || !containerRef.current) return undefined;
        if (prefersReducedMotion()) {
            if (progressBarRef.current) {
                progressBarRef.current.style.width = `${completionPct}%`;
            }
            if (pctRef.current) {
                pctRef.current.textContent = `${completionPct}%`;
            }
            prevPctRef.current = completionPct;
            return undefined;
        }

        const ctx = gsap.context(() => {
            if (progressBarRef.current) {
                gsap.to(progressBarRef.current, {
                    width: `${completionPct}%`,
                    duration: 0.75,
                    ease: "power3.out",
                    overwrite: "auto",
                });
            }
            if (pctRef.current) {
                const counter = { value: prevPctRef.current };
                gsap.to(counter, {
                    value: completionPct,
                    duration: 0.7,
                    ease: "power2.out",
                    onUpdate: () => {
                        if (pctRef.current) {
                            pctRef.current.textContent = `${Math.round(counter.value)}%`;
                        }
                    },
                });
            }
            prevPctRef.current = completionPct;
        }, containerRef);

        return () => ctx.revert();
    }, [isInView, completionPct]);

    // Inner zoom-out cascade for group cards, titles, progress bars & checklist rows (incremental on Load More)
    useEffect(() => {
        if (!isInView || !containerRef.current || prefersReducedMotion()) {
            prevVisibleCountRef.current = slicedGroups.length;
            return undefined;
        }

        const startIdx = prevVisibleCountRef.current;
        const allCards = Array.from(containerRef.current.querySelectorAll(".dcm-group-card"));
        const targetCards = allCards.filter((card) => Number(card.getAttribute("data-dcm-index")) >= startIdx);

        if (!targetCards.length) {
            prevVisibleCountRef.current = slicedGroups.length;
            return undefined;
        }

        const ctx = gsap.context(() => {
            gsap.fromTo(
                targetCards,
                { opacity: 0, scale: 1.04, y: 16 },
                {
                    opacity: 1,
                    scale: 1,
                    y: 0,
                    duration: 0.44,
                    stagger: 0.055,
                    ease: "power3.out",
                    clearProps: "transform,opacity",
                }
            );

            const titles = targetCards.flatMap((c) => Array.from(c.querySelectorAll(".dcm-group-title")));
            if (titles.length) {
                gsap.fromTo(
                    titles,
                    { opacity: 0, x: -8 },
                    {
                        opacity: 1,
                        x: 0,
                        duration: 0.36,
                        stagger: 0.045,
                        delay: 0.06,
                        ease: "power2.out",
                        clearProps: "transform,opacity",
                    }
                );
            }

            const rows = targetCards.flatMap((c) => Array.from(c.querySelectorAll(".dcm-checklist-row")));
            if (rows.length) {
                gsap.fromTo(
                    rows,
                    { opacity: 0, scale: 1.03, y: 10 },
                    {
                        opacity: 1,
                        scale: 1,
                        y: 0,
                        duration: 0.34,
                        stagger: 0.03,
                        delay: 0.09,
                        ease: "power2.out",
                        clearProps: "transform,opacity",
                    }
                );
            }

            const groupBars = targetCards.flatMap((c) => Array.from(c.querySelectorAll(".dcm-group-progress-fill")));
            if (groupBars.length) {
                gsap.fromTo(
                    groupBars,
                    { scaleX: 0, transformOrigin: "left center" },
                    {
                        scaleX: 1,
                        duration: 0.55,
                        stagger: 0.045,
                        delay: 0.1,
                        ease: "power3.out",
                        clearProps: "transform",
                    }
                );
            }
        }, containerRef);

        prevVisibleCountRef.current = slicedGroups.length;

        return () => ctx.revert();
    }, [isInView, groupingMode, filterTaskKey, slicedGroups.length]);

    // Collapsible Add Form screen zoom-out reveal
    useEffect(() => {
        if (!showAddForm || !addFormRef.current || prefersReducedMotion()) return undefined;
        const ctx = gsap.context(() => {
            gsap.fromTo(
                addFormRef.current,
                { opacity: 0, scale: 1.035, y: -10 },
                {
                    opacity: 1,
                    scale: 1,
                    y: 0,
                    duration: 0.42,
                    ease: "power3.out",
                    clearProps: "transform,opacity",
                }
            );
        }, addFormRef);

        return () => ctx.revert();
    }, [showAddForm]);

    // Springy GSAP hover popping handlers
    const handlePopEnter = useCallback((e, { scale = 1.016, y = -3 } = {}) => {
        if (prefersReducedMotion()) return;
        gsap.to(e.currentTarget, {
            scale,
            y,
            duration: 0.24,
            ease: "back.out(2.2)",
            overwrite: "auto",
        });
    }, []);

    const handlePopLeave = useCallback((e) => {
        if (prefersReducedMotion()) return;
        gsap.to(e.currentTarget, {
            scale: 1,
            y: 0,
            duration: 0.24,
            ease: "power2.out",
            overwrite: "auto",
        });
    }, []);

    const animateCheckboxPop = useCallback((el) => {
        if (!el || prefersReducedMotion()) return;
        gsap.timeline()
            .to(el, { scale: 0.72, duration: 0.1, ease: "power2.in" })
            .to(el, { scale: 1.24, duration: 0.22, ease: "back.out(3)" })
            .to(el, { scale: 1, duration: 0.16, ease: "power2.out", clearProps: "transform" });
    }, []);

    const runToggle = (group, item, checkboxEl = null) => {
        if (!canManage) return;
        animateCheckboxPop(checkboxEl);
        router.patch(
            route(`${routePrefix}.checklists.toggle`, [project.id, item.id]),
            {},
            {
                preserveScroll: true,
                onError: (errors) => {
                    const first = Object.values(errors)[0];
                    if (first) toast.error(first);
                    else toast.error("Failed to toggle checklist item.");
                },
            }
        );
    };

    const runDelete = (group, item) => {
        if (!canManage) return;
        if (!confirm("Are you sure you want to delete this checklist item?")) return;
        router.delete(route(`${routePrefix}.checklists.destroy`, [project.id, item.id]), {
            preserveScroll: true,
            onSuccess: (page) => {
                if (page?.props?.flash?.error) {
                    return;
                }
                if (editing?.item?.id === item.id) setEditing(null);
                toast.success("Checklist item deleted.");
            },
            onError: (errors) => {
                const first = Object.values(errors)[0];
                if (first) toast.error(first);
                else toast.error("Failed to delete checklist item.");
            },
        });
    };

    const selectedCreateTask = useMemo(
        () => taskOptions.find((t) => t.key === createTaskKey),
        [taskOptions, createTaskKey]
    );

    return (
        <div
            id="dynamic-checklist-manager"
            ref={containerRef}
            className="dcm-stage w-full scroll-mt-28 space-y-3.5"
        >
            <span id="checklists" className="sr-only pointer-events-none" />
            <span id="chk-group-default" className="sr-only pointer-events-none" />
            {/* Header & Metrics */}
            <div className="dcm-header-card relative overflow-hidden rounded-2xl border border-slate-200/90 bg-white p-3.5 shadow-sm transition-shadow hover:shadow-md dark:border-slate-800/90 dark:bg-slate-900/95">
                <div className="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-indigo-500 via-violet-500 to-emerald-500 opacity-85" />

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2.5">
                            <span
                                onMouseEnter={(e) => handlePopEnter(e, { scale: 1.08, y: -2 })}
                                onMouseLeave={handlePopLeave}
                                className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500/15 to-violet-500/15 text-base text-indigo-700 ring-1 ring-indigo-500/20 dark:from-indigo-500/20 dark:to-violet-500/20 dark:text-indigo-300 dark:ring-indigo-400/25"
                            >
                                📋
                            </span>
                            <div className="min-w-0">
                                <h2
                                    aria-label="Dynamic Checklist Manager"
                                    className="flex flex-wrap items-center gap-x-1.5 text-base font-extrabold tracking-tight text-slate-900 dark:text-white"
                                >
                                    {TITLE_WORDS.map((word) => (
                                        <span key={word} className="inline-flex overflow-hidden py-0.5">
                                            <span className="dcm-title-word inline-block bg-gradient-to-r from-slate-900 via-indigo-900 to-slate-900 bg-clip-text text-transparent dark:from-white dark:via-indigo-200 dark:to-white">
                                                {word}
                                            </span>
                                        </span>
                                    ))}
                                </h2>
                                <div className="mt-0.5 flex flex-wrap items-center gap-2 text-[11px] text-slate-500 dark:text-slate-400">
                                    <span>
                                        Total <strong className="font-semibold text-slate-700 dark:text-slate-200">{built.totals.completed}</strong> of{" "}
                                        <strong className="font-semibold text-slate-700 dark:text-slate-200">{built.totals.total}</strong> items completed
                                    </span>
                                    <span className="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700 ring-1 ring-indigo-200/70 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-500/30">
                                        <span ref={pctRef} className="dcm-pct-value">
                                            {completionPct}%
                                        </span>
                                    </span>
                                    {baseGroups.length > 0 && (
                                        <span className="text-[10px] font-medium text-slate-400 dark:text-slate-500">
                                            • Showing {slicedGroups.length} of {baseGroups.length}
                                        </span>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="mt-2.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-100 ring-1 ring-inset ring-slate-200/70 dark:bg-slate-800/90 dark:ring-slate-700/60">
                            <div
                                ref={progressBarRef}
                                className="dcm-progress-fill h-full rounded-full bg-gradient-to-r from-indigo-500 via-violet-500 to-emerald-500"
                                style={{ width: `${completionPct}%` }}
                            />
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {/* Filter by Task */}
                        {taskOptions.length > 0 && (
                            <div className="dcm-header-control flex items-center gap-1.5">
                                <label className="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                    Filter
                                </label>
                                <select
                                    value={filterTaskKey}
                                    onChange={(e) => setFilterTaskKey(e.target.value)}
                                    className="rounded-xl border border-slate-200 bg-slate-50/80 px-2.5 py-1.5 text-xs font-semibold text-slate-800 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                >
                                    <option value="all">All Project Tasks ({taskOptions.length})</option>
                                    {taskOptions.map((opt) => (
                                        <option key={opt.key} value={opt.key}>
                                            {opt.code} - {opt.title}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}

                        <div className="dcm-header-control inline-flex rounded-xl border border-slate-200 bg-slate-50 p-0.5 dark:border-slate-700 dark:bg-slate-950">
                            <button
                                type="button"
                                onClick={() => setGroupingMode("group")}
                                className={`rounded-lg px-2.5 py-1 text-[11px] font-semibold transition ${
                                    groupingMode === "group"
                                        ? "bg-indigo-600 text-white shadow-sm"
                                        : "text-slate-600 hover:bg-slate-200/80 dark:text-slate-300 dark:hover:bg-slate-800"
                                }`}
                            >
                                Group by Task
                            </button>
                            <button
                                type="button"
                                onClick={() => setGroupingMode("day")}
                                className={`rounded-lg px-2.5 py-1 text-[11px] font-semibold transition ${
                                    groupingMode === "day"
                                        ? "bg-indigo-600 text-white shadow-sm"
                                        : "text-slate-600 hover:bg-slate-200/80 dark:text-slate-300 dark:hover:bg-slate-800"
                                }`}
                            >
                                Group by Day
                            </button>
                        </div>

                        {variant === "project-show" && canManage && (
                            <button
                                type="button"
                                onClick={() => setShowAddForm((prev) => !prev)}
                                onMouseEnter={(e) => handlePopEnter(e, { scale: 1.04, y: -1.5 })}
                                onMouseLeave={handlePopLeave}
                                className={`dcm-header-control inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-[11px] font-semibold shadow-sm transition-colors ${
                                    showAddForm
                                        ? "bg-slate-200 text-slate-800 hover:bg-slate-300 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                                        : "bg-indigo-600 text-white hover:bg-indigo-500 shadow-indigo-500/20"
                                }`}
                            >
                                {showAddForm ? (
                                    <>
                                        <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        Close Form
                                    </>
                                ) : (
                                    <>
                                        <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                        </svg>
                                        Add Checklist Item
                                    </>
                                )}
                            </button>
                        )}

                        {headerExtra || null}
                    </div>
                </div>
            </div>

            {/* Task Selection & Creation Flow (Collapsible) */}
            {variant === "project-show" && canManage && showAddForm ? (
                <div
                    ref={addFormRef}
                    className="dcm-add-form-panel rounded-2xl border border-indigo-200/80 bg-gradient-to-br from-indigo-50/60 via-white to-violet-50/50 p-5 shadow-sm dark:border-indigo-900/50 dark:from-slate-900 dark:via-slate-900 dark:to-indigo-950/30"
                >
                    <div className="flex flex-wrap items-center justify-between gap-4 border-b border-indigo-100/80 pb-4 dark:border-slate-800">
                        <div>
                            <h3 className="text-sm font-bold text-indigo-950 dark:text-indigo-200">
                                Attach Checklist Item to Task or Survey Plan
                            </h3>
                            <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                Select any assigned task below to attach dynamic checklists with title, hours, status, notes & images.
                            </p>
                        </div>

                        {/* Task Select Dropdown */}
                        <div className="min-w-[280px]">
                            <label className="mb-1 block text-[11px] font-semibold text-slate-600 dark:text-slate-300">
                                Target Task / Plan:
                            </label>
                            <select
                                value={createTaskKey}
                                onChange={handleCreateTaskChange}
                                className="w-full rounded-xl border border-indigo-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-800 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-indigo-800 dark:bg-slate-900 dark:text-white"
                            >
                                {taskOptions.map((opt) => (
                                    <option key={opt.key} value={opt.key}>
                                        {opt.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>

                    {/* Active Selected Task Badge Card */}
                    {selectedCreateTask ? (
                        <div className="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-indigo-200/70 bg-indigo-50/70 px-4 py-2.5 text-xs dark:border-indigo-900/40 dark:bg-indigo-950/40">
                            <div className="flex items-center gap-2">
                                <span className="font-bold text-indigo-900 dark:text-indigo-200">Target Task:</span>
                                <span className="rounded-md bg-indigo-600 px-2 py-0.5 font-mono text-[11px] text-white">
                                    {selectedCreateTask.code}
                                </span>
                                <span className="font-semibold text-slate-800 dark:text-slate-200">{selectedCreateTask.title}</span>
                            </div>
                            {selectedCreateTask.status ? (
                                <StatusBadge value={selectedCreateTask.status} />
                            ) : null}
                        </div>
                    ) : null}

                    {/* Form to Add Checklist Item */}
                    <form onSubmit={submitCreate} className="mt-4 space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="lg:col-span-2">
                                <label className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    Checklist Title <span className="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={createForm.data.item_title}
                                    onChange={(e) => createForm.setData("item_title", e.target.value)}
                                    placeholder="e.g. Conduct elevation survey and record boundary coordinates"
                                    className="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                />
                            </div>

                            <div>
                                <label className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    Assign Hours <span className="font-normal text-slate-400">(Optional)</span>
                                </label>
                                <input
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    value={createForm.data.assign_hours}
                                    onChange={(e) => createForm.setData("assign_hours", e.target.value)}
                                    placeholder="e.g. 2.5"
                                    className="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                />
                            </div>

                            <div>
                                <label className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    Status
                                </label>
                                <select
                                    value={createForm.data.status}
                                    onChange={(e) => {
                                        const val = e.target.value;
                                        createForm.setData("status", val);
                                        createForm.setData("is_completed", val === "completed");
                                    }}
                                    className="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                >
                                    <option value="pending">Pending</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    Survey Day #
                                </label>
                                <input
                                    type="number"
                                    min="1"
                                    value={createForm.data.day_number}
                                    onChange={(e) => createForm.setData("day_number", e.target.value)}
                                    className="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                />
                            </div>

                            <div>
                                <label className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    Notes <span className="font-normal text-slate-400">(Optional)</span>
                                </label>
                                <input
                                    type="text"
                                    value={createForm.data.notes}
                                    onChange={(e) => createForm.setData("notes", e.target.value)}
                                    placeholder="Add optional notes or field remarks..."
                                    className="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                />
                            </div>
                        </div>

                        {/* Image Attachments with Remove Image Button */}
                        <div className="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                            <span className="mb-2.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Photo Attachments (Max 2 images)
                            </span>
                            <div className="grid gap-4 sm:grid-cols-2">
                                {/* Image 1 */}
                                <div className="space-y-2">
                                    <label className="block text-[11px] font-medium text-slate-500">Image 1</label>
                                    {img1Preview ? (
                                        <div className="relative inline-block overflow-hidden rounded-xl border border-slate-200 shadow-sm">
                                            <img src={img1Preview} alt="Preview 1" className="h-20 w-32 object-cover" />
                                            <button
                                                type="button"
                                                onClick={() => removeImage1(createForm.setData, setImg1Preview)}
                                                className="absolute right-1 top-1 rounded-full bg-rose-600 px-1.5 py-0.5 text-[10px] text-white shadow hover:bg-rose-700"
                                                title="Remove Image 1"
                                            >
                                                ✕ Remove
                                            </button>
                                        </div>
                                    ) : (
                                        <input
                                            type="file"
                                            accept="image/*"
                                            onChange={(e) => handleImage1Change(e, createForm.setData, setImg1Preview)}
                                            className="block w-full text-xs text-slate-500 file:mr-3 file:rounded-xl file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
                                        />
                                    )}
                                </div>

                                {/* Image 2 */}
                                <div className="space-y-2">
                                    <label className="block text-[11px] font-medium text-slate-500">Image 2</label>
                                    {img2Preview ? (
                                        <div className="relative inline-block overflow-hidden rounded-xl border border-slate-200 shadow-sm">
                                            <img src={img2Preview} alt="Preview 2" className="h-20 w-32 object-cover" />
                                            <button
                                                type="button"
                                                onClick={() => removeImage2(createForm.setData, setImg2Preview)}
                                                className="absolute right-1 top-1 rounded-full bg-rose-600 px-1.5 py-0.5 text-[10px] text-white shadow hover:bg-rose-700"
                                                title="Remove Image 2"
                                            >
                                                ✕ Remove
                                            </button>
                                        </div>
                                    ) : (
                                        <input
                                            type="file"
                                            accept="image/*"
                                            onChange={(e) => handleImage2Change(e, createForm.setData, setImg2Preview)}
                                            className="block w-full text-xs text-slate-500 file:mr-3 file:rounded-xl file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
                                        />
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="flex justify-end gap-2.5 pt-1">
                            <button
                                type="button"
                                onClick={() => setShowAddForm(false)}
                                className="rounded-xl border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={createForm.processing}
                                onMouseEnter={(e) => handlePopEnter(e, { scale: 1.04, y: -1.5 })}
                                onMouseLeave={handlePopLeave}
                                className="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2 text-xs font-semibold text-white shadow-md hover:bg-indigo-500 disabled:opacity-60"
                            >
                                {createForm.processing ? "Saving Checklist..." : "➕ Add Checklist Item"}
                            </button>
                        </div>
                    </form>
                </div>
            ) : null}

            {/* Checklist Items Display List (Compact Mini-Space, 2 per row, 10 per batch) */}
            {!slicedGroups.length ? (
                <EmptyState
                    title="No checklist items found."
                    description="Select a task above and create checklist items to track execution details."
                />
            ) : (
                <div className="space-y-3">
                    <div className="grid gap-3 md:grid-cols-2">
                        {slicedGroups.map((group, groupIndex) => {
                            const pct = group.total ? Math.round((group.completed / group.total) * 100) : 0;
                            const anchorGroup = `chk-group-${group.slug || SLUG_SAFE(group.key)}`;
                            return (
                                <section
                                    key={group.key}
                                    id={anchorGroup}
                                    data-dcm-index={groupIndex}
                                    onMouseEnter={(e) => handlePopEnter(e, { scale: 1.014, y: -3 })}
                                    onMouseLeave={handlePopLeave}
                                    className="dcm-group-card scroll-mt-24 rounded-xl border border-slate-200/90 bg-white p-3 shadow-sm transition-colors hover:border-indigo-300/80 hover:shadow-md hover:shadow-indigo-500/5 dark:border-slate-800 dark:bg-slate-900/95 dark:hover:border-indigo-500/40"
                                >
                                    <div className="flex items-start justify-between gap-2 border-b border-slate-100 pb-2 dark:border-slate-800">
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-1.5">
                                                <span className="inline-flex shrink-0 items-center rounded bg-slate-100 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                    {group.kind}
                                                </span>
                                                <span className="text-[10px] font-medium text-slate-500 dark:text-slate-400">
                                                    {group.completed}/{group.total} •{" "}
                                                    <span className="font-semibold text-emerald-600 dark:text-emerald-400">{pct}%</span>
                                                </span>
                                            </div>
                                            <h3 className="dcm-group-title mt-1 truncate text-xs font-bold text-slate-900 dark:text-white">
                                                {group.heading}
                                            </h3>
                                        </div>
                                        <div className="shrink-0 scale-90 origin-top-right">
                                            <StatusBadge
                                                value={
                                                    group.total === 0
                                                        ? "planned"
                                                        : group.completed === group.total
                                                        ? "completed"
                                                        : "in_progress"
                                                }
                                            />
                                        </div>
                                    </div>

                                    <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                        <div
                                            className="dcm-group-progress-fill h-full rounded-full bg-gradient-to-r from-indigo-500 via-violet-500 to-emerald-500"
                                            style={{ width: `${pct}%` }}
                                        />
                                    </div>

                                    <ul className="mt-2.5 space-y-1.5">
                                        {group.items.map((item) => (
                                            <li
                                                key={`${item.source}-${item.id}`}
                                                onMouseEnter={(e) => handlePopEnter(e, { scale: 1.012, y: -1.5 })}
                                                onMouseLeave={handlePopLeave}
                                                className="dcm-checklist-row rounded-lg border border-slate-200/80 bg-slate-50/70 px-2.5 py-2 transition-colors hover:border-indigo-300/80 hover:bg-white hover:shadow-sm dark:border-slate-800/90 dark:bg-slate-950/70 dark:hover:border-indigo-500/40 dark:hover:bg-slate-900/90"
                                            >
                                                <div className="flex items-start justify-between gap-2">
                                                    <label className="flex min-w-0 flex-1 cursor-pointer items-start gap-2">
                                                        <input
                                                            type="checkbox"
                                                            disabled={!canManage}
                                                            checked={!!item.is_completed}
                                                            onChange={(e) => runToggle(group, item, e.currentTarget)}
                                                            className="mt-0.5 h-3.5 w-3.5 shrink-0 cursor-pointer rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900"
                                                        />
                                                        <div className="min-w-0 flex-1">
                                                            <div className="flex flex-wrap items-center gap-x-1.5 gap-y-1">
                                                                <p
                                                                    className={`break-words text-[11px] font-semibold leading-snug ${
                                                                        item.is_completed
                                                                            ? "text-slate-400 line-through dark:text-slate-500"
                                                                            : "text-slate-900 dark:text-white"
                                                                    }`}
                                                                >
                                                                    {item.item_title || "(Untitled Item)"}
                                                                </p>

                                                                {item.assign_hours ? (
                                                                    <span className="inline-flex items-center gap-0.5 rounded bg-amber-50 px-1.5 py-0.5 text-[9px] font-semibold text-amber-700 ring-1 ring-amber-200/70 dark:bg-amber-900/25 dark:text-amber-300 dark:ring-amber-700/30">
                                                                        ⏱️ {item.assign_hours}h
                                                                    </span>
                                                                ) : null}

                                                                <span
                                                                    className={`inline-flex rounded px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide ${
                                                                        item.is_completed
                                                                            ? "bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200/70 dark:bg-emerald-900/25 dark:text-emerald-300 dark:ring-emerald-700/30"
                                                                            : item.status === "in_progress"
                                                                            ? "bg-blue-50 text-blue-700 ring-1 ring-blue-200/70 dark:bg-blue-900/25 dark:text-blue-300 dark:ring-blue-700/30"
                                                                            : "bg-slate-100 text-slate-600 ring-1 ring-slate-200/80 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700"
                                                                    }`}
                                                                >
                                                                    {item.status || (item.is_completed ? "Completed" : "Pending")}
                                                                </span>

                                                                {item.day_number ? (
                                                                    <span className="rounded bg-purple-50 px-1.5 py-0.5 text-[9px] font-semibold text-purple-700 ring-1 ring-purple-200/70 dark:bg-purple-900/25 dark:text-purple-300 dark:ring-purple-700/30">
                                                                        Day {item.day_number}
                                                                    </span>
                                                                ) : null}
                                                            </div>

                                                            {/* Notes snippet */}
                                                            {item.notes ? (
                                                                <p className="mt-1.5 rounded border border-slate-100 bg-white px-2 py-1 text-[10px] italic text-slate-600 dark:border-slate-800 dark:bg-slate-900/90 dark:text-slate-300">
                                                                    💬 {item.notes}
                                                                </p>
                                                            ) : null}

                                                            {/* Images preview thumbnails */}
                                                            {item.image_url_1 || item.image_url_2 ? (
                                                                <div className="mt-1.5 flex items-center gap-1.5">
                                                                    {item.image_url_1 ? (
                                                                        <img
                                                                            src={item.image_url_1}
                                                                            alt="Img 1"
                                                                            onClick={() => setPreviewImage(item.image_url_1)}
                                                                            onMouseEnter={(e) => handlePopEnter(e, { scale: 1.08, y: -2 })}
                                                                            onMouseLeave={handlePopLeave}
                                                                            className="h-9 w-12 cursor-pointer rounded border border-slate-200 object-cover shadow-sm dark:border-slate-700"
                                                                        />
                                                                    ) : null}
                                                                    {item.image_url_2 ? (
                                                                        <img
                                                                            src={item.image_url_2}
                                                                            alt="Img 2"
                                                                            onClick={() => setPreviewImage(item.image_url_2)}
                                                                            onMouseEnter={(e) => handlePopEnter(e, { scale: 1.08, y: -2 })}
                                                                            onMouseLeave={handlePopLeave}
                                                                            className="h-9 w-12 cursor-pointer rounded border border-slate-200 object-cover shadow-sm dark:border-slate-700"
                                                                        />
                                                                    ) : null}
                                                                </div>
                                                            ) : null}
                                                        </div>
                                                    </label>

                                                    {/* Action Buttons */}
                                                    <div className="flex shrink-0 items-center gap-1">
                                                        <button
                                                            type="button"
                                                            disabled={!canManage}
                                                            onClick={() => setEditing({ group, item })}
                                                            onMouseEnter={(e) => handlePopEnter(e, { scale: 1.07, y: -1 })}
                                                            onMouseLeave={handlePopLeave}
                                                            className="rounded-md bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-700 transition-colors hover:bg-indigo-100 disabled:opacity-50 dark:bg-indigo-900/30 dark:text-indigo-300 dark:hover:bg-indigo-900/50"
                                                        >
                                                            Edit
                                                        </button>
                                                        <button
                                                            type="button"
                                                            disabled={!canManage}
                                                            onClick={() => runDelete(group, item)}
                                                            onMouseEnter={(e) => handlePopEnter(e, { scale: 1.07, y: -1 })}
                                                            onMouseLeave={handlePopLeave}
                                                            className="rounded-md bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700 transition-colors hover:bg-rose-100 disabled:opacity-50 dark:bg-rose-900/30 dark:text-rose-300 dark:hover:bg-rose-900/50"
                                                        >
                                                            Delete
                                                        </button>
                                                    </div>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            );
                        })}
                    </div>

                    {/* Load More / Show Less Footer with GSAP Loading Animation */}
                    {(hasMoreGroups || (typeof limitGroups !== "number" && slicedGroups.length > GROUPS_PER_PAGE)) && (
                        <div
                            ref={loaderRef}
                            className="flex flex-wrap items-center justify-center gap-3 rounded-xl border border-slate-200/80 bg-white/90 px-4 py-2.5 shadow-sm dark:border-slate-800 dark:bg-slate-900/80"
                        >
                            <span className="text-[11px] font-medium text-slate-500 dark:text-slate-400">
                                Showing <strong className="font-bold text-slate-800 dark:text-slate-200">{slicedGroups.length}</strong> of{" "}
                                <strong className="font-bold text-slate-800 dark:text-slate-200">{baseGroups.length}</strong> entries
                            </span>

                            {hasMoreGroups && (
                                <button
                                    type="button"
                                    onClick={handleLoadMore}
                                    disabled={isLoadingMore}
                                    onMouseEnter={(e) => handlePopEnter(e, { scale: 1.04, y: -1.5 })}
                                    onMouseLeave={handlePopLeave}
                                    className="dcm-load-more-btn inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm shadow-indigo-500/20 transition hover:from-indigo-500 hover:to-violet-500 disabled:cursor-wait disabled:opacity-90"
                                >
                                    {isLoadingMore ? (
                                        <span className="dcm-load-more-loader inline-flex items-center gap-2">
                                            <svg
                                                className="dcm-loader-ring h-3.5 w-3.5 text-white"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                            >
                                                <circle
                                                    cx="12"
                                                    cy="12"
                                                    r="9"
                                                    stroke="currentColor"
                                                    strokeOpacity="0.3"
                                                    strokeWidth="3"
                                                />
                                                <path
                                                    d="M12 3a9 9 0 0 1 9 9"
                                                    stroke="currentColor"
                                                    strokeWidth="3"
                                                    strokeLinecap="round"
                                                />
                                            </svg>
                                            <span>Loading next {Math.min(GROUPS_PER_PAGE, remainingGroups)}</span>
                                            <span className="inline-flex items-center gap-1">
                                                <span className="dcm-loader-dot h-1.5 w-1.5 rounded-full bg-white/80" />
                                                <span className="dcm-loader-dot h-1.5 w-1.5 rounded-full bg-white/80" />
                                                <span className="dcm-loader-dot h-1.5 w-1.5 rounded-full bg-white/80" />
                                            </span>
                                        </span>
                                    ) : (
                                        <>
                                            <span>
                                                Load More (+{Math.min(GROUPS_PER_PAGE, remainingGroups)} of {remainingGroups} left)
                                            </span>
                                            <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </>
                                    )}
                                </button>
                            )}

                            {typeof limitGroups !== "number" && slicedGroups.length > GROUPS_PER_PAGE && !isLoadingMore && (
                                <button
                                    type="button"
                                    onClick={handleShowLess}
                                    onMouseEnter={(e) => handlePopEnter(e, { scale: 1.04, y: -1 })}
                                    onMouseLeave={handlePopLeave}
                                    className="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                                >
                                    Show Less
                                </button>
                            )}
                        </div>
                    )}
                </div>
            )}

            {/* Edit Modal */}
            {editing ? (
                <Modal open={true} onClose={() => setEditing(null)}>
                    <form onSubmit={submitEdit} className="grid gap-4 p-6 pt-2">
                        <div>
                            <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                Edit Checklist Item
                            </h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                {editing.group?.heading}
                            </p>
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Item Title
                            </label>
                            <input
                                type="text"
                                required
                                value={editForm.data.item_title}
                                onChange={(e) => editForm.setData("item_title", e.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            />
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    Assign Hours
                                </label>
                                <input
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    value={editForm.data.assign_hours}
                                    onChange={(e) => editForm.setData("assign_hours", e.target.value)}
                                    className="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                />
                            </div>

                            <div>
                                <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    Status
                                </label>
                                <select
                                    value={editForm.data.status}
                                    onChange={(e) => {
                                        const val = e.target.value;
                                        editForm.setData("status", val);
                                        editForm.setData("is_completed", val === "completed");
                                    }}
                                    className="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                >
                                    <option value="pending">Pending</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Notes
                            </label>
                            <textarea
                                rows={2}
                                value={editForm.data.notes}
                                onChange={(e) => editForm.setData("notes", e.target.value)}
                                className="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            />
                        </div>

                        {/* Image Attachments with Remove Option */}
                        <div className="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950">
                            <span className="mb-2 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Update Photos & Attachments
                            </span>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-1">
                                    <label className="block text-[11px] font-medium text-slate-500">Image 1</label>
                                    {editImg1Preview ? (
                                        <div className="relative inline-block overflow-hidden rounded-xl border border-slate-200">
                                            <img src={editImg1Preview} alt="Edit Img 1" className="h-16 w-24 object-cover" />
                                            <button
                                                type="button"
                                                onClick={() => removeImage1(editForm.setData, setEditImg1Preview, true)}
                                                className="absolute right-1 top-1 rounded bg-rose-600 px-1.5 py-0.5 text-[9px] text-white shadow"
                                            >
                                                ✕ Remove
                                            </button>
                                        </div>
                                    ) : (
                                        <input
                                            type="file"
                                            accept="image/*"
                                            onChange={(e) => handleImage1Change(e, editForm.setData, setEditImg1Preview)}
                                            className="block w-full text-xs text-slate-500"
                                        />
                                    )}
                                </div>

                                <div className="space-y-1">
                                    <label className="block text-[11px] font-medium text-slate-500">Image 2</label>
                                    {editImg2Preview ? (
                                        <div className="relative inline-block overflow-hidden rounded-xl border border-slate-200">
                                            <img src={editImg2Preview} alt="Edit Img 2" className="h-16 w-24 object-cover" />
                                            <button
                                                type="button"
                                                onClick={() => removeImage2(editForm.setData, setEditImg2Preview, true)}
                                                className="absolute right-1 top-1 rounded bg-rose-600 px-1.5 py-0.5 text-[9px] text-white shadow"
                                            >
                                                ✕ Remove
                                            </button>
                                        </div>
                                    ) : (
                                        <input
                                            type="file"
                                            accept="image/*"
                                            onChange={(e) => handleImage2Change(e, editForm.setData, setEditImg2Preview)}
                                            className="block w-full text-xs text-slate-500"
                                        />
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="flex justify-end gap-3 pt-2">
                            <button
                                type="button"
                                onClick={() => setEditing(null)}
                                className="rounded-xl border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={editForm.processing}
                                className="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-500 disabled:opacity-60"
                            >
                                {editForm.processing ? "Saving..." : "Save Changes"}
                            </button>
                        </div>
                    </form>
                </Modal>
            ) : null}

            {/* Lightbox Modal for Image Zoom Preview */}
            {previewImage ? (
                <Modal open={true} onClose={() => setPreviewImage(null)}>
                    <div className="p-4 pt-1 text-center">
                        <img
                            src={previewImage}
                            alt="Attachment Full View"
                            className="mx-auto max-h-[80vh] w-auto rounded-xl object-contain"
                        />
                        <button
                            type="button"
                            onClick={() => setPreviewImage(null)}
                            className="mt-3 rounded-xl bg-slate-900 px-4 py-1.5 text-xs font-semibold text-white hover:bg-slate-800 dark:bg-slate-700 dark:hover:bg-slate-600"
                        >
                            Close Image
                        </button>
                    </div>
                </Modal>
            ) : null}
        </div>
    );
}

function Modal({ open, onClose, children }) {
    const modalRootRef = useRef(null);

    useEffect(() => {
        if (!open) return undefined;
        const handler = (e) => {
            if (e.key === "Escape") onClose && onClose();
        };
        document.addEventListener("keydown", handler);
        return () => document.removeEventListener("keydown", handler);
    }, [open, onClose]);

    useEffect(() => {
        if (!open || !modalRootRef.current || prefersReducedMotion()) return undefined;
        const ctx = gsap.context(() => {
            gsap.fromTo(
                ".dcm-modal-backdrop",
                { opacity: 0 },
                { opacity: 1, duration: 0.24, ease: "power2.out" }
            );
            gsap.fromTo(
                ".dcm-modal-panel",
                { opacity: 0, scale: 1.06, y: 12 },
                {
                    opacity: 1,
                    scale: 1,
                    y: 0,
                    duration: 0.34,
                    ease: "back.out(1.5)",
                    clearProps: "transform,opacity",
                }
            );
        }, modalRootRef);

        return () => ctx.revert();
    }, [open]);

    if (!open) return null;
    return (
        <div ref={modalRootRef} className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div
                className="dcm-modal-backdrop absolute inset-0 bg-slate-900/55 backdrop-blur-sm"
                onClick={() => onClose && onClose()}
            />
            <div className="dcm-modal-panel relative z-10 w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900">
                <div className="flex justify-end p-2.5">
                    <button
                        type="button"
                        onClick={() => onClose && onClose()}
                        className="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                    >
                        ✕
                    </button>
                </div>
                {children}
            </div>
        </div>
    );
}
