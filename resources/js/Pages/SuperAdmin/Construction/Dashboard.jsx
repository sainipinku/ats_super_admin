import { Link } from "@inertiajs/react";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";

export default function Dashboard({ stats, recentProjects, recentActivity }) {
    return (
        <ConstructionShell
            title="Super Admin Dashboard"
            description="Global command center for Phase 1 foundation setup and Phase 2 survey, drafting, and drawing approvals."
            variant="super"
        >
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-7">
                <StatCard label="Companies" value={stats.companies} />
                <StatCard label="Clients" value={stats.clients} />
                <StatCard label="Projects" value={stats.projects} />
                <StatCard label="Budget Pending" value={stats.budgetPending} />
                <StatCard label="Survey Planned" value={stats.surveyPlanned} />
                <StatCard label="Review Pending" value={stats.surveyApprovalsPending} />
                <StatCard label="Drafting Queue" value={stats.draftingQueue} />
            </div>

            <div className="grid gap-6 xl:grid-cols-[1.4fr,1fr]">
                <SectionCard
                    title="Phase 1 Foundation"
                    description="Core records and approvals that must be completed before Phase 2 execution."
                    actions={
                        <>
                            <Link href={route("super.construction.companies.index")} className="rounded-full bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-200">Companies</Link>
                            <Link href={route("super.construction.clients.index")} className="rounded-full bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-200">Clients</Link>
                            <Link href={route("super.construction.projects.index")} className="rounded-full bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Projects</Link>
                        </>
                    }
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <StageCard title="Register companies and clients" count={stats.companies + stats.clients} note="Build the master data that all later modules depend on." />
                        <StageCard title="Create and approve projects" count={stats.budgetPending} note="Budget approval is the current Phase 1 blocker count." accent="amber" />
                    </div>
                </SectionCard>

                <SectionCard
                    title="Phase 2 Execution"
                    description="Survey planning, submission review, drafting, and final drawing approval."
                    actions={<Link href={route("super.construction.survey.index")} className="rounded-full bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Open Survey</Link>}
                >
                    <div className="space-y-4">
                        <StageRow label="Survey planned / in progress" value={stats.surveyPlanned} badge="survey_planned" />
                        <StageRow label="Survey reviews pending" value={stats.surveyApprovalsPending} badge="submitted" />
                        <StageRow label="Drafting queue" value={stats.draftingQueue} badge="drafting_in_progress" />
                    </div>
                </SectionCard>
            </div>

            <div className="grid gap-6 xl:grid-cols-[1.5fr,1fr]">
                <SectionCard title="Recent Projects" description="Projects moving through Phase 1 and Phase 2 right now.">
                    {recentProjects.length ? (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-left text-sm">
                                <thead className="text-slate-500">
                                    <tr>
                                        <th className="pb-3">Project</th>
                                        <th className="pb-3">Client</th>
                                        <th className="pb-3">Stage</th>
                                        <th className="pb-3">Budget</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {recentProjects.map((project) => (
                                        <tr key={project.id}>
                                            <td className="py-3">
                                                <Link href={route("super.construction.projects.show", project.id)} className="font-medium text-slate-900 hover:text-indigo-600 dark:text-white">
                                                    {project.name}
                                                </Link>
                                                <div className="text-xs text-slate-500">{project.project_code}</div>
                                            </td>
                                            <td className="py-3 text-slate-600 dark:text-slate-300">{project.client?.name || "-"}</td>
                                            <td className="py-3"><StatusBadge value={project.current_stage} /></td>
                                            <td className="py-3 text-slate-600 dark:text-slate-300">{project.latest_budget?.approved_amount || project.latest_budget?.estimated_amount || "-"}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <EmptyState title="No construction projects yet." description="Start by creating the first project from the Projects screen." />
                    )}
                </SectionCard>

                <SectionCard title="Recent Activity" description="Cross-project workflow actions.">
                    {recentActivity.length ? (
                        <div className="space-y-3">
                            {recentActivity.map((item) => (
                                <div key={item.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="font-medium text-slate-900 dark:text-white">{item.module}</p>
                                        <StatusBadge value={item.action} />
                                    </div>
                                    <p className="mt-2 text-sm text-slate-500">Project: {item.project?.name || "Global"}</p>
                                    <p className="mt-1 text-xs text-slate-500">{item.created_at}</p>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No activity yet." description="Activity logs will appear as the ERP workflow starts moving." />
                    )}
                </SectionCard>
            </div>
        </ConstructionShell>
    );
}

function StageCard({ title, count, note, accent = "indigo" }) {
    const accentClasses = {
        indigo: "border-indigo-200 bg-indigo-50 dark:border-indigo-500/30 dark:bg-indigo-500/10",
        amber: "border-amber-200 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/10",
    };

    return (
        <div className={`rounded-2xl border p-4 ${accentClasses[accent] || accentClasses.indigo}`}>
            <p className="text-sm font-semibold text-slate-900 dark:text-white">{title}</p>
            <p className="mt-2 text-3xl font-semibold text-slate-900 dark:text-white">{count}</p>
            <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">{note}</p>
        </div>
    );
}

function StageRow({ label, value, badge }) {
    return (
        <div className="flex items-center justify-between gap-3 rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
            <div>
                <p className="font-medium text-slate-900 dark:text-white">{label}</p>
                <p className="text-sm text-slate-500 dark:text-slate-400">Current workload count</p>
            </div>
            <div className="flex items-center gap-3">
                <p className="text-2xl font-semibold text-slate-900 dark:text-white">{value}</p>
                <StatusBadge value={badge} />
            </div>
        </div>
    );
}
