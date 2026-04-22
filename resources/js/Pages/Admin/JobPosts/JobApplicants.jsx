import React, { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '../Layouts/AuthenticatedLayout';
import { useAlerts } from '../../../Components/Alerts';
import axios from 'axios';
import { route } from 'ziggy-js';

// Applicant Card Component
const ApplicantCard = ({ application, onViewDetails, onStatusChange }) => {
    const getStatusBadge = (status) => {
        const badges = {
            pending: 'bg-yellow-100 text-yellow-800 border-yellow-200',
            reviewing: 'bg-blue-100 text-blue-800 border-blue-200',
            shortlisted: 'bg-green-100 text-green-800 border-green-200',
            rejected: 'bg-red-100 text-red-800 border-red-200',
            hired: 'bg-purple-100 text-purple-800 border-purple-200',
        };
        return badges[status] || 'bg-gray-100 text-gray-800 border-gray-200';
    };

    const formatDate = (dateString) => {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-IN', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    };

    const getInitials = (name) => {
        if (!name) return '?';
        return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
    };

    return (
        <div className="bg-white rounded-2xl shadow-sm border border-slate-200 hover:shadow-lg hover:border-blue-300 transition-all duration-300 overflow-hidden">
            {/* Header with Candidate Info */}
            <div className="p-5">
                <div className="flex items-start gap-4">
                    <div className="w-14 h-14 rounded-xl bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-lg font-bold flex-shrink-0 overflow-hidden">
                        {application.candidate?.image ? (
                            <img
                                src={application.candidate.image}
                                alt={application.candidate_name}
                                className="w-full h-full object-cover"
                                onError={(e) => {
                                    e.target.style.display = 'none';
                                    e.target.parentElement.innerHTML = getInitials(application.candidate_name);
                                }}
                            />
                        ) : (
                            getInitials(application.candidate_name)
                        )}
                    </div>
                    <div className="flex-1 min-w-0">
                        <h3 className="text-lg font-semibold text-slate-900 line-clamp-1 mb-1">
                            {application.candidate_name}
                        </h3>
                        <p className="text-slate-600 text-sm">{application.candidate_email}</p>
                        {application.candidate_phone && (
                            <p className="text-slate-500 text-xs mt-0.5">{application.candidate_phone}</p>
                        )}
                    </div>
                </div>

                {/* Job Info */}
                <div className="mt-4 p-3 bg-slate-50 rounded-xl">
                    <p className="text-xs text-slate-500 mb-1">Applied for:</p>
                    <p className="text-sm font-semibold text-slate-800">{application.job?.title}</p>
                    <p className="text-xs text-slate-600">{application.job?.company}</p>
                </div>

                {/* Skills Preview */}
                {application.candidate_skills && application.candidate_skills.length > 0 && (
                    <div className="mt-3">
                        <p className="text-xs text-slate-500 mb-1">Skills:</p>
                        <div className="flex flex-wrap gap-1">
                            {application.candidate_skills.slice(0, 3).map((skill, idx) => (
                                <span key={idx} className="px-2 py-0.5 bg-blue-50 text-blue-700 text-xs rounded-md border border-blue-100">
                                    {skill}
                                </span>
                            ))}
                            {application.candidate_skills.length > 3 && (
                                <span className="px-2 py-0.5 bg-slate-50 text-slate-500 text-xs rounded-md">
                                    +{application.candidate_skills.length - 3} more
                                </span>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {/* Footer with Status and Actions */}
            <div className="px-5 py-4 bg-slate-50 border-t border-slate-100">
                <div className="flex items-center justify-between">
                    <div className="text-xs text-slate-500">
                        <div className="flex items-center gap-1">
                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Applied: {formatDate(application.created_at)}
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <span className={`px-3 py-1 rounded-full text-xs font-medium border capitalize ${getStatusBadge(application.status)}`}>
                            {application.status}
                        </span>
                        <button
                            onClick={() => onViewDetails(application)}
                            className="px-3 py-1.5 text-sm font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors"
                        >
                            View
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
};

// Status Filter Button
const StatusFilter = ({ label, count, active, onClick, color }) => {
    const colorClasses = {
        yellow: 'bg-yellow-100 text-yellow-800 border-yellow-200',
        blue: 'bg-blue-100 text-blue-800 border-blue-200',
        green: 'bg-green-100 text-green-800 border-green-200',
        red: 'bg-red-100 text-red-800 border-red-200',
        purple: 'bg-purple-100 text-purple-800 border-purple-200',
        gray: 'bg-slate-100 text-slate-700 border-slate-200',
    };

    return (
        <button
            onClick={onClick}
            className={`px-4 py-2 rounded-xl text-sm font-medium border transition-all duration-200 flex items-center gap-2 ${
                active
                    ? colorClasses[color] + ' ring-2 ring-offset-1 ring-blue-300'
                    : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'
            }`}
        >
            <span>{label}</span>
            <span className={`px-2 py-0.5 rounded-full text-xs ${active ? 'bg-white/50' : 'bg-slate-100'}`}>
                {count}
            </span>
        </button>
    );
};

export default function JobApplicants({ auth }) {
    const [applications, setApplications] = useState([]);
    const [jobs, setJobs] = useState([]);
    const [statusCounts, setStatusCounts] = useState({
        pending: 0,
        reviewing: 0,
        shortlisted: 0,
        rejected: 0,
        hired: 0,
        total: 0,
    });
    const [loading, setLoading] = useState(true);
    const [selectedStatus, setSelectedStatus] = useState('');
    const [selectedJob, setSelectedJob] = useState('');
    const [searchQuery, setSearchQuery] = useState('');
    const [pagination, setPagination] = useState({
        current_page: 1,
        last_page: 1,
        total: 0,
    });
    const [selectedApplication, setSelectedApplication] = useState(null);
    const [showDetailsModal, setShowDetailsModal] = useState(false);
    const [updatingStatus, setUpdatingStatus] = useState(false);
    const [adminNotes, setAdminNotes] = useState('');

    const { successAlert, errorAlert } = useAlerts();

    const fetchApplicants = async (page = 1) => {
        setLoading(true);
        try {
            const params = {
                page,
                ...(selectedStatus && { status: selectedStatus }),
                ...(selectedJob && { job_id: selectedJob }),
                ...(searchQuery && { search: searchQuery }),
            };

            const response = await axios.get(route('admin.api.job.applicants.list'), { params });

            if (response.data.success) {
                setApplications(response.data.data.data);
                setPagination({
                    current_page: response.data.data.current_page,
                    last_page: response.data.data.last_page,
                    total: response.data.data.total,
                });
                setJobs(response.data.jobs);
                setStatusCounts(response.data.statusCounts);
            }
        } catch (error) {
            console.error('Error fetching applicants:', error);
            errorAlert('Failed to load applicants');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchApplicants();
    }, [selectedStatus, selectedJob]);

    useEffect(() => {
        const timeoutId = setTimeout(() => {
            fetchApplicants(1);
        }, 500);
        return () => clearTimeout(timeoutId);
    }, [searchQuery]);

    const handleViewDetails = async (application) => {
        try {
            const response = await axios.get(route('admin.api.job.applicants.details', application.id));
            if (response.data.success) {
                setSelectedApplication(response.data.data);
                setAdminNotes(response.data.data.admin_notes || '');
                setShowDetailsModal(true);
            }
        } catch (error) {
            console.error('Error fetching applicant details:', error);
            errorAlert('Failed to load applicant details');
        }
    };

    const handleStatusChange = async (newStatus) => {
        if (!selectedApplication) return;

        setUpdatingStatus(true);
        try {
            const response = await axios.patch(
                route('admin.api.job.applicants.status', selectedApplication.id),
                {
                    status: newStatus,
                    admin_notes: adminNotes,
                }
            );

            if (response.data.success) {
                successAlert('Application status updated successfully');
                setSelectedApplication({ ...selectedApplication, status: newStatus, admin_notes: adminNotes });
                fetchApplicants(pagination.current_page);
                setShowDetailsModal(false);
                setSelectedApplication(null);
            }
        } catch (error) {
            console.error('Error updating status:', error);
            errorAlert('Failed to update status');
        } finally {
            setUpdatingStatus(false);
        }
    };

    const getStatusColor = (status) => {
        const colors = {
            pending: 'yellow',
            reviewing: 'blue',
            shortlisted: 'green',
            rejected: 'red',
            hired: 'purple',
        };
        return colors[status] || 'gray';
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold text-slate-800">Job Applicants</h2>}
        >
            <Head title="Job Applicants" />

            <div className="py-6">
                <div className="mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Stats Cards */}
                    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
                        <StatusFilter
                            label="All"
                            count={statusCounts.total}
                            active={selectedStatus === ''}
                            onClick={() => setSelectedStatus('')}
                            color="gray"
                        />
                        <StatusFilter
                            label="Pending"
                            count={statusCounts.pending}
                            active={selectedStatus === 'pending'}
                            onClick={() => setSelectedStatus('pending')}
                            color="yellow"
                        />
                        <StatusFilter
                            label="Reviewing"
                            count={statusCounts.reviewing}
                            active={selectedStatus === 'reviewing'}
                            onClick={() => setSelectedStatus('reviewing')}
                            color="blue"
                        />
                        <StatusFilter
                            label="Shortlisted"
                            count={statusCounts.shortlisted}
                            active={selectedStatus === 'shortlisted'}
                            onClick={() => setSelectedStatus('shortlisted')}
                            color="green"
                        />
                        <StatusFilter
                            label="Hired"
                            count={statusCounts.hired}
                            active={selectedStatus === 'hired'}
                            onClick={() => setSelectedStatus('hired')}
                            color="purple"
                        />
                        <StatusFilter
                            label="Rejected"
                            count={statusCounts.rejected}
                            active={selectedStatus === 'rejected'}
                            onClick={() => setSelectedStatus('rejected')}
                            color="red"
                        />
                    </div>

                    {/* Filters */}
                    <div className="bg-white rounded-xl shadow-sm border border-slate-200 p-4 mb-6">
                        <div className="flex flex-col md:flex-row gap-4">
                            {/* Search */}
                            <div className="flex-1">
                                <div className="relative">
                                    <input
                                        type="text"
                                        placeholder="Search by candidate name, email, or job title..."
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        className="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm"
                                    />
                                    <svg
                                        className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth={2}
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                        />
                                    </svg>
                                </div>
                            </div>

                            {/* Job Filter */}
                            <div className="md:w-64">
                                <select
                                    value={selectedJob}
                                    onChange={(e) => setSelectedJob(e.target.value)}
                                    className="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm bg-white"
                                >
                                    <option value="">All Jobs</option>
                                    {jobs.map((job) => (
                                        <option key={job.id} value={job.id}>
                                            {job.title} - {job.company}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    </div>

                    {/* Loading State */}
                    {loading && (
                        <div className="flex items-center justify-center py-12">
                            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                        </div>
                    )}

                    {/* Empty State */}
                    {!loading && applications.length === 0 && (
                        <div className="text-center py-12 bg-white rounded-xl border border-slate-200">
                            <svg
                                className="mx-auto h-16 w-16 text-slate-300"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={1.5}
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                />
                            </svg>
                            <h3 className="mt-4 text-lg font-medium text-slate-900">No applicants found</h3>
                            <p className="mt-2 text-slate-500">
                                {selectedStatus || selectedJob || searchQuery
                                    ? 'Try adjusting your filters'
                                    : 'No one has applied to your jobs yet'}
                            </p>
                        </div>
                    )}

                    {/* Applicants Grid */}
                    {!loading && applications.length > 0 && (
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            {applications.map((application) => (
                                <ApplicantCard
                                    key={application.id}
                                    application={application}
                                    onViewDetails={handleViewDetails}
                                />
                            ))}
                        </div>
                    )}

                    {/* Pagination */}
                    {!loading && pagination.last_page > 1 && (
                        <div className="mt-6 flex items-center justify-between bg-white rounded-xl border border-slate-200 px-4 py-3">
                            <div className="text-sm text-slate-600">
                                Showing {((pagination.current_page - 1) * 12) + 1} - {Math.min(pagination.current_page * 12, pagination.total)} of {pagination.total} applicants
                            </div>
                            <div className="flex gap-2">
                                <button
                                    onClick={() => fetchApplicants(pagination.current_page - 1)}
                                    disabled={pagination.current_page === 1}
                                    className="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    Previous
                                </button>
                                <button
                                    onClick={() => fetchApplicants(pagination.current_page + 1)}
                                    disabled={pagination.current_page === pagination.last_page}
                                    className="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    Next
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Details Modal */}
            {showDetailsModal && selectedApplication && (
                <div className="fixed inset-0 z-50 overflow-y-auto">
                    <div className="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                        <div className="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" onClick={() => setShowDetailsModal(false)}></div>

                        <span className="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                        <div className="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                            <div className="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div className="flex items-start justify-between mb-4">
                                    <div className="flex items-center gap-4">
                                        <div className="w-16 h-16 rounded-xl bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-xl font-bold">
                                            {selectedApplication.candidate_name?.charAt(0).toUpperCase() || '?'}
                                        </div>
                                        <div>
                                            <h3 className="text-xl font-semibold text-slate-900">
                                                {selectedApplication.candidate_name}
                                            </h3>
                                            <p className="text-slate-600">{selectedApplication.candidate_email}</p>
                                        </div>
                                    </div>
                                    <button
                                        onClick={() => setShowDetailsModal(false)}
                                        className="text-slate-400 hover:text-slate-600 transition-colors"
                                    >
                                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>

                                {/* Job Info */}
                                <div className="mb-6 p-4 bg-slate-50 rounded-xl">
                                    <p className="text-xs text-slate-500 mb-1">Applied for:</p>
                                    <p className="text-lg font-semibold text-slate-800">{selectedApplication.job?.title}</p>
                                    <p className="text-sm text-slate-600">{selectedApplication.job?.company} • {selectedApplication.job?.location}</p>
                                    <p className="text-xs text-slate-500 mt-1">
                                        Applied on: {new Date(selectedApplication.created_at).toLocaleDateString('en-IN', {
                                            day: 'numeric',
                                            month: 'long',
                                            year: 'numeric',
                                        })}
                                    </p>
                                </div>

                                {/* Contact Info */}
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                    <div className="p-3 border border-slate-200 rounded-xl">
                                        <p className="text-xs text-slate-500">Phone</p>
                                        <p className="text-sm font-medium text-slate-800">{selectedApplication.candidate_phone || 'Not provided'}</p>
                                    </div>
                                    <div className="p-3 border border-slate-200 rounded-xl">
                                        <p className="text-xs text-slate-500">Current Status</p>
                                        <span className={`inline-block px-3 py-1 rounded-full text-xs font-medium border capitalize mt-1 ${
                                            selectedApplication.status === 'pending' ? 'bg-yellow-100 text-yellow-800 border-yellow-200' :
                                            selectedApplication.status === 'reviewing' ? 'bg-blue-100 text-blue-800 border-blue-200' :
                                            selectedApplication.status === 'shortlisted' ? 'bg-green-100 text-green-800 border-green-200' :
                                            selectedApplication.status === 'hired' ? 'bg-purple-100 text-purple-800 border-purple-200' :
                                            'bg-red-100 text-red-800 border-red-200'
                                        }`}>
                                            {selectedApplication.status}
                                        </span>
                                    </div>
                                </div>

                                {/* Skills */}
                                {selectedApplication.candidate_skills && selectedApplication.candidate_skills.length > 0 && (
                                    <div className="mb-6">
                                        <p className="text-sm font-medium text-slate-700 mb-2">Skills</p>
                                        <div className="flex flex-wrap gap-2">
                                            {selectedApplication.candidate_skills.map((skill, idx) => (
                                                <span key={idx} className="px-3 py-1 bg-blue-50 text-blue-700 text-sm rounded-lg border border-blue-100">
                                                    {skill}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* Experience */}
                                {selectedApplication.candidate_experience && (
                                    <div className="mb-6">
                                        <p className="text-sm font-medium text-slate-700 mb-2">Experience</p>
                                        <p className="text-sm text-slate-600 bg-slate-50 p-3 rounded-xl">
                                            {typeof selectedApplication.candidate_experience === 'string'
                                                ? selectedApplication.candidate_experience
                                                : JSON.stringify(selectedApplication.candidate_experience)}
                                        </p>
                                    </div>
                                )}

                                {/* Cover Letter */}
                                {selectedApplication.cover_letter && (
                                    <div className="mb-6">
                                        <p className="text-sm font-medium text-slate-700 mb-2">Cover Letter</p>
                                        <p className="text-sm text-slate-600 bg-slate-50 p-3 rounded-xl whitespace-pre-wrap">
                                            {selectedApplication.cover_letter}
                                        </p>
                                    </div>
                                )}

                                {/* Resume */}
                                {selectedApplication.resume_url && (
                                    <div className="mb-6">
                                        <p className="text-sm font-medium text-slate-700 mb-2">Resume</p>
                                        <a
                                            href={`/${selectedApplication.resume_url}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="inline-flex items-center gap-2 px-4 py-2 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition-colors text-sm font-medium"
                                        >
                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            Download Resume
                                        </a>
                                    </div>
                                )}

                                {/* Status Update */}
                                <div className="border-t border-slate-200 pt-4">
                                    <p className="text-sm font-medium text-slate-700 mb-3">Update Status</p>
                                    <div className="flex flex-wrap gap-2 mb-4">
                                        {['pending', 'reviewing', 'shortlisted', 'hired', 'rejected'].map((status) => (
                                            <button
                                                key={status}
                                                onClick={() => handleStatusChange(status)}
                                                disabled={updatingStatus || selectedApplication.status === status}
                                                className={`px-4 py-2 rounded-lg text-sm font-medium capitalize transition-colors ${
                                                    selectedApplication.status === status
                                                        ? 'bg-slate-100 text-slate-400 cursor-not-allowed'
                                                        : status === 'pending' ? 'bg-yellow-50 text-yellow-700 hover:bg-yellow-100' :
                                                          status === 'reviewing' ? 'bg-blue-50 text-blue-700 hover:bg-blue-100' :
                                                          status === 'shortlisted' ? 'bg-green-50 text-green-700 hover:bg-green-100' :
                                                          status === 'hired' ? 'bg-purple-50 text-purple-700 hover:bg-purple-100' :
                                                          'bg-red-50 text-red-700 hover:bg-red-100'
                                                }`}
                                            >
                                                {updatingStatus && selectedApplication.status !== status ? (
                                                    <span className="flex items-center gap-1">
                                                        <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
                                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                                        </svg>
                                                        Updating...
                                                    </span>
                                                ) : (
                                                    status
                                                )}
                                            </button>
                                        ))}
                                    </div>

                                    {/* Admin Notes */}
                                    <div>
                                        <p className="text-sm font-medium text-slate-700 mb-2">Admin Notes</p>
                                        <textarea
                                            value={adminNotes}
                                            onChange={(e) => setAdminNotes(e.target.value)}
                                            placeholder="Add notes about this candidate..."
                                            rows={3}
                                            className="w-full px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-sm resize-none"
                                        />
                                        <button
                                            onClick={() => handleStatusChange(selectedApplication.status)}
                                            disabled={updatingStatus}
                                            className="mt-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium disabled:opacity-50"
                                        >
                                            Save Notes
                                        </button>
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
