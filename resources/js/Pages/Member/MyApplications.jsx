import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from './Layouts/AuthenticatedLayout';

const StatusBadge = ({ status }) => {
    const styles = {
        pending: 'bg-yellow-100 text-yellow-800 border-yellow-200',
        reviewing: 'bg-blue-100 text-blue-800 border-blue-200',
        shortlisted: 'bg-emerald-100 text-emerald-800 border-emerald-200',
        rejected: 'bg-red-100 text-red-800 border-red-200',
        hired: 'bg-purple-100 text-purple-800 border-purple-200',
    };

    const labels = {
        pending: 'Pending Review',
        reviewing: 'Under Review',
        shortlisted: 'Shortlisted',
        rejected: 'Not Selected',
        hired: 'Hired',
    };

    return (
        <span className={`px-3 py-1 rounded-full text-xs font-medium border ${styles[status] || styles.pending}`}>
            {labels[status] || status}
        </span>
    );
};

const ApplicationCard = ({ application, onWithdraw }) => {
    const [showWithdrawConfirm, setShowWithdrawConfirm] = useState(false);
    const [isWithdrawing, setIsWithdrawing] = useState(false);

    const handleWithdraw = async () => {
        setIsWithdrawing(true);
        try {
            const response = await fetch(`/member/applications/${application.id}/withdraw`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    'Accept': 'application/json',
                },
            });

            const data = await response.json();
            if (data.success) {
                onWithdraw(application.id);
            }
        } catch (error) {
            console.error('Withdraw failed:', error);
        } finally {
            setIsWithdrawing(false);
            setShowWithdrawConfirm(false);
        }
    };

    const canWithdraw = ['pending', 'reviewing'].includes(application.status);

    return (
        <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-6 hover:shadow-md transition-shadow">
            <div className="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                {/* Job Info */}
                <div className="flex-1">
                    <div className="flex items-start gap-4">
                        <div className="w-14 h-14 rounded-xl bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-xl font-bold flex-shrink-0">
                            {application.job?.company?.charAt(0)?.toUpperCase() || 'J'}
                        </div>
                        <div className="flex-1 min-w-0">
                            <h3 className="text-lg font-semibold text-slate-900 line-clamp-1">
                                {application.job?.title}
                            </h3>
                            <p className="text-slate-600 text-sm">{application.job?.company}</p>
                            
                            <div className="flex flex-wrap gap-2 mt-2">
                                <span className="px-2 py-0.5 bg-slate-100 text-slate-600 text-xs rounded-md">
                                    {application.job?.job_type?.replace('-', ' ')?.toUpperCase()}
                                </span>
                                <span className="px-2 py-0.5 bg-slate-100 text-slate-600 text-xs rounded-md">
                                    {application.job?.location}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Application Details */}
                    <div className="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <p className="text-slate-500 mb-1">Applied On</p>
                            <p className="font-medium text-slate-900">
                                {new Date(application.created_at).toLocaleDateString('en-IN', {
                                    day: 'numeric',
                                    month: 'short',
                                    year: 'numeric',
                                })}
                            </p>
                        </div>
                        <div>
                            <p className="text-slate-500 mb-1">Status</p>
                            <StatusBadge status={application.status} />
                        </div>
                        {application.reviewed_at && (
                            <div>
                                <p className="text-slate-500 mb-1">Reviewed On</p>
                                <p className="font-medium text-slate-900">
                                    {new Date(application.reviewed_at).toLocaleDateString('en-IN', {
                                        day: 'numeric',
                                        month: 'short',
                                        year: 'numeric',
                                    })}
                                </p>
                            </div>
                        )}
                        {application.resume_url && (
                            <div>
                                <p className="text-slate-500 mb-1">Resume</p>
                                <a
                                    href={`/${application.resume_url}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="font-medium text-blue-600 hover:text-blue-800 flex items-center gap-1"
                                >
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Download
                                </a>
                            </div>
                        )}
                    </div>

                    {/* Admin Notes */}
                    {application.admin_notes && (
                        <div className="mt-4 p-3 bg-slate-50 rounded-lg border border-slate-200">
                            <p className="text-xs text-slate-500 mb-1">Feedback from Recruiter</p>
                            <p className="text-sm text-slate-700">{application.admin_notes}</p>
                        </div>
                    )}

                    {/* Cover Letter Preview */}
                    {application.cover_letter && (
                        <div className="mt-4">
                            <p className="text-xs text-slate-500 mb-2">Your Cover Letter</p>
                            <p className="text-sm text-slate-600 line-clamp-3">{application.cover_letter}</p>
                        </div>
                    )}
                </div>

                {/* Actions */}
                <div className="flex flex-col gap-2 lg:items-end">
                    {canWithdraw ? (
                        <>
                            {!showWithdrawConfirm ? (
                                <button
                                    onClick={() => setShowWithdrawConfirm(true)}
                                    className="px-4 py-2 text-sm font-medium text-red-600 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-colors"
                                >
                                    Withdraw Application
                                </button>
                            ) : (
                                <div className="flex gap-2">
                                    <button
                                        onClick={() => setShowWithdrawConfirm(false)}
                                        className="px-3 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        onClick={handleWithdraw}
                                        disabled={isWithdrawing}
                                        className="px-3 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50 flex items-center gap-1"
                                    >
                                        {isWithdrawing ? (
                                            <>
                                                <svg className="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                Withdrawing...
                                            </>
                                        ) : (
                                            'Confirm Withdraw'
                                        )}
                                    </button>
                                </div>
                            )}
                        </>
                    ) : (
                        <div className="text-sm text-slate-500">
                            {application.status === 'shortlisted' && (
                                <span className="flex items-center gap-1 text-emerald-600">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Congratulations! You've been shortlisted
                                </span>
                            )}
                            {application.status === 'hired' && (
                                <span className="flex items-center gap-1 text-purple-600">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    You've been hired! Welcome aboard
                                </span>
                            )}
                            {application.status === 'rejected' && (
                                <span className="flex items-center gap-1 text-slate-500">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Keep applying to other opportunities
                                </span>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

const StatCard = ({ count, label, color, icon }) => {
    const colors = {
        yellow: 'bg-yellow-50 border-yellow-200 text-yellow-800',
        blue: 'bg-blue-50 border-blue-200 text-blue-800',
        emerald: 'bg-emerald-50 border-emerald-200 text-emerald-800',
        red: 'bg-red-50 border-red-200 text-red-800',
        purple: 'bg-purple-50 border-purple-200 text-purple-800',
    };

    return (
        <div className={`p-4 rounded-xl border ${colors[color]}`}>
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-2xl font-bold">{count}</p>
                    <p className="text-sm opacity-80">{label}</p>
                </div>
                <div className="text-3xl opacity-50">{icon}</div>
            </div>
        </div>
    );
};

export default function MyApplications({ auth, applications, statusCounts }) {
    const [filter, setFilter] = useState('all');
    const [localApplications, setLocalApplications] = useState(applications.data);

    const handleWithdraw = (applicationId) => {
        setLocalApplications(prev => prev.filter(app => app.id !== applicationId));
    };

    const filteredApplications = filter === 'all'
        ? localApplications
        : localApplications.filter(app => app.status === filter);

    const filters = [
        { key: 'all', label: 'All Applications', count: applications.total },
        { key: 'pending', label: 'Pending', count: statusCounts.pending },
        { key: 'reviewing', label: 'Under Review', count: statusCounts.reviewing },
        { key: 'shortlisted', label: 'Shortlisted', count: statusCounts.shortlisted },
        { key: 'rejected', label: 'Not Selected', count: statusCounts.rejected },
        { key: 'hired', label: 'Hired', count: statusCounts.hired },
    ];

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="My Applications" />

            <div className="py-8 px-4 sm:px-6 lg:px-8">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-3xl pt-10 font-bold text-slate-900 mb-2">My Applications</h1>
                    <p className="text-slate-600">Track and manage your job applications</p>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
                    <StatCard
                        count={statusCounts.pending}
                        label="Pending"
                        color="yellow"
                        // icon="⏳"
                    />
                    <StatCard
                        count={statusCounts.reviewing}
                        label="Reviewing"
                        color="blue"
                        // icon="👀"
                    />
                    <StatCard
                        count={statusCounts.shortlisted}
                        label="Shortlisted"
                        color="emerald"
                        // icon="⭐"
                    />
                    <StatCard
                        count={statusCounts.rejected}
                        label="Not Selected"
                        color="red"
                        // icon="❌"
                    />
                    <StatCard
                        count={statusCounts.hired}
                        label="Hired"
                        color="purple"
                        // icon="🎉"
                    />
                </div>

                {/* Filter Tabs */}
                <div className="flex flex-wrap gap-2 mb-6">
                    {filters.map((f) => (
                        <button
                            key={f.key}
                            onClick={() => setFilter(f.key)}
                            className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                                filter === f.key
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'
                            }`}
                        >
                            {f.label}
                            <span className={`ml-2 px-2 py-0.5 rounded-full text-xs ${
                                filter === f.key ? 'bg-blue-500 text-white' : 'bg-slate-100 text-slate-600'
                            }`}>
                                {f.count}
                            </span>
                        </button>
                    ))}
                </div>

                {/* Applications List */}
                {filteredApplications.length > 0 ? (
                    <div className="space-y-4">
                        {filteredApplications.map((application) => (
                            <ApplicationCard
                                key={application.id}
                                application={application}
                                onWithdraw={handleWithdraw}
                            />
                        ))}
                    </div>
                ) : (
                    <div className="text-center py-16 bg-white rounded-xl border border-slate-200">
                        <div className="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg className="w-10 h-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <h3 className="text-lg font-medium text-slate-900 mb-2">
                            {filter === 'all' ? 'No applications yet' : `No ${filter} applications`}
                        </h3>
                        <p className="text-slate-500 mb-4">
                            {filter === 'all'
                                ? 'Start applying to jobs and track your progress here'
                                : 'Try selecting a different filter'}
                        </p>
                        {filter === 'all' && (
                            <Link
                                href="/member/jobs"
                                className="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors"
                            >
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                Browse Jobs
                            </Link>
                        )}
                    </div>
                )}

                {/* Pagination */}
                {applications.last_page > 1 && (
                    <div className="mt-8 flex justify-center">
                        <div className="flex items-center gap-2">
                            {applications.links.map((link, idx) => (
                                <button
                                    key={idx}
                                    onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                    disabled={!link.url}
                                    className={`px-4 py-2 rounded-lg text-sm font-medium ${
                                        link.active
                                            ? 'bg-blue-600 text-white'
                                            : link.url
                                                ? 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50'
                                                : 'bg-slate-100 text-slate-400 cursor-not-allowed'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
