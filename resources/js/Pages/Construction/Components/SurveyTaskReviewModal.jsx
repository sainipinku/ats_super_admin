import { useState } from "react";
import { useForm } from "@inertiajs/react";
import { toast } from "react-hot-toast";
import Modal from "@/Components/Modal";

const latestVisit = (task) => {
    const visits = [...(task.survey_visits || task.surveyVisits || [])];
    return visits.sort((a, b) => new Date(b.submitted_at || b.updated_at || b.created_at) - new Date(a.submitted_at || a.updated_at || a.created_at))[0];
};

export default function SurveyTaskReviewModal({ project, task, draftsmen, routeName, taskSource = "execution" }) {
    const [open, setOpen] = useState(false);
    const visit = latestVisit(task);
    const submission = visit?.submission || {};
    const details = submission.submission_details || {};
    const reviewForm = useForm({
        decision: "approved",
        remarks: "",
        transfer_to_member_id: "",
    });

    const submitDecision = (decision) => {
        if (decision === "approved" && !reviewForm.data.transfer_to_member_id) {
            toast.error("Select an active Draft Person before approving this task.");
            return;
        }
        if (decision !== "approved" && !reviewForm.data.remarks.trim()) {
            toast.error("A reason is required for this decision.");
            return;
        }

        reviewForm
            .transform((data) => ({
                ...data,
                decision,
                task_source: taskSource,
                transfer_to_member_id: decision === "approved" ? data.transfer_to_member_id : undefined,
            }))
            .post(route(routeName, [project.id, task.id]), {
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);
                    reviewForm.reset();
                    toast.success(decision === "approved" ? "Survey approved and assigned." : decision === "rejected" ? "Survey submission rejected." : "Revision requested.");
                },
                onError: (errors) => {
                    const first = Object.values(errors)[0];
                    toast.error(Array.isArray(first) ? first[0] : first || "Unable to update the survey review.");
                },
            });
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="rounded-lg bg-sky-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-600"
            >
                Review Submission
            </button>
            {open && (
                <Modal show onClose={() => setOpen(false)} maxWidth="3xl">
                    <div className="space-y-5 p-6">
                        <div>
                            <h3 className="text-lg font-bold text-slate-900 dark:text-white">Survey Task Review</h3>
                            <p className="mt-1 text-sm text-slate-500">
                                {project.name} · {task.task_code || `Task #${task.id}`} · {task.title}
                            </p>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="rounded-lg bg-slate-50 p-3 text-sm dark:bg-slate-800">
                                <span className="block text-xs text-slate-500">Survey Team Member</span>
                                <strong>{visit?.checked_in_by?.name || "Not recorded"}</strong>
                            </div>
                            <div className="rounded-lg bg-slate-50 p-3 text-sm dark:bg-slate-800">
                                <span className="block text-xs text-slate-500">Submitted</span>
                                <strong>{submission.submitted_at || visit?.submitted_at || "Not recorded"}</strong>
                            </div>
                            <div className="rounded-lg bg-slate-50 p-3 text-sm dark:bg-slate-800">
                                <span className="block text-xs text-slate-500">GPS Coordinates</span>
                                <strong>
                                    {visit?.check_in_latitude && visit?.check_in_longitude
                                        ? `${visit.check_in_latitude}, ${visit.check_in_longitude}`
                                        : "Not available"}
                                </strong>
                            </div>
                            <div className="rounded-lg bg-slate-50 p-3 text-sm dark:bg-slate-800">
                                <span className="block text-xs text-slate-500">Execution</span>
                                <strong>
                                    {[details.work_status, details.total_hours].filter(Boolean).join(" · ") || "Details unavailable"}
                                </strong>
                            </div>
                        </div>

                        <div className="max-h-72 space-y-3 overflow-y-auto rounded-lg border border-slate-200 p-4 text-sm dark:border-slate-700">
                            <div>
                                <h4 className="font-semibold">Survey Remarks</h4>
                                <p className="mt-1 whitespace-pre-wrap text-slate-600 dark:text-slate-300">{details.remarks || visit?.remarks || "No remarks submitted."}</p>
                            </div>
                            {details.pending_work && <p><strong>Pending work:</strong> {details.pending_work}</p>}
                            {details.obstruction_details && <p><strong>Obstructions:</strong> {details.obstruction_details}</p>}
                            <div>
                                <h4 className="font-semibold">Completed Checklists</h4>
                                {details.checklists?.length ? (
                                    <ul className="mt-1 list-inside list-disc text-slate-600 dark:text-slate-300">
                                        {details.checklists.map((item) => (
                                            <li key={item.id}>{item.item_title} — {item.is_completed ? "Completed" : "Incomplete"}</li>
                                        ))}
                                    </ul>
                                ) : <p className="mt-1 text-slate-500">No checklist details submitted.</p>}
                            </div>
                            {Object.entries(details.categorized_files || {}).map(([category, files]) => files?.length ? (
                                <div key={category}>
                                    <h4 className="font-semibold">{category.replaceAll("_", " ")}</h4>
                                    <ul className="mt-1 list-inside list-disc">
                                        {files.map((file, index) => (
                                            <li key={`${category}-${index}`}>
                                                <a className="text-sky-700 underline dark:text-sky-300" href={file.url} target="_blank" rel="noreferrer">
                                                    {file.name || file.file_name || "Open attachment"}
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ) : null)}
                            {details.photos?.length > 0 && (
                                <div className="flex flex-wrap gap-2">
                                    {details.photos.map((photo, index) => (
                                        <a key={index} href={photo} target="_blank" rel="noreferrer">
                                            <img src={photo} alt={`Survey attachment ${index + 1}`} className="h-20 w-20 rounded object-cover" />
                                        </a>
                                    ))}
                                </div>
                            )}
                        </div>

                        <label className="block text-sm font-medium text-slate-700 dark:text-slate-200">
                            Assign to Draft Person
                            <select
                                value={reviewForm.data.transfer_to_member_id}
                                onChange={(event) => reviewForm.setData("transfer_to_member_id", event.target.value)}
                                className="mt-1 block w-full rounded-lg border border-slate-300 bg-white p-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                            >
                                <option value="">Select an active Draft Person</option>
                                {draftsmen.map((person) => (
                                    <option key={person.member_id} value={person.member_id}>{person.name}{person.email ? ` · ${person.email}` : ""}</option>
                                ))}
                            </select>
                        </label>

                        <label className="block text-sm font-medium text-slate-700 dark:text-slate-200">
                            Revision / rejection reason (required for those decisions)
                            <textarea
                                rows={3}
                                value={reviewForm.data.remarks}
                                onChange={(event) => reviewForm.setData("remarks", event.target.value)}
                                className="mt-1 block w-full rounded-lg border border-slate-300 bg-white p-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                            />
                        </label>

                        <div className="flex flex-wrap justify-end gap-2">
                            <button type="button" onClick={() => setOpen(false)} className="rounded-lg border px-3 py-2 text-xs">Close</button>
                            <button type="button" disabled={reviewForm.processing} onClick={() => submitDecision("revision_requested")} className="rounded-lg bg-amber-600 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50">Request Revision</button>
                            <button type="button" disabled={reviewForm.processing} onClick={() => submitDecision("rejected")} className="rounded-lg bg-rose-600 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50">Reject</button>
                            <button type="button" disabled={reviewForm.processing || draftsmen.length === 0} onClick={() => submitDecision("approved")} className="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50">Approve & Assign</button>
                        </div>
                    </div>
                </Modal>
            )}
        </>
    );
}
