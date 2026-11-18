import { useMemo, useState } from "react";
import { useForm } from "@inertiajs/react";
import { toast } from "react-hot-toast";
import Modal from "@/Components/Modal";

const surveyorRoles = ["surveyor", "survey", "field_surveyor"];
const draftsmanRoles = ["draft_person", "draftsman", "drafter", "draft_man"];
const assignableSurveyorStatuses = ["completed", "survey_approved", "review"];

function roleMatches(value, roles) {
    const values = Array.isArray(value) ? value : [value];
    return values.some((item) => {
        if (!item) return false;
        const raw = typeof item === "object" ? (item.slug || item.name || item.value || "") : item;
        const role = String(raw || "").toLowerCase().replace(/[\s-]+/g, "_").trim();
        return roles.includes(role) || roles.some((candidate) => role.includes(candidate));
    });
}

function hasActiveDraftsmanAssignment(task) {
    if (String(task.status || "").toLowerCase() === "drafting_in_progress") {
        return true;
    }

    const assignees = [
        ...(Array.isArray(task.assignees) ? task.assignees : []),
        ...(Array.isArray(task.assignments) ? task.assignments : []),
        ...(Array.isArray(task.task_assignments) ? task.task_assignments : []),
    ];

    return assignees.some((assignment) => {
        const status = assignment?.status?.value ?? assignment?.status;
        if (status && String(status).toLowerCase() !== "active") return false;
        return roleMatches(assignment.assignment_role, draftsmanRoles);
    });
}

function hasSurveyorAssignment(task, hasSurveyVisits = false) {
    if (hasSurveyVisits) {
        return true;
    }

    const assignees = [
        ...(Array.isArray(task.assignees) ? task.assignees : []),
        ...(Array.isArray(task.assignments) ? task.assignments : []),
        ...(Array.isArray(task.task_assignments) ? task.task_assignments : []),
    ];

    const matchedAssignee = assignees.some((assignment) => {
        const status = assignment?.status?.value ?? assignment?.status;
        if (status && !["active", "completed"].includes(String(status).toLowerCase())) return false;
        const member = assignment.member || assignment.assignee || {};
        return roleMatches(assignment.assignment_role, surveyorRoles)
            || roleMatches(member.assigned_roles, surveyorRoles)
            || roleMatches(member.role_names, surveyorRoles)
            || roleMatches(member.assigned_role_names, surveyorRoles)
            || roleMatches(member.role_badges, surveyorRoles)
            || roleMatches(member.designation, surveyorRoles);
    });

    if (matchedAssignee) {
        return true;
    }

    const supervisor = task.supervisor || task.assigned_supervisor_member || null;
    if (supervisor) {
        if (
            roleMatches(supervisor.assigned_roles, surveyorRoles)
            || roleMatches(supervisor.role_names, surveyorRoles)
            || roleMatches(supervisor.assigned_role_names, surveyorRoles)
            || roleMatches(supervisor.role_badges, surveyorRoles)
            || roleMatches(supervisor.designation, surveyorRoles)
            || roleMatches(supervisor.designation_text, surveyorRoles)
        ) {
            return true;
        }
    }

    return false;
}

