import { Head, Link, usePage } from "@inertiajs/react";
import SuperAdminLayout from "@/Pages/SuperAdmin/Layouts/AuthenticatedLayout";
import AdminLayout from "@/Pages/Admin/Layouts/AuthenticatedLayout";

const variantConfig = {
    super: {
        layout: SuperAdminLayout,
        items: [
            { label: "Dashboard", href: route("super.construction.dashboard"), active: "super.construction.dashboard", permissions: ["dashboard.view"] },
            { label: "Companies", href: route("super.construction.companies.index"), active: "super.construction.companies.*", permissions: ["company.manage"] },
            { label: "Clients", href: route("super.construction.clients.index"), active: "super.construction.clients.*", permissions: ["client.manage"] },
            { label: "Projects", href: route("super.construction.projects.index"), active: "super.construction.projects.*", permissions: ["project.manage"] },
            { label: "Survey", href: route("super.construction.survey.index"), active: "super.construction.survey.*", permissions: ["survey_plan.manage", "survey_submission.review"] },
            { label: "Drafting", href: route("super.construction.drafting.index"), active: "super.construction.drafting.*", permissions: ["drafting.manage", "drawing_approval.manage"] },
            { label: "Execution", href: route("super.construction.execution.index"), active: "super.construction.execution.*", permissions: ["execution.manage", "execution_task.manage", "dpr.review", "attendance.review"] },
        ],
    },
    admin: {
        layout: AdminLayout,
        items: [
            { label: "Dashboard", href: route("admin.construction.dashboard"), active: "admin.construction.dashboard", permissions: ["dashboard.view"] },
            { label: "Projects", href: route("admin.construction.projects.index"), active: "admin.construction.projects.*", permissions: ["project.manage"] },
            { label: "Survey", href: route("admin.construction.survey.index"), active: "admin.construction.survey.*", permissions: ["survey_plan.manage", "survey_submission.review"] },
            { label: "Drafting", href: route("admin.construction.drafting.index"), active: "admin.construction.drafting.*", permissions: ["drafting.manage", "drawing_approval.manage"] },
            { label: "Execution", href: route("admin.construction.execution.index"), active: "admin.construction.execution.*", permissions: ["execution.manage", "execution_task.manage", "dpr.review", "attendance.review"] },
        ],
    },
};

export default function ConstructionShell({
    title,
    description,
    variant = "super",
    children,
}) {
    const config = variantConfig[variant];
    const Layout = config.layout;
    const permissions = usePage().props.auth?.permissions ?? [];
    const navItems = config.items.filter((item) =>
        !item.permissions || item.permissions.some((permission) => permissions.includes(permission))
    );

    return (
        <Layout>
            <Head title={title} />
            <div className="space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p className="text-sm font-semibold uppercase tracking-[0.2em] text-indigo-600">
                                Construction ERP
                            </p>
                            <h1 className="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">
                                {title}
                            </h1>
                            {description ? (
                                <p className="mt-2 max-w-3xl text-sm text-slate-600 dark:text-slate-300">
                                    {description}
                                </p>
                            ) : null}
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {navItems.map((item) => {
                                const active = route().current(item.active);

                                return (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        className={`rounded-full px-4 py-2 text-sm font-medium transition ${
                                            active
                                                ? "bg-indigo-600 text-white"
                                                : "bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                        }`}
                                    >
                                        {item.label}
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                </div>
                {children}
            </div>
        </Layout>
    );
}
