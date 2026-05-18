import React, { useEffect, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { route } from 'ziggy-js';
import AuthenticatedLayout from '../Layouts/AuthenticatedLayout';
import { useAlerts } from '../../../Components/Alerts';

const STATUS_META = {
    pending: { label: 'Pending', badge: 'bg-yellow-100 text-yellow-800 border-yellow-200' },
    assigned_to_calling_team: { label: 'Assigned To Calling Team', badge: 'bg-indigo-100 text-indigo-800 border-indigo-200' },
    interested: { label: 'Interested', badge: 'bg-emerald-100 text-emerald-800 border-emerald-200' },
    interview_scheduled: { label: 'Interview Scheduled', badge: 'bg-sky-100 text-sky-800 border-sky-200' },
    selected: { label: 'Selected', badge: 'bg-green-100 text-green-800 border-green-200' },
    on_hold: { label: 'On Hold', badge: 'bg-orange-100 text-orange-800 border-orange-200' },
    on_hold_not_interested: { label: 'On Hold (Not Interested)', badge: 'bg-red-100 text-red-800 border-red-200' },
    shortlisted: { label: 'Shortlisted', badge: 'bg-teal-100 text-teal-800 border-teal-200' },
    waiting_list: { label: 'Waiting List', badge: 'bg-blue-100 text-blue-800 border-blue-200' },
    hired: { label: 'Hired', badge: 'bg-purple-100 text-purple-800 border-purple-200' },
    not_selected: { label: 'Not Selected', badge: 'bg-amber-100 text-amber-800 border-amber-200' },
    rejected: { label: 'Rejected', badge: 'bg-rose-100 text-rose-800 border-rose-200' },
};

const FILTER_ORDER = [
    { key: '', label: 'All', countKey: 'total', color: 'gray' },
    { key: 'pending', label: 'Pending', countKey: 'pending', color: 'yellow' },
    { key: 'assigned_to_calling_team', label: 'Assigned', countKey: 'assigned_to_calling_team', color: 'indigo' },
    { key: 'interested', label: 'Interested', countKey: 'interested', color: 'green' },
    { key: 'interview_scheduled', label: 'Interview', countKey: 'interview_scheduled', color: 'sky' },
    { key: 'selected', label: 'Selected', countKey: 'selected', color: 'emerald' },
    { key: 'on_hold_not_interested', label: 'Not Interested', countKey: 'on_hold_not_interested', color: 'red' },
    { key: 'on_hold', label: 'On Hold', countKey: 'on_hold', color: 'orange' },
];

const filterColorClass = {
    gray: 'bg-slate-100 text-slate-700 border-slate-200',
    yellow: 'bg-yellow-100 text-yellow-800 border-yellow-200',
    indigo: 'bg-indigo-100 text-indigo-800 border-indigo-200',
    green: 'bg-emerald-100 text-emerald-800 border-emerald-200',
    sky: 'bg-sky-100 text-sky-800 border-sky-200',
    emerald: 'bg-green-100 text-green-800 border-green-200',
    orange: 'bg-orange-100 text-orange-800 border-orange-200',
    red: 'bg-red-100 text-red-800 border-red-200',
};

function StatusBadge({ status }) {
    const meta = STATUS_META[status] || { label: status, badge: 'bg-slate-100 text-slate-700 border-slate-200' };

    return (
        <span className={`inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium ${meta.badge}`}>
            {meta.label}
        </span>
    );
}

function FilterChip({ active, label, count, color, onClick }) {
    return (
        <button
            onClick={onClick}
            className={`inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm font-medium transition ${
                active
                    ? `${filterColorClass[color]} ring-2 ring-blue-200`
                    : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'
            }`}
        >
            <span>{label}</span>
            <span className={`rounded-full px-2 py-0.5 text-xs ${active ? 'bg-white/60' : 'bg-slate-100 text-slate-600'}`}>
                {count}
            </span>
        </button>
    );
}

function ApplicantCard({ application, onOpen }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md">
            <div className="p-5 space-y-4">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h3 className="text-lg font-semibold text-slate-900">{application.candidate_name}</h3>
                        <p className="text-sm text-slate-500">{application.candidate_email}</p>
                        <p className="text-xs text-slate-400 mt-1">{application.candidate_phone || 'Phone not provided'}</p>
                    </div>
                    <StatusBadge status={application.status} />
                </div>

                <div className="rounded-xl bg-slate-50 p-3">
                    <p className="text-xs uppercase tracking-wide text-slate-500">Job</p>
                    <p className="mt-1 text-sm font-semibold text-slate-800">{application.job?.title}</p>
                    <p className="text-xs text-slate-500">{application.job?.company}</p>
                </div>

                <div className="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <p className="text-xs uppercase tracking-wide text-slate-500">Calling Team</p>
                        <p className="mt-1 text-slate-700">{application.assigned_calling_team_member?.name || 'Not assigned'}</p>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-wide text-slate-500">Interview</p>
                        <p className="mt-1 text-slate-700">
                            {application.interview_date_time
                                ? new Date(application.interview_date_time).toLocaleString('en-IN')
                                : 'Not scheduled'}
                        </p>
                    </div>
                </div>
            </div>

            <div className="flex items-center justify-between border-t border-slate-100 bg-slate-50 px-5 py-4">
                <p className="text-xs text-slate-500">
                    Applied {new Date(application.created_at).toLocaleDateString('en-IN')}
                </p>
                <button
                    onClick={() => onOpen(application)}
                    className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                >
                    View Details
                </button>
            </div>
        </div>
    );
}

