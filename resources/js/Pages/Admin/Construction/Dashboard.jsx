import { Link } from "@inertiajs/react";
import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";

export default function Dashboard({ stats, projects }) {
    return (
        <ConstructionShell title="Admin Dashboard" description="Assigned-project view of Phase 2 planning, reviews, and drafting execution." variant="admin">
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatCard label="Assigned Projects" value={stats.assignedProjects} />
                <StatCard label="Survey Plans" value={stats.surveyPlans} />
                <StatCard label="Review Pending" value={stats.surveyApprovalsPending} />
                <StatCard label="Drafting Queue" value={stats.draftingQueue} />
            </div>

            <div className="grid gap-6 xl:grid-cols-[1fr,1.2fr]">
                <SectionCard
                    title="Execution Focus"
                    description="The next actions that keep assigned projects moving."
                    actions={
                        <>
                            <Link href={route("admin.construction.survey.index")} className="rounded-full bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-200">Survey</Link>
                            <Link href={route("admin.construction.drafting.index")} className="rounded-full bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Drafting</Link>
                        </>
                    }
                >
                    <div className="space-y-4">
                        <Row label="Assigned projects to monitor" value={stats.assignedProjects} badge="active" />
                        <Row label="Survey submissions to review" value={stats.surveyApprovalsPending} badge="submitted" />
                        <Row label="Drafting jobs in flight" value={stats.draftingQueue} badge="drafting_in_progress" />
                    </div>
                </SectionCard>

                <SectionCard title="Assigned Projects" description="Jump directly into the projects currently on your desk.">
                    {projects.length ? (
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {projects.map((project) => (
                                <Link key={project.id} href={route("admin.construction.projects.show", project.id)} className="rounded-2xl border border-slate-200 p-5 transition hover:border-indigo-300 dark:border-slate-800">
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="font-semibold text-slate-900 dark:text-white">{project.name}</p>
                                        <StatusBadge value={project.current_stage} />
                                    </div>
                                    <p className="mt-2 text-sm text-slate-500">{project.project_code}</p>
                                    <p className="mt-3 text-sm text-slate-600 dark:text-slate-300">{project.client?.name || "-"}</p>
                                </Link>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No assigned projects." description="Once a project team assignment is made, it will show up here for execution." />
                    )}
                </SectionCard>
            </div>
        </ConstructionShell>
    );
}

function Row({ label, value, badge }) {
    return (
        <div className="flex items-center justify-between gap-3 rounded-2xl bg-slate-50 p-4 dark:bg-slate-900">
            <div>
                <p className="font-medium text-slate-900 dark:text-white">{label}</p>
                <p className="text-sm text-slate-500 dark:text-slate-400">Current count</p>
            </div>
            <div className="flex items-center gap-3">
                <p className="text-2xl font-semibold text-slate-900 dark:text-white">{value}</p>
                <StatusBadge value={badge} />
            </div>
        </div>
    );
}
