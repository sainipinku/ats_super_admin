import { router } from "@inertiajs/react";

export default function ProjectSwitcher({ projects, activeProject, activeRole }) {
    if (!projects?.length) return null;

    const handleChange = (e) => {
        const projectId = e.target.value;
        const params = new URLSearchParams(window.location.search);

        if (projectId) {
            params.set("project", projectId);
        } else {
            params.delete("project");
        }

        // Clear role context when switching projects; it may not be valid.
        params.delete("role");

        if (activeRole) {
            params.set("role", activeRole.slug);
        }

        const query = params.toString();
        router.get(window.location.pathname + (query ? `?${query}` : ""), {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <div className="flex items-center gap-2">
            <label htmlFor="project-switcher" className="text-sm font-medium text-slate-600 dark:text-slate-300">
                Project
            </label>
            <select
                id="project-switcher"
                value={activeProject?.id ?? ""}
                onChange={handleChange}
                className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
            >
                <option value="">Select project</option>
                {projects.map((project) => (
                    <option key={project.id} value={project.id}>
                        {project.name}
                    </option>
                ))}
            </select>
        </div>
    );
}