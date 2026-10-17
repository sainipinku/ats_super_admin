import React, { useState } from "react";

function getFileIcon(ext = "") {
    const e = String(ext).toLowerCase().replace(/^\./, "");
    if (["pdf"].includes(e)) return "📑";
    if (["jpg", "jpeg", "png", "webp", "gif", "svg"].includes(e)) return "🖼️";
    if (["csv", "xls", "xlsx"].includes(e)) return "📊";
    if (["dwg", "dxf"].includes(e)) return "📐";
    if (["kml", "kmz", "raw", "txt"].includes(e)) return "🗺️";
    return "📄";
}

function safeParse(val) {
    if (!val) return null;
    if (typeof val === "object" && val !== null) return val;
    if (typeof val === "string") {
        const trimmed = val.trim();
        if ((trimmed.startsWith("{") && trimmed.endsWith("}")) || (trimmed.startsWith("[") && trimmed.endsWith("]"))) {
            try {
                return JSON.parse(trimmed);
            } catch (e) {
                return null;
            }
        }
    }
    return null;
}

export default function FieldNotesDisplay({ notes, compact = false, hideRemarks = false, className = "" }) {
    const [showRaw, setShowRaw] = useState(false);

    if (!notes) return null;

    const data = safeParse(notes);

    // If notes is just a simple plain string and not JSON
    if (!data || typeof data !== "object") {
        return (
            <div className={`mt-2 rounded-xl bg-slate-100/80 p-3 text-xs dark:bg-slate-900/80 ${className}`}>
                <p className="font-semibold text-slate-800 dark:text-slate-200">Field Notes</p>
                <p className="mt-1 whitespace-pre-wrap break-words text-slate-700 dark:text-slate-300">{String(notes)}</p>
            </div>
        );
    }

    const {
        work_status,
        has_obstruction,
        obstruction_details,
        pending_work,
        start_time,
        end_time,
        total_hours,
        work_summary,
        categorized_files,
        remarks,
        checklist_summary,
        submitted_at,
    } = data;

    const isObstruction =
        has_obstruction === true ||
        has_obstruction === 1 ||
        has_obstruction === "1" ||
        String(has_obstruction).toLowerCase() === "true" ||
        Boolean(obstruction_details);

    const timingText =
        total_hours || (start_time && end_time ? `${start_time} - ${end_time}` : work_summary?.total_hours);

    // Aggregate all categorized files
    const allFileGroups = [
        {
            key: "survey_data",
            label: "Survey Data & Raw Files",
            badge: "Raw / Survey Data",
            badgeColor: "bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-800",
            files: Array.isArray(categorized_files?.survey_data) ? categorized_files.survey_data : [],
        },
        {
            key: "sketch_markup",
            label: "Sketches & Markups",
            badge: "Sketch / Markup",
            badgeColor: "bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/40 dark:text-purple-300 dark:border-purple-800",
            files: Array.isArray(categorized_files?.sketch_markup) ? categorized_files.sketch_markup : [],
        },
        {
            key: "reference_documents",
            label: "Reference Documents",
            badge: "Reference Doc",
            badgeColor: "bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800",
            files: Array.isArray(categorized_files?.reference_documents) ? categorized_files.reference_documents : [],
        },
    ];

    const totalFilesCount = allFileGroups.reduce((acc, g) => acc + g.files.length, 0);

    return (
        <div className={`mt-3 space-y-3 rounded-2xl border border-slate-200 bg-white p-3.5 shadow-2xs dark:border-slate-800 dark:bg-slate-900/90 text-xs ${className}`}>
            {/* Header: Title and Badges */}
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2.5 dark:border-slate-800">
                <span className="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-1.5">
                    <span>📋</span>
                    <span>Field Duty Execution Details</span>
                </span>

                <div className="flex flex-wrap items-center gap-1.5">
                    {/* Work Status */}
                    {work_status && (
                        <span
                            className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-semibold border ${
                                work_status === "completed"
                                    ? "bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800"
                                    : work_status === "partial"
                                    ? "bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800"
                                    : "bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800"
                            }`}
                        >
                            <span>{work_status === "completed" ? "✓" : work_status === "partial" ? "⏳" : "✕"}</span>
                            <span>{work_status.charAt(0).toUpperCase() + work_status.slice(1)}</span>
                        </span>
                    )}

                    {/* Site Obstruction Status */}
                    <span
                        className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-semibold border ${
                            isObstruction
                                ? "bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800"
                                : "bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700"
                        }`}
                    >
                        <span>{isObstruction ? "🚧" : "✓"}</span>
                        <span>{isObstruction ? "Obstruction Reported" : "No Obstruction"}</span>
                    </span>

                    {/* Timing */}
                    {timingText && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-200/60 dark:border-slate-700">
                            <span>⏰</span>
                            <span>{timingText}</span>
                        </span>
                    )}

                    {/* Checklist Counter */}
                    {checklist_summary && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-0.5 text-[11px] font-semibold text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                            <span>☑</span>
                            <span>
                                Checklists: {checklist_summary.completed}/{checklist_summary.total}
                            </span>
                        </span>
                    )}
                </div>
            </div>

            {/* Obstruction Details Callout */}
            {isObstruction && obstruction_details && (
                <div className="rounded-xl border border-rose-200 bg-rose-50/80 p-3 text-rose-900 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200">
                    <p className="font-bold flex items-center gap-1.5 text-rose-800 dark:text-rose-300">
                        <span>🚧</span>
                        <span>Obstruction Notes:</span>
                    </p>
                    <p className="mt-1 leading-relaxed text-rose-950 dark:text-rose-100 whitespace-pre-wrap">
                        {obstruction_details}
                    </p>
                </div>
            )}

            {/* Pending Work Callout */}
            {pending_work && (
                <div className="rounded-xl border border-amber-200 bg-amber-50/80 p-3 text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
                    <p className="font-bold flex items-center gap-1.5 text-amber-800 dark:text-amber-300">
                        <span>⏳</span>
                        <span>Pending / Remaining Work:</span>
                    </p>
                    <p className="mt-1 leading-relaxed text-amber-950 dark:text-amber-100 whitespace-pre-wrap">
                        {pending_work}
                    </p>
                </div>
            )}

            {/* Categorized Attached Files */}
            {totalFilesCount > 0 && (
                <div className="space-y-2 rounded-xl border border-slate-200/80 bg-slate-50/60 p-3 dark:border-slate-800 dark:bg-slate-900/60">
                    <div className="flex items-center justify-between">
                        <p className="text-[11px] font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                            <span>📁</span>
                            <span>Attached Files & Documents ({totalFilesCount})</span>
                        </p>
                    </div>

                    <div className="grid gap-2 sm:grid-cols-2">
                        {allFileGroups.map((group) =>
                            group.files.map((file, fIdx) => {
                                const href = file.url || file.path;
                                if (!href) return null;
                                const safeHref = /^https?:\/\//i.test(href)
                                    ? href
                                    : href.startsWith("/")
                                    ? href
                                    : "/" + href.replace(/^\/+/, "");
                                const ext = file.extension || file.name?.split(".").pop() || "";
                                return (
                                    <div
                                        key={`${group.key}-${fIdx}`}
                                        className="flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white p-2 shadow-2xs hover:border-indigo-300 dark:border-slate-700 dark:bg-slate-950"
                                    >
                                        <div className="flex items-center gap-2 min-w-0">
                                            <span className="text-base select-none">{getFileIcon(ext)}</span>
                                            <div className="min-w-0">
                                                <p
                                                    className="font-medium text-slate-800 dark:text-slate-200 truncate text-[11px]"
                                                    title={file.name || "File"}
                                                >
                                                    {file.name || "Attached File"}
                                                </p>
                                                <div className="flex items-center gap-1.5 text-[10px] text-slate-400">
                                                    <span className={`rounded-sm px-1 font-semibold border ${group.badgeColor}`}>
                                                        {group.badge}
                                                    </span>
                                                    {file.size && <span>• {file.size}</span>}
                                                </div>
                                            </div>
                                        </div>

                                        <a
                                            href={safeHref}
                                            target="_blank"
                                            rel="noreferrer noopener"
                                            className="shrink-0 inline-flex items-center gap-1 rounded-md bg-indigo-50 px-2 py-1 text-[11px] font-semibold text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-950/50 dark:text-indigo-300 dark:hover:bg-indigo-900/50"
                                            title="View / Download File"
                                        >
                                            <span>Open</span>
                                            <span>↗</span>
                                        </a>
                                    </div>
                                );
                            })
                        )}
                    </div>
                </div>
            )}

            {/* Remarks if provided */}
            {remarks && !hideRemarks && (
                <div className="rounded-xl bg-slate-100/70 p-2.5 text-slate-700 dark:bg-slate-800/50 dark:text-slate-300">
                    <p className="font-semibold text-slate-800 dark:text-slate-200">Notes / Remarks</p>
                    <p className="mt-1 leading-relaxed whitespace-pre-wrap">{remarks}</p>
                </div>
            )}

            {/* Toggle Raw JSON Viewer for Admins / Debugging */}
            <div className="pt-1 flex items-center justify-end">
                <button
                    type="button"
                    onClick={() => setShowRaw(!showRaw)}
                    className="text-[10px] text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 underline"
                >
                    {showRaw ? "Hide Raw Data" : "View Raw JSON Data"}
                </button>
            </div>

            {showRaw && (
                <pre className="mt-2 max-h-48 overflow-auto rounded-lg bg-slate-900 p-2.5 font-mono text-[10px] text-emerald-400">
                    {JSON.stringify(data, null, 2)}
                </pre>
            )}
        </div>
    );
}
