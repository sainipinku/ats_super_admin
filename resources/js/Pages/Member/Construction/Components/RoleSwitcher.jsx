import { router } from "@inertiajs/react";

export default function RoleSwitcher({ available_roles, activeRole, activeProject }) {
    if (!available_roles?.length) return null;

    const handleChange = (e) => {
        const role = e.target.value;
        const params = new URLSearchParams(window.location.search);

        if (role) {
            params.set("role", role);
        } else {
            params.delete("role");
        }

        if (activeProject) {
            params.set("project", activeProject.id);
        }

        const query = params.toString();
        router.get(window.location.pathname + (query ? `?${query}` : ""), {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <div className="flex items-center gap-2">
            <label htmlFor="role-switcher" className="text-sm font-medium text-slate-600 dark:text-slate-300">
                Working As
            </label>
            <select
                id="role-switcher"
                value={activeRole?.slug ?? ""}
                onChange={handleChange}
                className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
            >
                <option value="">Select role</option>
                {available_roles.map((role) => (
                    <option key={role.id} value={role.slug}>
                        {role.name}
                    </option>
                ))}
            </select>
        </div>
    );
}
