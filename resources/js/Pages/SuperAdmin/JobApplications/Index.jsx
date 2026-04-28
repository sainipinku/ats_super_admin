import React, { useEffect, useMemo, useState } from "react";
import { Head } from "@inertiajs/react";
import AuthenticatedLayout from "../Layouts/AuthenticatedLayout";
import ConfirmDialog from "../../../Components/ConfirmDialog";
import { useAlerts } from "../../../Components/Alerts";

export default function Index({ auth }) {
    const { successAlert, errorAlert } = useAlerts();

    const [jobs, setJobs] = useState([]);
    const [applicationsPage, setApplicationsPage] = useState(null);
    const [loading, setLoading] = useState(true);
    const [jobsLoading, setJobsLoading] = useState(true);

    const [filters, setFilters] = useState({
        search: "",
        status: "",
        jobId: "",
        dateFrom: "",
        dateTo: "",
        perPage: 15,
        page: 1,
    });

    const [confirmOpen, setConfirmOpen] = useState(false);
    const [confirmAction, setConfirmAction] = useState(null);
    const [confirmApp, setConfirmApp] = useState(null);

    const [previewOpen, setPreviewOpen] = useState(false);
    const [previewUrl, setPreviewUrl] = useState(null);

    const statusOptions = useMemo(
        () => [
            { value: "", label: "All Status" },
            { value: "pending", label: "Pending" },
            { value: "shortlisted", label: "Shortlisted" },
            { value: "waiting_list", label: "Waiting List" },
            { value: "hired", label: "Hired" },
            { value: "not_selected", label: "Not Selected" },
            { value: "rejected", label: "Rejected" },
        ],
        []
    );

    const loadJobs = async () => {
        setJobsLoading(true);
        try {
            const res = await fetch(route("super.job.requests.api.all"), {
                headers: { Accept: "application/json" },
            });
            const data = await res.json().catch(() => null);
            if (!res.ok || !data?.success) {
                setJobs([]);
                return;
            }
            setJobs(Array.isArray(data.data) ? data.data : []);
        } catch (e) {
            setJobs([]);
        } finally {
            setJobsLoading(false);
        }
    };

    const loadApplications = async () => {
        setLoading(true);
        try {
            const params = new URLSearchParams();
            if (filters.search) params.set("search", filters.search);
            if (filters.status) params.set("status", filters.status);
            if (filters.jobId) params.set("job_id", filters.jobId);
            if (filters.dateFrom) params.set("date_from", filters.dateFrom);
            if (filters.dateTo) params.set("date_to", filters.dateTo);
            params.set("per_page", String(filters.perPage));
            params.set("page", String(filters.page));

            const res = await fetch(
                `${route("super.job.applications.api.list")}?${params.toString()}`,
                { headers: { Accept: "application/json" } }
            );
            const data = await res.json().catch(() => null);
            if (!res.ok || !data?.success) {
                setApplicationsPage(null);
                errorAlert(data?.message || "Failed to load applications.");
                return;
            }
            setApplicationsPage(data.data);
        } catch (e) {
            setApplicationsPage(null);
            errorAlert("Failed to load applications.");
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadJobs();
    }, []);

    useEffect(() => {
        loadApplications();
    }, [filters.page, filters.perPage]);

    const applyFilters = () => {
        setFilters((prev) => ({ ...prev, page: 1 }));
        setTimeout(loadApplications, 0);
    };

    const clearFilters = () => {
        setFilters({
            search: "",
            status: "",
            jobId: "",
            dateFrom: "",
            dateTo: "",
            perPage: 15,
            page: 1,
        });
        setTimeout(loadApplications, 0);
    };

    const openDecision = (app, action) => {
        setConfirmApp(app);
        setConfirmAction(action);
        setConfirmOpen(true);
    };

    const confirmDecision = async () => {
        if (!confirmApp || !confirmAction) return;
        try {
            const res = await fetch(
                route("super.job.requests.api.applications.decision", confirmApp.id),
                {
                    method: "PATCH",
                    headers: {
                        Accept: "application/json",
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute("content"),
                    },
                    body: JSON.stringify({ action: confirmAction }),
                }
            );
            const data = await res.json().catch(() => null);
            if (!res.ok || !data?.success) {
                errorAlert(data?.message || "Failed to update application.");
                return;
            }

            setApplicationsPage((prev) => {
                if (!prev?.data) return prev;
                return {
                    ...prev,
                    data: prev.data.map((a) =>
                        a.id === confirmApp.id ? { ...a, status: data.data.status } : a
                    ),
                };
            });
            successAlert("Application updated successfully!");
        } catch (e) {
            errorAlert("Failed to update application.");
        } finally {
            setConfirmOpen(false);
            setConfirmAction(null);
            setConfirmApp(null);
        }
    };

    const [updatingStatusId, setUpdatingStatusId] = useState(null);

    const handleStatusChangeDirectly = async (app, newStatus) => {
        if (app.status === newStatus) return;
        setUpdatingStatusId(app.id);
        try {
            const res = await fetch(
                route("super.api.job.applicants.status", app.id),
                {
                    method: "PATCH",
                    headers: {
                        Accept: "application/json",
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute("content"),
                    },
                    body: JSON.stringify({ status: newStatus }),
                }
            );
            const data = await res.json().catch(() => null);
            if (!res.ok || !data?.success) {
                errorAlert(data?.message || "Failed to update status.");
                return;
            }

            setApplicationsPage((prev) => {
                if (!prev?.data) return prev;
                return {
                    ...prev,
                    data: prev.data.map((a) =>
                        a.id === app.id ? { ...a, status: newStatus } : a
                    ),
                };
            });
            successAlert("Status updated successfully!");
        } catch (e) {
            errorAlert("Failed to update status.");
        } finally {
            setUpdatingStatusId(null);
        }
    };

    const isPreviewable = (url) => {
        const u = String(url || "");
        const lower = u.toLowerCase();
        return lower.endsWith(".pdf") || lower.endsWith(".html") || lower.includes("generated-resumes");
    };

    const openResume = (url) => {
        if (!url) return;
        if (isPreviewable(url)) {
            setPreviewUrl(String(url).startsWith("/") ? url : `/${url}`);
            setPreviewOpen(true);
        } else {
            window.open(String(url).startsWith("/") ? url : `/${url}`, "_blank");
        }
    };

    const apps = Array.isArray(applicationsPage?.data) ? applicationsPage.data : [];

    return (
        <AuthenticatedLayout user={auth?.user}>
            <Head title="Job Applicants" />

            <div className="min-h-screen bg-slate-100 p-4 sm:p-6">
                <div className="max-w-7xl mx-auto">
                    <div className="mb-6">
                        <h1 className="text-2xl sm:text-3xl font-bold text-slate-800">
                            Total Job Applicants
                        </h1>
                        <p className="text-slate-500 mt-1">
                            View and filter job applications
                        </p>
                    </div>

                    <div className="bg-white rounded-2xl border border-slate-200 p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
                            <div className="md:col-span-2">
                                <input
                                    value={filters.search}
                                    onChange={(e) =>
                                        setFilters((prev) => ({
                                            ...prev,
                                            search: e.target.value,
                                        }))
                                    }
                                    placeholder="Search candidate, email, job..."
                                    className="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                />
                            </div>
                            <div>
                                <select
                                    value={filters.status}
                                    onChange={(e) =>
                                        setFilters((prev) => ({
                                            ...prev,
                                            status: e.target.value,
                                        }))
                                    }
                                    className="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                >
                                    {statusOptions.map((opt) => (
                                        <option key={opt.value} value={opt.value}>
                                            {opt.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="md:col-span-2">
                                <select
                                    value={filters.jobId}
                                    onChange={(e) =>
                                        setFilters((prev) => ({
                                            ...prev,
                                            jobId: e.target.value,
                                        }))
                                    }
                                    className="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    disabled={jobsLoading}
                                >
                                    <option value="">
                                        {jobsLoading ? "Loading jobs..." : "All Jobs"}
                                    </option>
                                    {jobs.map((j) => (
                                        <option key={j.id} value={j.id}>
                                            {j.title} — {j.company}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="mt-3 flex flex-wrap items-center gap-2">
                            <div className="flex items-center gap-2 flex-1">
                                <input
                                    type="date"
                                    value={filters.dateFrom}
                                    onChange={(e) =>
                                        setFilters((prev) => ({
                                            ...prev,
                                            dateFrom: e.target.value,
                                        }))
                                    }
                                    className="px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                />
                                <span className="text-slate-400">to</span>
                                <input
                                    type="date"
                                    value={filters.dateTo}
                                    onChange={(e) =>
                                        setFilters((prev) => ({
                                            ...prev,
                                            dateTo: e.target.value,
                                        }))
                                    }
                                    className="px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                                />
                            </div>
                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    onClick={clearFilters}
                                    className="px-3 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 text-sm"
                                >
                                    Clear
                                </button>
                                <button
                                    type="button"
                                    onClick={applyFilters}
                                    className="px-3 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 text-sm"
                                >
                                    Apply Filters
                                </button>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                        {loading ? (
                            <div className="py-12 flex items-center justify-center">
                                <div className="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-600" />
                            </div>
                        ) : apps.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm">
                                    <thead className="bg-slate-50">
                                        <tr className="text-left text-slate-600 whitespace-nowrap">
                                            <th className="px-5 py-3 font-medium">
                                                Candidate
                                            </th>
                                            <th className="px-5 py-3 font-medium">
                                                Job
                                            </th>
                                            <th className="px-5 py-3 font-medium">
                                                Status
                                            </th>
                                            <th className="px-5 py-3 font-medium">
                                                Applied
                                            </th>
                                            <th className="px-5 py-3 font-medium">
                                                Resume
                                            </th>
                                            <th className="px-5 py-3 font-medium">
                                                Action
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {apps.map((app) => (
                                            <tr key={app.id} className="align-top">
                                                <td className="px-5 py-3">
                                                    <div className="font-semibold text-slate-900">
                                                        {app.candidate_name || "-"}
                                                    </div>
                                                    <div className="text-slate-500">
                                                        {app.candidate_email || "-"}
                                                    </div>
                                                    <div className="text-slate-500">
                                                        {app.candidate_phone || "-"}
                                                    </div>
                                                </td>
                                                <td className="px-5 py-3">
                                                    <div className="font-medium text-slate-900">
                                                        {app.job?.title || "-"}
                                                    </div>
                                                    <div className="text-slate-500">
                                                        {app.job?.company || "-"}
                                                    </div>
                                                </td>
                                                <td className="px-5 py-3">
                                                    <div className="relative">
                                                        {updatingStatusId === app.id ? (
                                                            <div className="flex items-center gap-2">
                                                                <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
                                                                <span className="text-xs text-slate-500">Updating...</span>
                                                            </div>
                                                        ) : (
                                                            <select
                                                                value={app.status}
                                                                onChange={(e) => handleStatusChangeDirectly(app, e.target.value)}
                                                                className={`w-full px-2 py-1.5 text-xs font-medium rounded-full border cursor-pointer outline-none ${
                                                                    app.status === 'pending' ? 'bg-yellow-100 text-yellow-800 border-yellow-300' :
                                                                    app.status === 'shortlisted' ? 'bg-green-100 text-green-800 border-green-300' :
                                                                    app.status === 'waiting_list' ? 'bg-blue-100 text-blue-800 border-blue-300' :
                                                                    app.status === 'hired' ? 'bg-purple-100 text-purple-800 border-purple-300' :
                                                                    app.status === 'not_selected' ? 'bg-orange-100 text-orange-800 border-orange-300' :
                                                                    app.status === 'rejected' ? 'bg-red-100 text-red-800 border-red-300' :
                                                                    'bg-gray-100 text-gray-800 border-gray-300'
                                                                }`}
                                                            >
                                                                {statusOptions.filter(opt => opt.value !== '').map(opt => (
                                                                    <option key={opt.value} value={opt.value}>
                                                                        {opt.label}
                                                                    </option>
                                                                ))}
                                                            </select>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-5 py-3">
                                                    {app.created_at
                                                        ? new Date(
                                                              app.created_at
                                                          ).toLocaleString(
                                                              "en-US"
                                                          )
                                                        : "-"}
                                                </td>
                                                <td className="px-5 py-3">
                                                    {app.resume_url ? (
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                openResume(
                                                                    app.resume_url
                                                                )
                                                            }
                                                            className="px-2 py-1 rounded text-blue-600 hover:bg-blue-50 font-medium text-sm"
                                                        >
                                                            Preview
                                                        </button>
                                                    ) : (
                                                        <span className="text-slate-400">
                                                            —
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-5 py-3">
                                                    {app.status === "pending" ? (
                                                        <div className="flex items-center gap-2">
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    openDecision(
                                                                        app,
                                                                        "approve"
                                                                    )
                                                                }
                                                                className="px-2 py-1 rounded bg-green-600 text-white text-xs font-semibold hover:bg-green-700"
                                                            >
                                                                Shortlist
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    openDecision(
                                                                        app,
                                                                        "reject"
                                                                    )
                                                                }
                                                                className="px-2 py-1 rounded bg-red-600 text-white text-xs font-semibold hover:bg-red-700"
                                                            >
                                                                Reject
                                                            </button>
                                                        </div>
                                                    ) : (
                                                        <span className="text-slate-400">
                                                            —
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <div className="py-12 text-center text-slate-500">
                                No applications found.
                            </div>
                        )}
                    </div>

                    {applicationsPage && (
                        <div className="mt-5 flex items-center justify-between text-sm text-slate-600">
                            <div>
                                Page {applicationsPage.current_page} of{" "}
                                {applicationsPage.last_page}
                            </div>
                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    disabled={!applicationsPage.prev_page_url}
                                    onClick={() =>
                                        setFilters((prev) => ({
                                            ...prev,
                                            page: Math.max(1, prev.page - 1),
                                        }))
                                    }
                                    className="px-2 py-1 rounded border border-slate-300 disabled:opacity-50 text-sm"
                                >
                                    Prev
                                </button>
                                <button
                                    type="button"
                                    disabled={!applicationsPage.next_page_url}
                                    onClick={() =>
                                        setFilters((prev) => ({
                                            ...prev,
                                            page: prev.page + 1,
                                        }))
                                    }
                                    className="px-2 py-1 rounded border border-slate-300 disabled:opacity-50 text-sm"
                                >
                                    Next
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <ConfirmDialog
                isOpen={confirmOpen}
                onClose={() => {
                    setConfirmOpen(false);
                    setConfirmAction(null);
                    setConfirmApp(null);
                }}
                onConfirm={confirmDecision}
                message={
                    confirmApp
                        ? `${confirmAction === "approve" ? "Approve" : "Reject"} application of "${confirmApp.candidate_name}"?`
                        : "Are you sure?"
                }
                confirmText={
                    confirmAction === "approve" ? "Yes, Approve" : "Yes, Reject"
                }
                cancelText="Cancel"
                modalSpinnerMessage="Processing Please Wait...."
            />

            {previewOpen && previewUrl && (
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                    <div className="bg-white rounded-2xl shadow-xl max-w-5xl w-full overflow-hidden">
                        <div className="px-5 py-3 border-b border-slate-200 flex items-center justify-between">
                            <div className="font-semibold text-slate-900">
                                Resume Preview
                            </div>
                            <button
                                type="button"
                                onClick={() => {
                                    setPreviewOpen(false);
                                    setPreviewUrl(null);
                                }}
                                className="p-2 rounded-full hover:bg-slate-100"
                            >
                                <svg
                                    className="w-5 h-5 text-slate-600"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                        <div className="h-[75vh]">
                            <iframe
                                src={previewUrl}
                                title="Resume Preview"
                                className="w-full h-full"
                            />
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}