export default function JobApplicants({ auth, callingTeamMembers: initialCallingTeamMembers = [] }) {
    const [applications, setApplications] = useState([]);
    const [jobs, setJobs] = useState([]);
    const [callingTeamMembers, setCallingTeamMembers] = useState(initialCallingTeamMembers);
    const [statusCounts, setStatusCounts] = useState({ total: 0 });
    const [loading, setLoading] = useState(true);
    const [selectedStatus, setSelectedStatus] = useState('');
    const [selectedJob, setSelectedJob] = useState('');
    const [selectedCallingTeamMember, setSelectedCallingTeamMember] = useState('');
    const [search, setSearch] = useState('');
    const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });
    const [selectedApplication, setSelectedApplication] = useState(null);
    const [showModal, setShowModal] = useState(false);
    const [adminNotes, setAdminNotes] = useState('');
    const [assignMemberId, setAssignMemberId] = useState('');
    const [saving, setSaving] = useState(false);

    const { successAlert, errorAlert } = useAlerts();

    const filters = useMemo(
        () => FILTER_ORDER.map((item) => ({ ...item, count: statusCounts[item.countKey] || 0 })),
        [statusCounts]
    );

    const fetchApplicants = async (page = 1, customSearch = search) => {
        setLoading(true);
        try {
            const response = await axios.get(route('admin.api.job.applicants.list'), {
                params: {
                    page,
                    ...(selectedStatus ? { status: selectedStatus } : {}),
                    ...(selectedJob ? { job_id: selectedJob } : {}),
                    ...(selectedCallingTeamMember ? { calling_team_member_id: selectedCallingTeamMember } : {}),
                    ...(customSearch.trim().length >= 2 ? { search: customSearch.trim() } : {}),
                },
            });

            if (response.data.success) {
                setApplications(response.data.data.data || []);
                setJobs(response.data.jobs || []);
                setCallingTeamMembers(response.data.callingTeamMembers || []);
                setStatusCounts(response.data.statusCounts || { total: 0 });
                setPagination({
                    current_page: response.data.data.current_page,
                    last_page: response.data.data.last_page,
                    total: response.data.data.total,
                });
            }
        } catch (error) {
            errorAlert('Failed to load applicants');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchApplicants(1);
    }, [selectedStatus, selectedJob, selectedCallingTeamMember]);

    useEffect(() => {
        const timeout = setTimeout(() => {
            fetchApplicants(1, search);
        }, 400);

        return () => clearTimeout(timeout);
    }, [search]);

    const openDetails = async (application) => {
        try {
            const response = await axios.get(route('admin.api.job.applicants.details', application.id));
            if (response.data.success) {
                const payload = response.data.data;
                setSelectedApplication(payload);
                setAdminNotes(payload.admin_notes || '');
                setAssignMemberId(payload.assigned_calling_team_member_id ? String(payload.assigned_calling_team_member_id) : '');
                setShowModal(true);
            }
        } catch (error) {
            errorAlert('Failed to load applicant details');
        }
    };

    const refreshCurrentPage = async () => {
        await fetchApplicants(pagination.current_page || 1);
    };

    const updateManualStatus = async (status) => {
        if (!selectedApplication) return;

        setSaving(true);
        try {
            const response = await axios.patch(route('admin.api.job.applicants.status', selectedApplication.id), {
                status,
                admin_notes: adminNotes,
            });

            if (response.data.success) {
                successAlert('Application updated successfully');
                setSelectedApplication(response.data.data);
                await refreshCurrentPage();
            }
        } catch (error) {
            errorAlert(error?.response?.data?.message || 'Failed to update application');
        } finally {
            setSaving(false);
        }
    };

    const assignToCallingTeam = async () => {
        if (!selectedApplication || !assignMemberId) {
            errorAlert('Select a calling team member first');
            return;
        }

        setSaving(true);
        try {
            const response = await axios.patch(route('admin.api.job.applicants.assign-calling-team', selectedApplication.id), {
                calling_team_member_id: assignMemberId,
                admin_notes: adminNotes,
            });

            if (response.data.success) {
                successAlert('Candidate assigned to calling team');
                setSelectedApplication(response.data.data);
                await refreshCurrentPage();
            }
        } catch (error) {
            errorAlert(error?.response?.data?.message || 'Failed to assign candidate');
        } finally {
            setSaving(false);
        }
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-slate-800">Job Applicants</h2>}>
            <Head title="Job Applicants" />

            <div className="min-h-screen bg-slate-100 py-6 dark:bg-gray-900">
                <div className="mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    <div className="flex flex-wrap gap-3">
                        {filters.map((item) => (
                            <FilterChip
                                key={item.label}
                                label={item.label}
                                count={item.count}
                                color={item.color}
                                active={selectedStatus === item.key}
                                onClick={() => setSelectedStatus(item.key)}
                            />
                        ))}
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div className="grid grid-cols-1 gap-4 lg:grid-cols-4">
                            <input
                                type="text"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Search candidate, email, phone, job..."
                                className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm outline-none focus:border-blue-500"
                            />

                            <select
                                value={selectedJob}
                                onChange={(event) => setSelectedJob(event.target.value)}
                                className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm outline-none focus:border-blue-500"
                            >
                                <option value="">All jobs</option>
                                {jobs.map((job) => (
                                    <option key={job.id} value={job.id}>{job.title} - {job.company}</option>
                                ))}
                            </select>

                            <select
                                value={selectedCallingTeamMember}
                                onChange={(event) => setSelectedCallingTeamMember(event.target.value)}
                                className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm outline-none focus:border-blue-500"
                            >
                                <option value="">All calling team members</option>
                                {callingTeamMembers.map((member) => (
                                    <option key={member.id} value={member.id}>{member.name}</option>
                                ))}
                            </select>

                            <button
                                onClick={() => {
                                    setSelectedStatus('');
                                    setSelectedJob('');
                                    setSelectedCallingTeamMember('');
                                    setSearch('');
                                }}
                                className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                Reset Filters
                            </button>
                        </div>
                    </div>

                    {loading ? (
                        <div className="flex items-center justify-center rounded-2xl border border-slate-200 bg-white py-20">
                            <div className="h-12 w-12 animate-spin rounded-full border-b-2 border-blue-600" />
                        </div>
                    ) : applications.length === 0 ? (
                        <div className="rounded-2xl border border-slate-200 bg-white py-16 text-center">
                            <h3 className="text-lg font-semibold text-slate-900">No applicants found</h3>
                            <p className="mt-2 text-sm text-slate-500">Try changing filters or assign new candidates to the calling team.</p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                            {applications.map((application) => (
                                <ApplicantCard key={application.id} application={application} onOpen={openDetails} />
                            ))}
                        </div>
                    )}

                    {!loading && pagination.last_page > 1 && (
                        <div className="flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3">
                            <p className="text-sm text-slate-600">
                                Showing {((pagination.current_page - 1) * 12) + 1} - {Math.min(pagination.current_page * 12, pagination.total)} of {pagination.total}
                            </p>
                            <div className="flex gap-2">
                                <button
                                    onClick={() => fetchApplicants(pagination.current_page - 1)}
                                    disabled={pagination.current_page === 1}
                                    className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    Previous
                                </button>
                                <button
                                    onClick={() => fetchApplicants(pagination.current_page + 1)}
                                    disabled={pagination.current_page === pagination.last_page}
                                    className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    Next
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {showModal && selectedApplication && (
                <div className="fixed inset-0 z-50 overflow-y-auto bg-black/50 px-4 py-10">
                    <div className="mx-auto max-w-4xl rounded-3xl bg-white shadow-xl">
                        <div className="flex items-start justify-between border-b border-slate-200 px-6 py-5">
                            <div>
                                <h3 className="text-xl font-semibold text-slate-900">{selectedApplication.candidate_name}</h3>
                                <p className="text-sm text-slate-500">{selectedApplication.candidate_email}</p>
                            </div>
                            <button
                                onClick={() => setShowModal(false)}
                                className="rounded-full border border-slate-200 px-3 py-1 text-sm text-slate-600 hover:bg-slate-50"
                            >
                                Close
                            </button>
                        </div>

                        <div className="grid gap-6 px-6 py-6 lg:grid-cols-3">
                            <div className="space-y-5 lg:col-span-2">
                                <div className="rounded-2xl bg-slate-50 p-4">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p className="text-xs uppercase tracking-wide text-slate-500">Application Status</p>
                                            <div className="mt-2"><StatusBadge status={selectedApplication.status} /></div>
                                        </div>
                                        <div className="text-sm text-slate-600">
                                            Applied on {new Date(selectedApplication.created_at).toLocaleString('en-IN')}
                                        </div>
                                    </div>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="rounded-2xl border border-slate-200 p-4">
                                        <p className="text-xs uppercase tracking-wide text-slate-500">Job</p>
                                        <p className="mt-2 text-base font-semibold text-slate-900">{selectedApplication.job?.title}</p>
                                        <p className="text-sm text-slate-500">{selectedApplication.job?.company}</p>
                                        <p className="text-sm text-slate-500">{selectedApplication.job?.location}</p>
                                    </div>
                                    <div className="rounded-2xl border border-slate-200 p-4">
                                        <p className="text-xs uppercase tracking-wide text-slate-500">Candidate Contact</p>
                                        <p className="mt-2 text-sm text-slate-800">{selectedApplication.candidate_phone || 'Phone not provided'}</p>
                                        <p className="text-sm text-slate-500">{selectedApplication.candidate_email}</p>
                                    </div>
                                </div>

                                {(selectedApplication.call_outcome || selectedApplication.call_outcome_reason || selectedApplication.call_notes) && (
                                    <div className="rounded-2xl border border-slate-200 p-4">
                                        <p className="text-xs uppercase tracking-wide text-slate-500">Calling Update</p>
                                        <div className="mt-3 space-y-2 text-sm text-slate-700">
                                            <p><span className="font-semibold">Outcome:</span> {selectedApplication.call_outcome || 'Not updated'}</p>
                                            <p><span className="font-semibold">Reason:</span> {selectedApplication.call_outcome_reason || '—'}</p>
                                            <p><span className="font-semibold">Notes:</span> {selectedApplication.call_notes || '—'}</p>
                                        </div>
                                    </div>
                                )}

                                {selectedApplication.interview_date_time && (
                                    <div className="rounded-2xl border border-slate-200 p-4">
                                        <p className="text-xs uppercase tracking-wide text-slate-500">Interview Schedule</p>
                                        <div className="mt-3 space-y-2 text-sm text-slate-700">
                                            <p><span className="font-semibold">Date & Time:</span> {new Date(selectedApplication.interview_date_time).toLocaleString('en-IN')}</p>
                                            <p><span className="font-semibold">Mode:</span> {selectedApplication.interview_mode || '—'}</p>
                                            <p><span className="font-semibold">Address:</span> {selectedApplication.interview_address || '—'}</p>
                                            <p><span className="font-semibold">Instructions:</span> {selectedApplication.interview_instructions || '—'}</p>
                                            <p><span className="font-semibold">Contact Person:</span> {selectedApplication.interview_contact_person || '—'}</p>
                                        </div>
                                    </div>
                                )}

                                {selectedApplication.cover_letter && (
                                    <div className="rounded-2xl border border-slate-200 p-4">
                                        <p className="text-xs uppercase tracking-wide text-slate-500">Cover Letter</p>
                                        <p className="mt-3 whitespace-pre-wrap text-sm text-slate-700">{selectedApplication.cover_letter}</p>
                                    </div>
                                )}
                            </div>

                            <div className="space-y-5">
                                <div className="rounded-2xl border border-slate-200 p-4">
                                    <p className="text-xs uppercase tracking-wide text-slate-500">Assign Calling Team</p>
                                    <select
                                        value={assignMemberId}
                                        onChange={(event) => setAssignMemberId(event.target.value)}
                                        className="mt-3 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm outline-none focus:border-blue-500"
                                    >
                                        <option value="">Select member</option>
                                        {callingTeamMembers.map((member) => (
                                            <option key={member.id} value={member.id}>{member.name}</option>
                                        ))}
                                    </select>
                                    <button
                                        onClick={assignToCallingTeam}
                                        disabled={saving}
                                        className="mt-3 w-full rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60"
                                    >
                                        Assign To Calling Team
                                    </button>
                                    {selectedApplication.assigned_calling_team_member && (
                                        <p className="mt-3 text-sm text-slate-600">
                                            Assigned to <span className="font-medium text-slate-900">{selectedApplication.assigned_calling_team_member.name}</span>
                                        </p>
                                    )}
                                </div>

                                <div className="rounded-2xl border border-slate-200 p-4">
                                    <p className="text-xs uppercase tracking-wide text-slate-500">Admin Notes</p>
                                    <textarea
                                        value={adminNotes}
                                        onChange={(event) => setAdminNotes(event.target.value)}
                                        rows={5}
                                        placeholder="Notes for admin or calling team"
                                        className="mt-3 w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-blue-500"
                                    />
                                </div>

                                <div className="rounded-2xl border border-slate-200 p-4">
                                    <p className="text-xs uppercase tracking-wide text-slate-500">Quick Admin Override</p>
                                    <div className="mt-3 grid grid-cols-2 gap-2">
                                        {['pending', 'shortlisted', 'selected', 'on_hold', 'rejected'].map((status) => (
                                            <button
                                                key={status}
                                                onClick={() => updateManualStatus(status)}
                                                disabled={saving || selectedApplication.status === status}
                                                className="rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                                            >
                                                {STATUS_META[status]?.label || status}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
