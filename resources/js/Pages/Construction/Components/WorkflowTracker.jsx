import { useEffect, useId, useRef, useState } from "react";
import gsap from "gsap";
import { MotionPathPlugin } from "gsap/MotionPathPlugin";
import { MotionPathHelper } from "gsap/MotionPathHelper";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import {
    FaLocationDot,
    FaCompassDrafting,
    FaFileInvoiceDollar,
    FaUserCheck,
    FaCircleCheck,
    FaCheck,
    FaBezierCurve,
} from "react-icons/fa6";

gsap.registerPlugin(MotionPathPlugin);

const steps = [
    { key: "survey", label: "Survey Team", icon: FaLocationDot, color: "sky", subtext: "Site Data & Photos" },
    { key: "drafting", label: "Draft Team", icon: FaCompassDrafting, color: "indigo", subtext: "Map & DWG/PDF" },
    { key: "accounts", label: "Accounts Team", icon: FaFileInvoiceDollar, color: "amber", subtext: "Commercial Verify" },
    { key: "supervisor", label: "Supervisor", icon: FaUserCheck, color: "violet", subtext: "Final Chain Review" },
    { key: "completed", label: "Completed", icon: FaCircleCheck, color: "emerald", subtext: "Approved & Locked" },
];

const internalStageMap = {
    // Survey stage variants
    survey: "survey",
    survey_submitted: "survey",
    survey_planned: "survey",
    survey_in_progress: "survey",
    survey_complete: "survey",
    budget_pending: "survey",
    budget_approved: "survey",
    draft: "survey",
    planning: "survey",
    team_assigned: "survey",

    // Drafting stage variants
    drafting: "drafting",
    drafting_in_progress: "drafting",
    draft_ready_for_review: "drafting",
    drawing_approval_pending: "drafting",
    revision_requested: "drafting",
    drawing_approved: "drafting",

    // Accounts stage variants
    accounts: "accounts",
    accounts_pending: "accounts",
    billing_pending: "accounts",

    // Supervisor stage variants
    supervisor: "supervisor",
    awaiting_supervisor_review: "supervisor",
    supervisor_review: "supervisor",

    // Completed stage variants
    completed: "completed",
    closed: "completed",
    approved: "completed",
};

function buildWorkflowCurve(points) {
    if (!points || points.length < 2) return "";

    let d = `M ${points[0].x.toFixed(1)},${points[0].y.toFixed(1)}`;
    for (let i = 0; i < points.length - 1; i++) {
        const p0 = points[i];
        const p1 = points[i + 1];
        const dx = p1.x - p0.x;
        // Alternate subtle arc up (-14px) and down (+10px) staying cleanly above text labels
        const arcOffset = i % 2 === 0 ? -14 : 10;
        const cx1 = p0.x + dx * 0.35;
        const cy1 = p0.y + arcOffset;
        const cx2 = p1.x - dx * 0.35;
        const cy2 = p1.y + arcOffset;
        d += ` C ${cx1.toFixed(1)},${cy1.toFixed(1)} ${cx2.toFixed(1)},${cy2.toFixed(1)} ${p1.x.toFixed(1)},${p1.y.toFixed(1)}`;
    }
    return d;
}

