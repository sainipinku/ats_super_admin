import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import {
    FaLayerGroup,
    FaToolbox,
    FaBuilding,
    FaRulerCombined,
    FaHandshake,
    FaFlagCheckered,
} from "react-icons/fa6";
import { FaSearch, FaCheck } from "react-icons/fa";

const steps = [
    { key: "planning", label: "Planning", icon: FaLayerGroup, color: "indigo", phase: "Phase 1" },
    { key: "survey", label: "Survey", icon: FaSearch, color: "sky", phase: "Phase 2" },
    { key: "foundation", label: "Foundation", icon: FaToolbox, color: "amber", phase: "Phase 3" },
    { key: "structure", label: "Structure", icon: FaBuilding, color: "violet", phase: "Phase 3" },
    { key: "finishing", label: "Finishing", icon: FaRulerCombined, color: "fuchsia", phase: "Phase 4" },
    { key: "handover", label: "Handover", icon: FaHandshake, color: "rose", phase: "Phase 5" },
    { key: "completed", label: "Completed", icon: FaFlagCheckered, color: "emerald", phase: "Closed" },
];

const internalStageMap = {
    draft: "planning",
    budget_pending: "planning",
    budget_approved: "planning",
    team_assigned: "planning",
    survey_planned: "survey",
    survey_in_progress: "survey",
    survey_complete: "survey",
    survey: "survey",
    drafting_in_progress: "survey",
    drawing_approval_pending: "survey",
    revision_requested: "survey",
    drawing_approved: "survey",
    ready_for_construction: "foundation",
    execution_planned: "foundation",
    foundation_started: "foundation",
    foundation: "foundation",
    foundation_completed: "structure",
    structure_started: "structure",
    construction_in_progress: "structure",
    structure: "structure",
    structure_completed: "finishing",
    finishing_started: "finishing",
    finishing: "finishing",
    finishing_completed: "handover",
    handover_planned: "handover",
    handover_in_progress: "handover",
    handed_over: "handover",
    handover: "handover",
    closed: "completed",
    completed: "completed",
    planning: "planning",
};

export default function WorkflowTracker({ currentStage }) {
    const mapped = internalStageMap[currentStage] || currentStage || "planning";
    const activeIndex = Math.max(steps.findIndex((step) => step.key === mapped), 0);

    const colorRing = {
        indigo: "border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-400 dark:bg-indigo-500/10 dark:text-indigo-300",
        sky: "border-sky-500 bg-sky-50 text-sky-700 dark:border-sky-400 dark:bg-sky-500/10 dark:text-sky-300",
        amber: "border-amber-500 bg-amber-50 text-amber-700 dark:border-amber-400 dark:bg-amber-500/10 dark:text-amber-300",
        violet: "border-violet-500 bg-violet-50 text-violet-700 dark:border-violet-400 dark:bg-violet-500/10 dark:text-violet-300",
        fuchsia: "border-fuchsia-500 bg-fuchsia-50 text-fuchsia-700 dark:border-fuchsia-400 dark:bg-fuchsia-500/10 dark:text-fuchsia-300",
        rose: "border-rose-500 bg-rose-50 text-rose-700 dark:border-rose-400 dark:bg-rose-500/10 dark:text-rose-300",
        emerald: "border-emerald-500 bg-emerald-50 text-emerald-700 dark:border-emerald-400 dark:bg-emerald-500/10 dark:text-emerald-300",
    };

    const iconBg = {
        indigo: "bg-indigo-600",
        sky: "bg-sky-600",
        amber: "bg-amber-500",
        violet: "bg-violet-600",
        fuchsia: "bg-fuchsia-600",
        rose: "bg-rose-600",
        emerald: "bg-emerald-600",
    };

    return (
        <div className="rounded-2xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-5 shadow-sm dark:border-slate-800 dark:from-slate-900 dark:to-slate-950">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p className="text-[15px] font-bold text-slate-900 dark:text-white tracking-tight">Project Lifecycle · 7 Stages</p>
                    <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        Every project moves through Planning → Survey → Foundation → Structure → Finishing → Handover → Completed
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <StatusBadge value={currentStage} />
                </div>
            </div>

            <div className="mt-6 relative">
                <div className="absolute left-4 right-4 top-6 h-[2px] bg-gradient-to-r from-slate-200 via-slate-200 to-slate-200 dark:from-slate-800 dark:via-slate-800 dark:to-slate-800" />
                <div
                    className="absolute left-4 top-6 h-[2px] bg-gradient-to-r from-indigo-500 via-sky-500 via-amber-500 via-violet-500 via-fuchsia-500 via-rose-500 to-emerald-500 transition-all duration-700"
                    style={{ width: `calc(${(activeIndex / (steps.length - 1)) * 100}% - 32px * ${(activeIndex / (steps.length - 1))})` }}
                />

                <div className="relative grid grid-cols-7 gap-2">
                    {steps.map((step, index) => {
                        const Icon = step.icon;
                        const isDone = index < activeIndex;
                        const isActive = index === activeIndex;

                        return (
                            <div key={step.key} className="flex flex-col items-center text-center">
                                <div
                                    className={`relative z-10 w-12 h-12 rounded-full flex items-center justify-center border-2 transition-all ${
                                        isActive
                                            ? `${colorRing[step.color]} shadow-lg scale-105`
                                            : isDone
                                              ? "border-emerald-500 bg-emerald-500 text-white dark:border-emerald-400 shadow-sm"
                                              : "border-slate-200 bg-white text-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-500"
                                    }`}
                                >
                                    {isDone ? <FaCheck size={18} /> : <Icon size={isActive ? 20 : 17} />}
                                </div>
                                <p className={`mt-2.5 text-[11px] font-bold uppercase tracking-wider ${
                                    isActive
                                        ? "text-slate-900 dark:text-white"
                                        : isDone
                                          ? "text-emerald-600 dark:text-emerald-400"
                                          : "text-slate-400 dark:text-slate-500"
                                }`}>
                                    {step.label}
                                </p>
                                <p className="mt-0.5 text-[10px] text-slate-500 dark:text-slate-500 leading-snug max-w-[110px]">
                                    {isActive
                                        ? "Current"
                                        : isDone
                                          ? "Done"
                                          : "Pending"}
                                </p>
                            </div>
                        );
                    })}
                </div>
            </div>

            <div className="mt-6 hidden md:flex items-center justify-around">
                {steps.map((step, index) => {
                    const Icon = step.icon;
                    const isDone = index < activeIndex;
                    const isActive = index === activeIndex;
                    return (
                        <div
                            key={step.key}
                            className={`w-[120px] rounded-xl border px-3 py-2 ${
                                isActive
                                    ? `${colorRing[step.color]}`
                                    : isDone
                                      ? "border-emerald-200 bg-emerald-50/60 dark:border-emerald-500/30 dark:bg-emerald-500/10"
                                      : "border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
                            }`}
                        >
                            <div className="flex items-center gap-2">
                                <span className={`w-6 h-6 rounded-md flex items-center justify-center text-white text-[11px] ${isDone ? "bg-emerald-500" : iconBg[step.color]}`}>
                                    {isDone ? <FaCheck size={11} /> : <Icon size={11} />}
                                </span>
                                <div className="min-w-0">
                                    <p className={`text-[11px] font-bold truncate ${isActive || isDone ? "text-slate-900 dark:text-white" : "text-slate-500 dark:text-slate-400"}`}>
                                        {step.label}
                                    </p>
                                    <p className="text-[10px] text-slate-500 dark:text-slate-500 truncate">{step.phase}</p>
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
