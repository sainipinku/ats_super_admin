import { useEffect, useState } from "react";
import { useForm } from "@inertiajs/react";
import { toast } from "react-hot-toast";
import Modal from "@/Components/Modal";

export default function AssignApprovalAdminButton({
    projectId,
    draftingJob = null,
    latestRevision = null,
    pendingApproval = null,
    companyAdmins = [],
    routeName = "super.construction.projects.drawing-approvals.assign-admin",
    authorized = false,
}) {
    const [open, setOpen] = useState(false);

    const effectiveRevision = latestRevision || (() => {
        const revs = draftingJob?.drawing_revisions || [];
        return revs.length ? revs[revs.length - 1] : null;
    })();

    const effectiveApproval = pendingApproval || (() => {
        const approvals = effectiveRevision?.approvals || [];
        return approvals.find((a) => String(a?.decision || "").toLowerCase() === "pending")
            || (approvals.length ? approvals[approvals.length - 1] : null);
    })();

    const isPending = Boolean(effectiveRevision) && (
        !effectiveApproval ||
        String(effectiveApproval?.decision || "pending").toLowerCase() === "pending"
    ) && String(draftingJob?.status || effectiveRevision?.status || "").toLowerCase() !== "approved";

    const currentAssignedKey = effectiveApproval?.assigned_to_id
        ? (String(effectiveApproval?.assigned_to_type || "").includes("SuperAdmin")
            ? `superadmin:${effectiveApproval.assigned_to_id}`
            : `member:${effectiveApproval.assigned_to_id}`)
        : "";

    const adminsList = Array.isArray(companyAdmins) ? companyAdmins : [];

    const form = useForm({
        assignee_key: currentAssignedKey || (adminsList[0]?.key ? String(adminsList[0].key) : ""),
        drafting_job_id: draftingJob?.id ? Number(draftingJob.id) : "",
        drawing_revision_id: effectiveRevision?.id ? Number(effectiveRevision.id) : "",
        drawing_approval_id: effectiveApproval?.id ? Number(effectiveApproval.id) : "",
    });

    useEffect(() => {
        if (!open) return;
        form.setData({
            assignee_key: currentAssignedKey || (adminsList[0]?.key ? String(adminsList[0].key) : ""),
            drafting_job_id: draftingJob?.id ? Number(draftingJob.id) : "",
            drawing_revision_id: effectiveRevision?.id ? Number(effectiveRevision.id) : "",
            drawing_approval_id: effectiveApproval?.id ? Number(effectiveApproval.id) : "",
        });
        form.clearErrors();
    }, [open, currentAssignedKey, draftingJob?.id, effectiveRevision?.id, effectiveApproval?.id]);

    // Strictly visible only to authorized company Admin / Super Admin with permission once CAD is submitted
    if (!authorized || !isPending) {
        return null;
    }

    const assignedName = effectiveApproval?.assigned_to?.name || null;

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!form.data.assignee_key) {
            toast.error("Please select an Admin or Super Admin.");
            return;
        }
        form.post(route(routeName, projectId), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                toast.success("Admin assigned for CAD approval.");
            },
            onError: (errors) => {
                const firstErr = Object.values(errors || {})[0];
                if (firstErr) toast.error(String(firstErr));
            },
        });
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1.5 text-[11px] font-semibold text-amber-800 shadow-xs transition hover:bg-amber-100 dark:border-amber-700/70 dark:bg-amber-950/50 dark:text-amber-200 dark:hover:bg-amber-900/50"
                title={assignedName ? `Assigned to ${assignedName}` : "Assign an Admin or Super Admin to approve this CAD drawing"}
            >
                <span>{assignedName ? `Assigned: ${assignedName}` : "Assign to Admin"}</span>
            </button>

            <Modal show={open} maxWidth="md" onClose={() => setOpen(false)}>
                <form onSubmit={handleSubmit} className="p-5">
                    <div className="flex items-start justify-between gap-3 border-b border-slate-200 pb-3 dark:border-slate-800">
                        <div>
                            <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                Assign CAD Approval to Admin
                            </h3>
                            <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                Select a company Admin or Super Admin to review and approve this submitted CAD drawing.
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={() => setOpen(false)}
                            className="rounded-lg px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
                        >
                            Close
                        </button>
                    </div>

                    <div className="mt-4 space-y-3">
                        {effectiveRevision?.drawing_no && (
                            <div className="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-700 dark:bg-slate-900 dark:text-slate-300">
                                CAD Drawing:{" "}
                                <strong className="font-semibold text-slate-900 dark:text-white">
                                    {effectiveRevision.drawing_no}
                                </strong>
                                {effectiveRevision.revision_no ? ` (Rev ${effectiveRevision.revision_no})` : ""}
                            </div>
                        )}

                        <div>
                            <label className="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Select Admin / Super Admin
                            </label>
                            <select
                                value={form.data.assignee_key}
                                onChange={(e) => form.setData("assignee_key", e.target.value)}
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:focus:ring-indigo-900"
                                required
                            >
                                <option value="">-- Choose Admin --</option>
                                {adminsList.map((admin) => (
                                    <option key={admin.key} value={admin.key}>
                                        {admin.name} ({admin.role_label || "Admin"})
                                    </option>
                                ))}
                            </select>
                            {form.errors.assignee_key && (
                                <p className="mt-1 text-xs text-rose-600">{form.errors.assignee_key}</p>
                            )}
                            {form.errors.drafting_job_id && (
                                <p className="mt-1 text-xs text-rose-600">{form.errors.drafting_job_id}</p>
                            )}
                            {adminsList.length === 0 && (
                                <p className="mt-1 text-xs text-amber-600">
                                    No active Admin or Super Admin found in this company.
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="mt-5 flex items-center justify-end gap-2 border-t border-slate-200 pt-3 dark:border-slate-800">
                        <button
                            type="button"
                            onClick={() => setOpen(false)}
                            className="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={form.processing || !form.data.assignee_key || adminsList.length === 0}
                            className="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {form.processing ? "Assigning..." : "Assign"}
                        </button>
                    </div>
                </form>
            </Modal>
        </>
    );
}
