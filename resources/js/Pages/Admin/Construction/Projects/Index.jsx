import { Link, usePage } from "@inertiajs/react";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import PendingCadApprovalAlertBanner from "@/Pages/Construction/Components/PendingCadApprovalAlertBanner";

export default function ProjectsIndex({ projects }) {
    const { props } = usePage();
    const pendingCadApprovals = Array.isArray(props?.pending_assigned_cad_approvals)
        ? props.pending_assigned_cad_approvals
        : [];
    const pendingProjectIds = new Set(pendingCadApprovals.map((item) => Number(item.project_id)));

    return (
        <ConstructionShell title="My Projects" description="Assigned projects with their current Phase 1 and Phase 2 workflow status." variant="admin">
            <PendingCadApprovalAlertBanner
                approvals={pendingCadApprovals}
                className="cad-action-blink-border"
            />
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatCard label="Assigned Projects" value={projects.length} />
                <StatCard label="Budget Approved" value={projects.filter((project) => project.current_stage === "budget_approved").length} />
                <StatCard label="Survey In Flow" value={projects.filter((project) => ["survey_planned", "survey_in_progress"].includes(project.current_stage)).length} />
                <StatCard label="Drafting / Approval" value={projects.filter((project) => ["drafting_in_progress", "drawing_approval_pending"].includes(project.current_stage)).length} />
            </div>

            <SectionCard title="Assigned Project Register" description="Open a project to see budgets, team, survey, drafting, and approval details.">
                {projects.length ? (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="text-slate-500">
                                <tr>
                                    <th className="pb-3">Project</th>
                                    <th className="pb-3">Company</th>
                                    <th className="pb-3">Client</th>
                                    <th className="pb-3">Stage</th>
                                    <th className="pb-3">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {projects.map((project) => {
                                    const hasPendingCadAction = pendingProjectIds.has(Number(project.id));
                                    return (
                                    <tr
                                        key={project.id}
                                        className={hasPendingCadAction ? "cad-action-blink-border bg-rose-50/60 dark:bg-rose-950/25" : ""}
                                    >
                                        <td className="py-3 px-2">
                                            <div className="font-medium text-slate-900 dark:text-white">{project.name}</div>
                                            <div className="text-xs text-slate-500">{project.project_code}</div>
                                            {hasPendingCadAction && (
                                                <span className="mt-1 inline-flex items-center rounded-full bg-rose-600 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">
                                                    Action Needed: CAD Approval
                                                </span>
                                            )}
                                        </td>
                                        <td className="py-3 text-slate-600 dark:text-slate-300">{project.company?.name || "-"}</td>
                                        <td className="py-3 text-slate-600 dark:text-slate-300">{project.client?.name || "-"}</td>
                                        <td className="py-3"><StatusBadge value={project.current_stage} /></td>
                                        <td className="py-3">
                                            <Link href={route("admin.construction.projects.show", project.id)} className="text-sm font-medium text-indigo-600">Open workflow</Link>
                                        </td>
                                    </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <EmptyState title="No assigned projects." description="Project assignments will appear here once Phase 1 team assignment is done." />
                )}
            </SectionCard>
        </ConstructionShell>
    );
}