export default function AssignToDraftsmanButton({
    task,
    projectId,
    taskSource,
    routeName,
    draftsmen = [],
    defaultDraftsmanId = "",
    hasSurveyVisits = false,
    authorized = false,
}) {
    const [showModal, setShowModal] = useState(false);
    const [searchQuery, setSearchQuery] = useState("");

    const normalizedDraftsmen = useMemo(() => {
        const seen = new Set();
        const list = [];

        for (const member of draftsmen) {
            const id = String(member.member_id || member.id || "");
            if (!id || seen.has(id)) continue;
            seen.add(id);
            list.push({
                id,
                name: member.name || `Employee #${id}`,
                email: member.email || "",
                role_name: member.role_name || member.role_badge || "Draft Person",
                is_project_member: Boolean(member.is_project_member),
            });
        }

        return list.sort((a, b) => {
            if (a.is_project_member !== b.is_project_member) {
                return a.is_project_member ? -1 : 1;
            }
            return a.name.localeCompare(b.name);
        });
    }, [draftsmen]);

    const resolvedDefaultId = useMemo(() => {
        if (defaultDraftsmanId && normalizedDraftsmen.some((m) => m.id === String(defaultDraftsmanId))) {
            return String(defaultDraftsmanId);
        }
        const projectDefault = normalizedDraftsmen.find((m) => m.is_project_member);
        return projectDefault ? projectDefault.id : "";
    }, [defaultDraftsmanId, normalizedDraftsmen]);

    const form = useForm({
        task_source: taskSource,
        task_id: task.id,
        member_id: resolvedDefaultId,
        due_date: "",
    });

    const filteredDraftsmen = useMemo(() => {
        const q = searchQuery.trim().toLowerCase();
        if (!q) return normalizedDraftsmen;
        return normalizedDraftsmen.filter(
            (m) =>
                m.name.toLowerCase().includes(q)
                || m.email.toLowerCase().includes(q)
                || m.role_name.toLowerCase().includes(q)
        );
    }, [normalizedDraftsmen, searchQuery]);

    const taskStatus = String(task.status || "").toLowerCase();
    const isEligibleStatus = assignableSurveyorStatuses.includes(taskStatus);

    if (
        !authorized
        || !isEligibleStatus
        || hasActiveDraftsmanAssignment(task)
        || !hasSurveyorAssignment(task, hasSurveyVisits)
    ) {
        return null;
    }

    const openModal = () => {
        setSearchQuery("");
        form.setData({
            task_source: taskSource,
            task_id: task.id,
            member_id: resolvedDefaultId,
            due_date: "",
        });
        setShowModal(true);
    };

    const submit = (event) => {
        event.preventDefault();
        if (!form.data.member_id) {
            toast.error("Please select a Draft Person to assign this task.");
            return;
        }

        form.post(route(routeName, projectId), {
            preserveScroll: true,
            onSuccess: () => {
                setShowModal(false);
                form.reset();
                toast.success("Task assigned to Draft Person successfully.");
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                toast.error(Array.isArray(firstError) ? firstError[0] : firstError || "Failed to assign Draft Person.");
            },
        });
    };

    return (
        <>
            <button
                type="button"
                onClick={openModal}
                className="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-violet-600 to-indigo-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xs transition hover:from-violet-500 hover:to-indigo-500"
                title="Assign this completed Surveyor task to a Draft Person"
            >
                <span>📐</span>
                <span>Assign Draft Person</span>
            </button>

            {showModal && (
                <Modal show={true} onClose={() => setShowModal(false)} maxWidth="lg">
                    <form onSubmit={submit} className="p-6">
                        <div className="flex items-start justify-between gap-3 border-b border-slate-200 pb-4 dark:border-slate-800">
                            <div>
                                <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                    Assign to Draft Person
                                </h3>
                                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    {task.task_code || `Task #${task.id}`} • {task.title}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowModal(false)}
                                className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            >
                                ✕
                            </button>
                        </div>

                        {resolvedDefaultId && (
                            <div className="mt-4 rounded-xl border border-indigo-200/80 bg-indigo-50/70 px-3.5 py-2.5 text-xs text-indigo-800 dark:border-indigo-500/30 dark:bg-indigo-950/40 dark:text-indigo-200">
                                Project team Draft Person is selected by default. You can also select any Draft Person from your employee list below.
                            </div>
                        )}

                        <div className="mt-4">
                            <div className="flex items-center justify-between gap-2">
                                <label className="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    Select Draft Person ({normalizedDraftsmen.length}) <span className="text-rose-500">*</span>
                                </label>
                                {normalizedDraftsmen.length > 4 && (
                                    <input
                                        type="text"
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        placeholder="Search employee..."
                                        className="w-44 rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                    />
                                )}
                            </div>

                            {filteredDraftsmen.length > 0 ? (
                                <div className="mt-2.5 max-h-60 space-y-2 overflow-y-auto pr-1">
                                    {filteredDraftsmen.map((member) => {
                                        const isSelected = String(form.data.member_id) === member.id;
                                        const isDefault = member.id === resolvedDefaultId && member.is_project_member;

                                        return (
                                            <label
                                                key={member.id}
                                                className={`flex cursor-pointer items-center justify-between gap-3 rounded-xl border p-3 transition ${
                                                    isSelected
                                                        ? "border-indigo-500 bg-indigo-50/80 ring-1 ring-indigo-500/30 dark:border-indigo-400 dark:bg-indigo-950/50"
                                                        : "border-slate-200 bg-white hover:border-indigo-300 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900/80 dark:hover:border-slate-700"
                                                }`}
                                            >
                                                <div className="flex min-w-0 items-center gap-3">
                                                    <input
                                                        type="radio"
                                                        name="draftsman_member_id"
                                                        value={member.id}
                                                        checked={isSelected}
                                                        onChange={() => form.setData("member_id", member.id)}
                                                        className="h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                    />
                                                    <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-200">
                                                        {(member.name[0] || "D").toUpperCase()}
                                                    </div>
                                                    <div className="min-w-0">
                                                        <p className="truncate text-xs font-bold text-slate-900 dark:text-white">
                                                            {member.name}
                                                        </p>
                                                        {member.email && (
                                                            <p className="truncate text-[11px] text-slate-500 dark:text-slate-400">
                                                                {member.email}
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>

                                                <div className="flex shrink-0 items-center gap-1.5">
                                                    {member.is_project_member ? (
                                                        <span className="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/15 dark:text-emerald-300">
                                                            {isDefault ? "Project Team • Default" : "Project Team"}
                                                        </span>
                                                    ) : (
                                                        <span className="rounded-full border border-slate-200 bg-slate-100 px-2.5 py-0.5 text-[10px] font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                            Company Employee
                                                        </span>
                                                    )}
                                                </div>
                                            </label>
                                        );
                                    })}
                                </div>
                            ) : (
                                <p className="mt-2.5 rounded-xl border border-dashed border-amber-300 bg-amber-50/60 p-3 text-xs text-amber-800 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-300">
                                    {normalizedDraftsmen.length === 0
                                        ? "No Draft Person found in your company employee list. Add an employee with the Draft Person role first."
                                        : "No matching Draft Person found for your search."}
                                </p>
                            )}
                        </div>

                        <label className="mt-4 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Due Date (Optional)
                            <input
                                type="date"
                                value={form.data.due_date}
                                onChange={(event) => form.setData("due_date", event.target.value)}
                                className="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                            />
                        </label>

                        <div className="mt-6 flex justify-end gap-2 border-t border-slate-200 pt-4 dark:border-slate-800">
                            <button
                                type="button"
                                onClick={() => setShowModal(false)}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={form.processing || normalizedDraftsmen.length === 0 || !form.data.member_id}
                                className="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {form.processing ? "Assigning..." : "Assign Draft Person"}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}
        </>
    );
}