export default function WorkflowTracker({ currentStage, isLocked = false }) {
    const mapped = internalStageMap[currentStage] || "survey";
    const activeIndex = Math.max(steps.findIndex((step) => step.key === mapped), 0);
    const progressRatio = activeIndex / (steps.length - 1);

    const rawId = useId();
    const uid = rawId.replace(/[^a-zA-Z0-9_-]/g, "");

    const trackerRef = useRef(null);
    const stepperRef = useRef(null);
    const pathRef = useRef(null);
    const activePathRef = useRef(null);
    const fileRef = useRef(null);
    const helperRef = useRef(null);

    const [isEditingPath, setIsEditingPath] = useState(false);
    const [svgGeometry, setSvgGeometry] = useState({ width: 0, height: 0, path: "" });

    // Measure exact pixel centers of all 5 step circles so the curved path aligns 1:1 at every width
    useEffect(() => {
        const container = stepperRef.current;
        if (!container) return;

        const measure = () => {
            const containerRect = container.getBoundingClientRect();
            const circles = Array.from(container.querySelectorAll(".wf-step-circle"));
            if (circles.length < 2 || containerRect.width <= 0) return;

            const points = circles.map((el) => {
                const r = el.getBoundingClientRect();
                return {
                    x: r.left - containerRect.left + r.width / 2,
                    y: r.top - containerRect.top + r.height / 2,
                };
            });

            const nextPath = buildWorkflowCurve(points);
            setSvgGeometry((prev) => {
                if (
                    Math.abs(prev.width - containerRect.width) < 1 &&
                    Math.abs(prev.height - containerRect.height) < 1 &&
                    prev.path === nextPath
                ) {
                    return prev;
                }
                return {
                    width: Math.round(containerRect.width),
                    height: Math.round(containerRect.height),
                    path: nextPath,
                };
            });
        };

        measure();
        const rafId = requestAnimationFrame(measure);

        let observer;
        if (typeof ResizeObserver !== "undefined") {
            observer = new ResizeObserver(measure);
            observer.observe(container);
        }
        window.addEventListener("resize", measure);

        return () => {
            cancelAnimationFrame(rafId);
            if (observer) observer.disconnect();
            window.removeEventListener("resize", measure);
        };
    }, [currentStage]);

    // Entrance animations for header, nodes, and bottom pills
    useEffect(() => {
        if (!trackerRef.current) return;

        const ctx = gsap.context(() => {
            gsap.fromTo(
                trackerRef.current,
                { opacity: 0, y: 10 },
                { opacity: 1, y: 0, duration: 0.36, ease: "power2.out", clearProps: "transform,opacity" }
            );

            const nodes = trackerRef.current.querySelectorAll(".wf-step-node");
            if (nodes.length > 0) {
                gsap.fromTo(
                    nodes,
                    { opacity: 0, scale: 0.85, y: 6 },
                    {
                        opacity: 1,
                        scale: 1,
                        y: 0,
                        duration: 0.34,
                        stagger: 0.055,
                        ease: "back.out(1.5)",
                        clearProps: "transform,opacity",
                    }
                );
            }

            const stagePills = trackerRef.current.querySelectorAll(".wf-stage-pill");
            if (stagePills.length > 0) {
                gsap.fromTo(
                    stagePills,
                    { opacity: 0, y: 8 },
                    {
                        opacity: 1,
                        y: 0,
                        duration: 0.3,
                        stagger: 0.04,
                        delay: 0.1,
                        ease: "power2.out",
                        clearProps: "transform,opacity",
                    }
                );
            }
        }, trackerRef);

        return () => ctx.revert();
    }, [activeIndex, currentStage]);

    // MotionPath animation for gliding Project File icon + curved active progress trace
    useEffect(() => {
        if (!svgGeometry.path || !stepperRef.current) return;

        const ctx = gsap.context(() => {
            // Animate active curved progress trace along the SVG path
            const activePathEl = activePathRef.current;
            if (activePathEl && typeof activePathEl.getTotalLength === "function") {
                const totalLength = activePathEl.getTotalLength();
                const targetOffset = totalLength * (1 - progressRatio);
                gsap.set(activePathEl, {
                    strokeDasharray: totalLength,
                    strokeDashoffset: totalLength,
                });
                gsap.to(activePathEl, {
                    strokeDashoffset: targetOffset,
                    duration: 0.75,
                    delay: 0.1,
                    ease: "power2.out",
                });
            }

            // MotionPath gliding file icon along the exact pixel-aligned curved path
            const pathEl = pathRef.current;
            const fileEl = fileRef.current;
            if (pathEl && fileEl) {
                gsap.set(fileEl, { scale: 0.95, autoAlpha: 1, transformOrigin: "50% 50%" });
                gsap.to(fileEl, {
                    duration: 5.5,
                    repeat: -1,
                    ease: "power1.inOut",
                    immediateRender: true,
                    motionPath: {
                        path: pathEl,
                        align: pathEl,
                        alignOrigin: [0.5, 0.5],
                        autoRotate: 0,
                    },
                });
            }
        }, stepperRef);

        return () => {
            if (helperRef.current) {
                helperRef.current.kill();
                helperRef.current = null;
            }
            ctx.revert();
        };
    }, [svgGeometry.path, progressRatio]);

    // Optional interactive MotionPathHelper editor toggle
    useEffect(() => {
        if (helperRef.current) {
            helperRef.current.kill();
            helperRef.current = null;
        }

        if (isEditingPath && fileRef.current && pathRef.current && svgGeometry.path) {
            helperRef.current = MotionPathHelper.create(fileRef.current, {
                path: pathRef.current,
                pathColor: "#0ae448",
                pathWidth: 2,
                selected: true,
            });
        }

        return () => {
            if (helperRef.current) {
                helperRef.current.kill();
                helperRef.current = null;
            }
        };
    }, [isEditingPath, svgGeometry.path]);

    const handlePillHover = (e, lift) => {
        gsap.to(e.currentTarget, {
            y: lift ? -2 : 0,
            duration: 0.18,
            ease: "power1.out",
            overwrite: "auto",
        });
    };

    const colorRing = {
        sky: "border-sky-500 bg-sky-50 text-sky-700 dark:border-sky-400 dark:bg-sky-500/15 dark:text-sky-300",
        indigo: "border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-400 dark:bg-indigo-500/15 dark:text-indigo-300",
        amber: "border-amber-500 bg-amber-50 text-amber-700 dark:border-amber-400 dark:bg-amber-500/15 dark:text-amber-300",
        violet: "border-violet-500 bg-violet-50 text-violet-700 dark:border-violet-400 dark:bg-violet-500/15 dark:text-violet-300",
        emerald: "border-emerald-500 bg-emerald-50 text-emerald-700 dark:border-emerald-400 dark:bg-emerald-500/15 dark:text-emerald-300",
    };

    const iconBg = {
        sky: "bg-sky-600",
        indigo: "bg-indigo-600",
        amber: "bg-amber-600",
        violet: "bg-violet-600",
        emerald: "bg-emerald-600",
    };

    return (
        <div
            ref={trackerRef}
            className="rounded-2xl border border-slate-200/80 bg-gradient-to-br from-white via-slate-50/50 to-slate-100/30 px-4 py-3.5 shadow-xs dark:border-slate-800 dark:from-slate-900 dark:via-slate-900/90 dark:to-slate-950"
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap items-baseline gap-x-2.5 gap-y-0.5">
                    <div className="flex items-center gap-2">
                        <p className="text-sm font-bold text-slate-900 dark:text-white tracking-tight">
                            Complete Workflow Progress
                        </p>
                        {isLocked && (
                            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                                🔒 Locked
                            </span>
                        )}
                    </div>
                    <p className="text-[11px] text-slate-500 dark:text-slate-400 hidden sm:block">
                        Survey → Draft → Accounts → Supervisor → Completed
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => setIsEditingPath((prev) => !prev)}
                        title="Toggle GSAP MotionPathHelper curve editor"
                        className={`inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[10px] font-semibold transition ${
                            isEditingPath
                                ? "border-emerald-400 bg-emerald-500/15 text-emerald-600 dark:text-emerald-300"
                                : "border-slate-200 bg-white/80 text-slate-500 hover:border-emerald-400/70 hover:text-emerald-600 dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-400 dark:hover:text-emerald-300"
                        }`}
                    >
                        <FaBezierCurve size={10} />
                        <span>{isEditingPath ? "Done Path" : "Edit Path"}</span>
                    </button>
                    <StatusBadge value={currentStage} />
                </div>
            </div>

            {/* Pixel-Aligned Curved GSAP MotionPath + 5-Stage Nodes */}
            <div ref={stepperRef} className="mt-2 relative pt-3.5 pb-1">
                {/* Layer 1 (Behind circles): Base dashed curve + Active gradient progress trace */}
                {svgGeometry.width > 0 && svgGeometry.path && (
                    <svg
                        viewBox={`0 0 ${svgGeometry.width} ${svgGeometry.height}`}
                        width={svgGeometry.width}
                        height={svgGeometry.height}
                        className="pointer-events-none absolute inset-0 z-0 overflow-visible"
                        aria-hidden="true"
                    >
                        <defs>
                            <linearGradient id={`wf-progress-grad-${uid}`} x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" stopColor="#0ea5e9" />
                                <stop offset="35%" stopColor="#6366f1" />
                                <stop offset="65%" stopColor="#f59e0b" />
                                <stop offset="85%" stopColor="#8b5cf6" />
                                <stop offset="100%" stopColor="#0ae448" />
                            </linearGradient>
                        </defs>

                        {/* Base Curved Dashed Track */}
                        <path
                            className="stroke-slate-300/75 dark:stroke-slate-700/85"
                            fill="none"
                            strokeWidth="2"
                            strokeDasharray="5 5"
                            d={svgGeometry.path}
                        />

                        {/* Active Progress Curve Overlay */}
                        <path
                            ref={activePathRef}
                            className="wf-progress-bar"
                            fill="none"
                            stroke={`url(#wf-progress-grad-${uid})`}
                            strokeWidth="2.5"
                            strokeLinecap="round"
                            d={svgGeometry.path}
                        />
                    </svg>
                )}

                {/* 5-Stage Circle Nodes & Labels */}
                <div className="relative z-10 grid grid-cols-5 gap-1.5">
                    {steps.map((step, index) => {
                        const Icon = step.icon;
                        const isDone = index < activeIndex;
                        const isActive = index === activeIndex;

                        return (
                            <div key={step.key} className="wf-step-node flex flex-col items-center text-center group">
                                <div
                                    className={`wf-step-circle relative z-10 w-8 h-8 rounded-full flex items-center justify-center border-2 transition-all duration-300 ${
                                        isActive
                                            ? `${colorRing[step.color]} shadow-md ring-2 ring-offset-1 ring-sky-500/30 dark:ring-offset-slate-950`
                                            : isDone
                                              ? "border-emerald-500 bg-emerald-500 text-white dark:border-emerald-400 shadow-2xs"
                                              : "border-slate-200 bg-white text-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-500"
                                    }`}
                                >
                                    {isActive && (
                                        <span className="pointer-events-none absolute inset-0 rounded-full animate-ping bg-sky-400/25" />
                                    )}
                                    {isDone ? (
                                        <FaCheck size={12} className="text-white" />
                                    ) : (
                                        <Icon size={isActive ? 14 : 12} />
                                    )}
                                </div>
                                <p
                                    className={`mt-3 text-[10px] font-bold uppercase tracking-wider leading-tight ${
                                        isActive
                                            ? "text-slate-900 dark:text-white"
                                            : isDone
                                              ? "text-emerald-600 dark:text-emerald-400"
                                              : "text-slate-400 dark:text-slate-500"
                                    }`}
                                >
                                    {step.label} {isDone ? "✓" : "○"}
                                </p>
                                <p className="mt-0.5 text-[9.5px] text-slate-500 dark:text-slate-500 truncate max-w-[115px]">
                                    {step.subtext}
                                </p>
                            </div>
                        );
                    })}
                </div>

                {/* Layer 2 (In front of circles): Reference MotionPath + Animated Gliding Project File Icon */}
                {svgGeometry.width > 0 && svgGeometry.path && (
                    <svg
                        id={`wf-motion-svg-${uid}`}
                        viewBox={`0 0 ${svgGeometry.width} ${svgGeometry.height}`}
                        width={svgGeometry.width}
                        height={svgGeometry.height}
                        className={`absolute inset-0 overflow-visible ${
                            isEditingPath ? "pointer-events-auto z-30" : "pointer-events-none z-20"
                        }`}
                        aria-hidden="true"
                    >
                        <defs>
                            <linearGradient id={`macha-${uid}`} x1="0.045" y1="0.293" x2="0.955" y2="0.707">
                                <stop offset="20.74%" stopColor="#0ae448" />
                                <stop offset="65.5%" stopColor="#abff84" />
                            </linearGradient>

                            <filter id={`macha-glow-${uid}`} x="-60%" y="-60%" width="220%" height="220%">
                                <feGaussianBlur stdDeviation="2.5" result="blur" />
                                <feMerge>
                                    <feMergeNode in="blur" />
                                    <feMergeNode in="SourceGraphic" />
                                </feMerge>
                            </filter>
                        </defs>

                        {/* MotionPath Reference Curve (invisible normally, editable when MotionPathHelper active) */}
                        <path
                            ref={pathRef}
                            id={`wf-motion-path-${uid}`}
                            className="wf-motion-path"
                            fill="none"
                            stroke="none"
                            strokeWidth="2"
                            d={svgGeometry.path}
                        />

                        {/* Animated Gliding Project File / Document Icon */}
                        <g ref={fileRef} className="wf-motion-file" style={{ visibility: "hidden" }}>
                            <circle
                                cx="0"
                                cy="0"
                                r="14"
                                fill={`url(#macha-${uid})`}
                                opacity="0.18"
                                filter={`url(#macha-glow-${uid})`}
                            />
                            <path
                                d="M -7.5,-10 L 2.5,-10 L 7.5,-5 L 7.5,9.5 C 7.5,10.6 6.6,11.5 5.5,11.5 L -5.5,11.5 C -6.6,11.5 -7.5,10.6 -7.5,9.5 Z"
                                fill={`url(#macha-${uid})`}
                                stroke="#052e16"
                                strokeWidth="1"
                                filter={`url(#macha-glow-${uid})`}
                            />
                            <path d="M 2.5,-10 L 2.5,-5 L 7.5,-5 Z" fill="#ecfdf5" opacity="0.92" />
                            <line x1="-4" y1="-1" x2="4" y2="-1" stroke="#052e16" strokeWidth="1.35" strokeLinecap="round" />
                            <line x1="-4" y1="3" x2="4" y2="3" stroke="#052e16" strokeWidth="1.35" strokeLinecap="round" />
                            <line x1="-4" y1="7" x2="1.8" y2="7" stroke="#052e16" strokeWidth="1.35" strokeLinecap="round" />
                        </g>
                    </svg>
                )}
            </div>

            <div className="mt-3 hidden md:flex items-center justify-between gap-2 border-t border-slate-200/60 dark:border-slate-800/60 pt-2.5">
                {steps.map((step, index) => {
                    const Icon = step.icon;
                    const isDone = index < activeIndex;
                    const isActive = index === activeIndex;

                    return (
                        <div
                            key={step.key}
                            onMouseEnter={(e) => handlePillHover(e, true)}
                            onMouseLeave={(e) => handlePillHover(e, false)}
                            className={`wf-stage-pill flex-1 rounded-xl border px-2.5 py-1.5 transition-colors ${
                                isActive
                                    ? `${colorRing[step.color]} shadow-2xs`
                                    : isDone
                                      ? "border-emerald-200 bg-emerald-50/50 dark:border-emerald-500/30 dark:bg-emerald-500/10"
                                      : "border-slate-200/70 bg-white dark:border-slate-800 dark:bg-slate-900/60"
                            }`}
                        >
                            <div className="flex items-center gap-2">
                                <span
                                    className={`w-6 h-6 rounded-md flex items-center justify-center text-white shrink-0 ${
                                        isDone ? "bg-emerald-500" : iconBg[step.color]
                                    }`}
                                >
                                    {isDone ? <FaCheck size={10} /> : <Icon size={10} />}
                                </span>
                                <div className="min-w-0 leading-tight">
                                    <p
                                        className={`text-[11px] font-bold truncate ${
                                            isActive || isDone
                                                ? "text-slate-900 dark:text-white"
                                                : "text-slate-500 dark:text-slate-400"
                                        }`}
                                    >
                                        {step.label}
                                    </p>
                                    <p className="text-[9.5px] text-slate-500 dark:text-slate-500 truncate">
                                        {isActive ? "In Progress" : isDone ? "Completed ✓" : "Pending"}
                                    </p>
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
