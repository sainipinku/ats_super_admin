import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import RoleSwitcher from "./Components/RoleSwitcher";
import ProjectSwitcher from "./Components/ProjectSwitcher";

export default function Dashboard({ member, roles, projects, active_role, active_project, permissions, dashboard }) {
    return (
        <ConstructionShell
            title="Site Member Dashboard"
            description="Your active construction project and role context."
            variant="member"
        >
            <div className="mb-6 flex flex-wrap items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div className="min-w-0">
                    <p className="font-semibold text-slate-900 dark:text-white">{member?.name}</p>
                    <p className="text-sm text-slate-500">
                        {active_project ? active_project.name : "No project selected"}
                    </p>
                </div>
                <div className="ml-auto flex flex-wrap items-center gap-4">
                    <ProjectSwitcher projects={projects} activeProject={active_project} activeRole={active_role} />
                    <RoleSwitcher roles={roles} activeRole={active_role} activeProject={active_project} />
                </div>
            </div>

            {!active_project ? (
                <SectionCard title="Select a project to continue" description="Choose a project to load your work context.">
                    <EmptyState title="No project selected." description="Use the Project switcher above to continue." />
                </SectionCard>
            ) : !active_role ? (
                <SectionCard title="Select your role to continue" description="Choose the role you are working as on this project.">
                    <EmptyState title="No role selected." description="Use the Working As switcher above to continue." />
                </SectionCard>
            ) : (
                <div className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        {dashboard.assigned_surveys !== undefined && (
                            <div className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                                <p className="text-sm text-slate-500">Assigned Surveys</p>
                                <p className="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">{dashboard.assigned_surveys}</p>
                            </div>
                        )}
                        {dashboard.assigned_vehicles !== undefined && (
                            <div className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                                <p className="text-sm text-slate-500">Assigned Vehicles</p>
                                <p className="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">{dashboard.assigned_vehicles?.length ?? 0}</p>
                            </div>
                        )}
                        {dashboard.active_tasks !== undefined && (
                            <div className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                                <p className="text-sm text-slate-500">Active Tasks</p>
                                <p className="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">{dashboard.active_tasks}</p>
                            </div>
                        )}
                        {dashboard.pending_approvals !== undefined && (
                            <div className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                                <p className="text-sm text-slate-500">Pending Approvals</p>
                                <p className="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">{dashboard.pending_approvals}</p>
                            </div>
                        )}
                        {dashboard.drafting_jobs !== undefined && (
                            <div className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                                <p className="text-sm text-slate-500">Drafting Jobs</p>
                                <p className="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">{dashboard.drafting_jobs}</p>
                            </div>
                        )}
                    </div>

                    {dashboard.assigned_vehicles?.length > 0 && (
                        <SectionCard title="My Vehicles" description="Vehicles assigned to you on this project.">
                            <div className="space-y-3">
                                {dashboard.assigned_vehicles.map((assignment) => (
                                    <div key={assignment.id} className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-950">
                                        <p className="font-semibold text-slate-900 dark:text-white">
                                            {assignment.vehicle?.registration_number ?? "Vehicle"}
                                        </p>
                                        <p className="text-sm text-slate-500">{assignment.vehicle?.vehicle_code ?? ""}</p>
                                    </div>
                                ))}
                            </div>
                        </SectionCard>
                    )}
                </div>
            )}
        </ConstructionShell>
    );
}