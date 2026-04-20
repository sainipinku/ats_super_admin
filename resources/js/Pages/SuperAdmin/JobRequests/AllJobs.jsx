import React, { useState, useEffect, useRef } from "react";
import { Head, Link } from "@inertiajs/react";
import AuthenticatedLayout from "../Layouts/AuthenticatedLayout";
import ConfirmDialog from "../../../Components/ConfirmDialog";
import { useAlerts } from "../../../Components/Alerts";

// Reusable JobCard Component
const JobCard = ({ job, onStatusChange, onCloseJob, onViewDetails, onEdit, onDelete }) => {
    const [showDropdown, setShowDropdown] = useState(false);
    const dropdownRef = useRef(null);

    // Close dropdown when clicking outside
    useEffect(() => {
        const handleClickOutside = (event) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setShowDropdown(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const getStatusBadge = (status) => {
        const badges = {
            pending: 'bg-yellow-100 text-yellow-800',
            active: 'bg-green-100 text-green-800',
            inactive: 'bg-gray-100 text-gray-800',
            declined: 'bg-red-100 text-red-800',
            closed: 'bg-red-200 text-red-900',
        };
        return badges[status] || 'bg-gray-100 text-gray-800';
    };

    const canToggleStatus = ['active', 'inactive', 'closed'].includes(job.status);
    const canClose = ['active', 'inactive'].includes(job.status);

    return (
        <div className="bg-white rounded-3xl shadow-sm p-4 border min-h-[320px] relative flex flex-col border-slate-200 hover:border-blue-300 hover:ring-2 hover:ring-blue-200 transition-all duration-200">
            <div className="absolute -top-4 -right-2 z-10 flex gap-1">
                <button
                    onClick={() => onEdit(job)}
                    className="w-8 h-8 bg-blue-500 text-white rounded-full flex items-center justify-center hover:bg-blue-600 transition-colors shadow-md"
                    title="Edit Job"
                >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                </button>
                <button
                    onClick={() => onDelete(job)}
                    className="w-8 h-8 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 transition-colors shadow-md"
                    title="Delete Job"
                >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
            </div>

            <div className="flex items-start justify-between mb-2">
                <div className="flex gap-3">
                    <img src={job.company_image || job.companyImage} alt={job.title} className="w-10 h-10 rounded-xl object-cover" />
                    <div>
                        <h2 className="text-[16px] font-semibold text-slate-900 line-clamp-1">{job.title}</h2>
                        <p className="text-slate-500 text-[12px]">{job.company}</p>
                    </div>
                </div>
                <div className="flex flex-col items-end gap-1">
                    <span className="px-2 py-1 rounded-full bg-emerald-100 text-emerald-700 text-[11px] font-medium">
                        {job.job_type || job.type}
                    </span>
                    {/* Show status badge */}
                    <span className={`px-2 py-0.5 rounded-full text-[10px] font-medium capitalize ${getStatusBadge(job.status)}`}>
                        {job.status === 'active' ? 'Active' : job.status === 'inactive' ? 'Deactive' : job.status === 'closed' ? 'Closed' : job.status}
                    </span>
                </div>
            </div>

            <div className="space-y-1 text-slate-600 text-[12px] mb-2">
                <div className="flex items-center gap-1">
                    <svg className="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>{job.location}</span>
                </div>
                <div className="flex items-center gap-1">
                    <svg className="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{job.experience}</span>
                </div>
                <div className="flex items-center gap-1">
                    <svg className="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span className="text-emerald-600 font-medium">{job.salary}</span>
                </div>
            </div>

            {/* Created By Admin */}
            <div className="flex items-center gap-1 text-[11px] text-slate-500 mb-2">
                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span>Posted by: {job.creator?.name || "Unknown"}</span>
            </div>

            {/* Skills */}
            <div className="flex flex-wrap gap-1 mb-2">
                {job.skills?.slice(0, 3).map((skill, index) => (
                    <span key={index} className="px-2 py-0.5 bg-blue-50 text-blue-600 rounded text-[10px] font-medium">
                        {skill}
                    </span>
                ))}
                {job.skills?.length > 3 && (
                    <span className="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-[10px]">
                        +{job.skills.length - 3}
                    </span>
                )}
            </div>

            {/* Posted Date, Applicants & Status Dropdown */}
            <div className="text-[11px] text-slate-400 mb-3">
                <div className="flex items-center justify-between">
                    <span>Posted: {job.created_at ? new Date(job.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'Just now'}</span>
                    <div className="flex items-center gap-3">
                        <span className="flex items-center gap-1">
                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5 0h-6v-1a6 6 0 00-9 5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            {job.applicants || 0} applicants
                        </span>
                        {/* Status Change Dropdown */}
                        {canToggleStatus && (
                        <div className="relative" ref={dropdownRef}>
                            <button
                                onClick={() => setShowDropdown(!showDropdown)}
                                className="flex items-center gap-1 px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg text-slate-600 text-[10px] font-medium transition-colors"
                                title="Change Job Status"
                            >
                                Job Status
                                <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            {showDropdown && (
                                <div className="absolute right-0 top-full mt-1 w-32 bg-white rounded-lg shadow-lg border border-slate-200 py-1 z-20">
                                    <button
                                        onClick={() => {
                                            onStatusChange(job, 'active');
                                            setShowDropdown(false);
                                        }}
                                        disabled={job.status === 'active'}
                                        className={`w-full text-left px-3 py-2 text-[11px] hover:bg-slate-50 transition-colors ${job.status === 'active' ? 'text-green-600 font-semibold bg-green-50' : 'text-slate-700'
                                            }`}
                                    >
                                        Active
                                    </button>
                                    <button
                                        onClick={() => {
                                            onStatusChange(job, 'inactive');
                                            setShowDropdown(false);
                                        }}
                                        disabled={job.status === 'inactive'}
                                        className={`w-full text-left px-3 py-2 text-[11px] hover:bg-slate-50 transition-colors ${job.status === 'inactive' ? 'text-gray-600 font-semibold bg-gray-50' : 'text-slate-700'
                                            }`}
                                    >
                                        Deactive
                                    </button>
                                    {canClose && (
                                        <>
                                            <div className="border-t border-slate-100 my-1"></div>
                                            <button
                                                onClick={() => {
                                                    onCloseJob(job);
                                                    setShowDropdown(false);
                                                }}
                                                className="w-full text-left px-3 py-2 text-[11px] text-red-600 hover:bg-red-50 transition-colors"
                                            >
                                                Close
                                            </button>
                                        </>
                                    )}
                                </div>
                            )}
                        </div>
                        )}
                    </div>
                </div>
            </div>

            <div className="mt-auto">
                <button
                    onClick={() => onViewDetails(job)}
                    className="w-full py-2.5 rounded-xl text-blue-600 text-[12px] font-semibold hover:bg-blue-50 transition-colors border border-blue-200 bg-white"
                >
                    View Details
                </button>
            </div>
        </div>
    );
};

export default function AllJobs({ auth }) {
    const [jobs, setJobs] = useState([]);
    const [loading, setLoading] = useState(true);
    const [searchQuery, setSearchQuery] = useState('');
    const [activeTab, setActiveTab] = useState('all');
    const [selectedJob, setSelectedJob] = useState(null);
    const [detailsLoading, setDetailsLoading] = useState(false);
    const [editingJob, setEditingJob] = useState(null);
    const [editForm, setEditForm] = useState(null);
    const [editSaving, setEditSaving] = useState(false);
    const [confirmToggleOpen, setConfirmToggleOpen] = useState(false);
    const [confirmToggleJob, setConfirmToggleJob] = useState(null);
    const [confirmToggleStatus, setConfirmToggleStatus] = useState(null);
    const [confirmCloseOpen, setConfirmCloseOpen] = useState(false);
    const [confirmCloseJob, setConfirmCloseJob] = useState(null);

    const { successAlert, errorAlert } = useAlerts();

    // Load jobs from API on component mount
    useEffect(() => {
        fetchJobs();
    }, []);

    const fetchJobs = async () => {
        try {
            const response = await fetch(route('super.job.requests.api.all'));
            const data = await response.json();
            if (data.success) {
                setJobs(data.data);
            } else {
                setJobs([]);
            }
        } catch (error) {
            console.error('Error fetching jobs:', error);
            setJobs([]);
        } finally {
            setLoading(false);
        }
    };

    const handleStatusChange = (job, newStatus) => {
        if (job.status === newStatus) return;
        setConfirmToggleJob(job);
        setConfirmToggleStatus(newStatus);
        setConfirmToggleOpen(true);
    };

    const confirmToggle = async () => {
        if (!confirmToggleJob || !confirmToggleStatus) return;

        try {
            const response = await fetch(route('super.job.requests.api.toggle-status', confirmToggleJob.id), {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ status: confirmToggleStatus }),
            });

            const data = await response.json().catch(() => null);
            if (!response.ok || !data?.success) {
                const message =
                    data?.message === 'Only approved or closed jobs can be toggled.'
                        ? 'Approve the job first to change status.'
                        : (data?.message || 'Failed to update job status.');
                errorAlert(message);
                return;
            }

            setJobs(prev => prev.map(j => (j.id === confirmToggleJob.id ? data.data : j)));
            successAlert('Job status updated successfully!');
        } catch (error) {
            console.error('Error changing job status:', error);
            errorAlert('Failed to update job status.');
        } finally {
            setConfirmToggleOpen(false);
            setConfirmToggleJob(null);
            setConfirmToggleStatus(null);
        }
    };

    const handleCloseJob = (job) => {
        setConfirmCloseJob(job);
        setConfirmCloseOpen(true);
    };

    const confirmClose = async () => {
        if (!confirmCloseJob) return;

        try {
            const response = await fetch(route('super.job.requests.api.close', confirmCloseJob.id), {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            });

            const data = await response.json().catch(() => null);
            if (!response.ok || !data?.success) {
                errorAlert(data?.message || 'Failed to close job.');
                return;
            }

            setJobs(prev => prev.map(j => (j.id === confirmCloseJob.id ? data.data : j)));
            successAlert('Job closed successfully!');
        } catch (error) {
            console.error('Error closing job:', error);
            errorAlert('Failed to close job.');
        } finally {
            setConfirmCloseOpen(false);
            setConfirmCloseJob(null);
        }
    };

    const handleViewDetails = async (job) => {
        setDetailsLoading(true);
        setSelectedJob(null);
        try {
            const response = await fetch(route('super.job.requests.api.show', job.id), {
                headers: {
                    'Accept': 'application/json',
                },
            });
            const data = await response.json();
            if (data.success) {
                setSelectedJob(data.data);
            } else {
                errorAlert(data.message || 'Failed to load job details.');
                setSelectedJob(job);
            }
        } catch (error) {
            console.error('Error fetching job details:', error);
            errorAlert('Failed to load job details.');
            setSelectedJob(job);
        } finally {
            setDetailsLoading(false);
        }
    };

    const handleDelete = async (job) => {
        if (confirm(`Are you sure you want to delete "${job.title}" job post?`)) {
            try {
                const response = await fetch(route('super.job.requests.api.destroy', job.id), {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                        'Accept': 'application/json',
                    },
                });
                const data = await response.json();
                if (data.success) {
                    setJobs((prev) => prev.filter((j) => j.id !== job.id));
                    if (selectedJob?.id === job.id) setSelectedJob(null);
                    if (editingJob?.id === job.id) {
                        setEditingJob(null);
                        setEditForm(null);
                    }
                    successAlert('Job post deleted successfully!');
                } else {
                    errorAlert(data.message || 'Failed to delete job.');
                }
            } catch (error) {
                console.error('Error deleting job:', error);
                errorAlert('Failed to delete job.');
            }
        }
    };

    const normalizeArrayText = (value) => {
        if (!Array.isArray(value)) return '';
        return value.filter(Boolean).join(', ');
    };

    const handleEdit = async (job) => {
        setEditingJob(job);
        setEditForm({
            title: job.title || '',
            company: job.company || '',
            location: job.location || '',
            job_type: job.job_type || job.type || 'Full Time',
            experience: job.experience || '',
            salary: job.salary || '',
            last_date: job.last_date || '',
            description: job.description || '',
            key_responsibilities: job.key_responsibilities || '',
            qualifications: job.qualifications || '',
            skills: normalizeArrayText(job.skills),
            perks: normalizeArrayText(job.perks),
            company_image: null,
            company_image_preview: job.company_image || job.companyImage || '',
        });
    };

    const parseCommaList = (value) => {
        return String(value || '')
            .split(',')
            .map((s) => s.trim())
            .filter((s) => s.length > 0);
    };

    const handleEditSubmit = async (e) => {
        e.preventDefault();
        if (!editingJob || !editForm) return;
        setEditSaving(true);
        try {
            const formData = new FormData();
            formData.append('title', editForm.title);
            formData.append('company', editForm.company);
            formData.append('location', editForm.location);
            formData.append('job_type', editForm.job_type);
            formData.append('experience', editForm.experience || '');
            formData.append('salary', editForm.salary || '');
            formData.append('last_date', editForm.last_date || '');
            formData.append('description', editForm.description || '');
            formData.append('key_responsibilities', editForm.key_responsibilities || '');
            formData.append('qualifications', editForm.qualifications || '');
            formData.append('skills', JSON.stringify(parseCommaList(editForm.skills)));
            formData.append('perks', JSON.stringify(parseCommaList(editForm.perks)));
            if (editForm.company_image) {
                formData.append('company_image', editForm.company_image);
            }

            const response = await fetch(route('super.job.requests.api.update', editingJob.id), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'Accept': 'application/json',
                },
                body: formData,
            });
            const data = await response.json();
            if (data.success) {
                setJobs((prev) => prev.map((j) => (j.id === editingJob.id ? { ...j, ...data.data } : j)));
                if (selectedJob?.id === editingJob.id) {
                    setSelectedJob((prev) => (prev ? { ...prev, ...data.data } : prev));
                }
                setEditingJob(null);
                setEditForm(null);
                successAlert('Job updated successfully!');
            } else {
                errorAlert(data.message || 'Failed to update job.');
            }
        } catch (error) {
            console.error('Error updating job:', error);
            errorAlert('Failed to update job.');
        } finally {
            setEditSaving(false);
        }
    };

    // Filter jobs based on search query and active tab
    const filteredJobs = jobs.filter((job) => {
        // Search filter
        const matchesSearch =
            searchQuery === '' ||
            job.title?.toLowerCase().includes(searchQuery.toLowerCase()) ||
            job.company?.toLowerCase().includes(searchQuery.toLowerCase()) ||
            job.creator?.name?.toLowerCase().includes(searchQuery.toLowerCase());

        // Tab filter
        let matchesTab = true;
        if (activeTab !== 'all') {
            matchesTab = job.status === activeTab;
        }

        return matchesSearch && matchesTab;
    });

    const getJobCounts = () => {
        return {
            all: jobs.length,
            active: jobs.filter((j) => j.status === 'active').length,
            inactive: jobs.filter((j) => j.status === 'inactive').length,
            pending: jobs.filter((j) => j.status === 'pending').length,
            declined: jobs.filter((j) => j.status === 'declined').length,
            closed: jobs.filter((j) => j.status === 'closed').length,
        };
    };

    const counts = getJobCounts();

    const tabs = [
        { key: 'all', label: 'All Jobs', count: counts.all },
        { key: 'active', label: 'Active', count: counts.active },
        { key: 'inactive', label: 'Inactive', count: counts.inactive },
        { key: 'pending', label: 'Pending', count: counts.pending },
        { key: 'declined', label: 'Declined', count: counts.declined },
        { key: 'closed', label: 'Closed', count: counts.closed },
    ];

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="All Job Listings" />

            <div className="min-h-screen bg-slate-100 p-4 sm:p-6">
                <div className="max-w-7xl mx-auto">
                    {/* Header */}
                    <div className="mb-6">
                        <h1 className="text-2xl sm:text-3xl font-bold text-slate-800">
                            All Job Listings
                        </h1>
                        <p className="text-slate-500 mt-1">
                            View and manage all job posts from all admins
                        </p>
                    </div>

                    {/* Search Bar */}
                    <div className="mb-6">
                        <div className="relative max-w-md">
                            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg className="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input
                                type="text"
                                placeholder="Search by job title, company, or admin name..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 bg-white shadow-sm"
                            />
                            {searchQuery && (
                                <button
                                    onClick={() => setSearchQuery('')}
                                    className="absolute inset-y-0 right-0 pr-3 flex items-center"
                                >
                                    <svg className="h-5 w-5 text-gray-400 hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            )}
                        </div>
                    </div>

                    {/* Filter Tabs */}
                    <div className="flex flex-wrap gap-2 mb-6">
                        {tabs.map((tab) => (
                            <button
                                key={tab.key}
                                onClick={() => setActiveTab(tab.key)}
                                className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${activeTab === tab.key
                                        ? "bg-[#5146E6] text-white"
                                        : "bg-white text-slate-600 hover:bg-slate-50"
                                    }`}
                            >
                                {tab.label}
                                <span className={`ml-2 px-2 py-0.5 rounded-full text-xs ${activeTab === tab.key
                                        ? "bg-white/20 text-white"
                                        : "bg-slate-200 text-slate-700"
                                    }`}>
                                    {tab.count}
                                </span>
                            </button>
                        ))}
                    </div>

                    {/* Jobs Grid */}
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        {loading ? (
                            <div className="col-span-full flex items-center justify-center py-12">
                                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-[#5146E6]"></div>
                            </div>
                        ) : filteredJobs.length > 0 ? (
                            filteredJobs.map((job, idx) => (
                                <JobCard
                                    key={idx}
                                    job={job}
                                    onStatusChange={handleStatusChange}
                                    onCloseJob={handleCloseJob}
                                    onViewDetails={handleViewDetails}
                                    onEdit={handleEdit}
                                    onDelete={handleDelete}
                                />
                            ))
                        ) : (
                            <div className="col-span-full flex items-center justify-center py-12">
                                <div className="text-center">
                                    <svg
                                        className="w-16 h-16 mx-auto text-gray-400 mb-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                    </svg>
                                    <h3 className="text-lg font-medium text-gray-900 mb-2">
                                        {searchQuery ? 'No Jobs Found' : 'No Job Listings Found'}
                                    </h3>
                                    <p className="text-gray-500">
                                        {searchQuery
                                            ? `No jobs matching "${searchQuery}" found. Try a different search term.`
                                            : 'No job posts have been created yet.'}
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>

                    {(selectedJob || detailsLoading) && (
                        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                            <div className="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                                <div className="p-6">
                                    <div className="flex items-start justify-between mb-4">
                                        <div className="flex gap-3">
                                            {selectedJob ? (
                                                <img
                                                    src={selectedJob.company_image || selectedJob.companyImage}
                                                    alt={selectedJob.title}
                                                    className="w-12 h-12 rounded-xl object-cover"
                                                />
                                            ) : (
                                                <div className="w-12 h-12 rounded-xl bg-slate-100" />
                                            )}
                                            <div>
                                                <h2 className="text-xl font-semibold text-slate-900">{selectedJob?.title || 'Loading...'}</h2>
                                                <p className="text-slate-500">{selectedJob?.company || ''}</p>
                                            </div>
                                        </div>
                                        <button
                                            onClick={() => {
                                                setSelectedJob(null);
                                                setDetailsLoading(false);
                                            }}
                                            className="p-2 hover:bg-slate-100 rounded-full transition-colors"
                                        >
                                            <svg className="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>

                                    {detailsLoading && !selectedJob ? (
                                        <div className="py-10 flex items-center justify-center">
                                            <div className="animate-spin rounded-full h-10 w-10 border-b-2 border-[#5146E6]" />
                                        </div>
                                    ) : (
                                        <>
                                            <div className="flex items-center gap-2 mb-4">
                                                <span className={`px-3 py-1 rounded-full text-sm font-medium capitalize ${selectedJob?.status === 'active' ? 'bg-green-100 text-green-800' :
                                                        selectedJob?.status === 'inactive' ? 'bg-gray-100 text-gray-800' :
                                                            selectedJob?.status === 'closed' ? 'bg-red-100 text-red-800' :
                                                                selectedJob?.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                                                                    selectedJob?.status === 'declined' ? 'bg-red-100 text-red-800' :
                                                                        'bg-gray-100 text-gray-800'
                                                    }`}>
                                                    {selectedJob?.status}
                                                </span>
                                                <span className="px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-sm font-medium">
                                                    {selectedJob?.job_type || selectedJob?.type}
                                                </span>
                                            </div>

                                            <div className="space-y-4 text-slate-600">
                                                <div className="grid grid-cols-2 gap-4">
                                                    <div className="flex items-center gap-2">
                                                        <svg className="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        </svg>
                                                        <span>{selectedJob?.location}</span>
                                                    </div>
                                                    <div className="flex items-center gap-2">
                                                        <svg className="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                        <span>{selectedJob?.experience}</span>
                                                    </div>
                                                    <div className="flex items-center gap-2">
                                                        <svg className="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                        <span className="text-emerald-600 font-medium">{selectedJob?.salary}</span>
                                                    </div>
                                                    <div className="flex items-center gap-2">
                                                        <svg className="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5 0h-6v-1a6 6 0 00-9 5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                                        </svg>
                                                        <span>{selectedJob?.applicants || 0} applicants</span>
                                                    </div>
                                                </div>

                                                <div className="border-t pt-4">
                                                    <h3 className="font-semibold text-slate-800 mb-2">Description</h3>
                                                    <p className="text-sm leading-relaxed">{selectedJob?.description || 'No description available.'}</p>
                                                </div>

                                                {selectedJob?.responsibilities && (
                                                    <div className="border-t pt-4">
                                                        <h3 className="font-semibold text-slate-800 mb-2">Responsibilities</h3>
                                                        <ul className="list-disc list-inside text-sm space-y-1">
                                                            {Array.isArray(selectedJob.responsibilities) ? selectedJob.responsibilities.map((item, idx) => (
                                                                <li key={idx}>{item}</li>
                                                            )) : <li>{selectedJob.responsibilities}</li>}
                                                        </ul>
                                                    </div>
                                                )}

                                                {selectedJob?.requirements && (
                                                    <div className="border-t pt-4">
                                                        <h3 className="font-semibold text-slate-800 mb-2">Requirements</h3>
                                                        <ul className="list-disc list-inside text-sm space-y-1">
                                                            {Array.isArray(selectedJob.requirements) ? selectedJob.requirements.map((item, idx) => (
                                                                <li key={idx}>{item}</li>
                                                            )) : <li>{selectedJob.requirements}</li>}
                                                        </ul>
                                                    </div>
                                                )}

                                                {selectedJob?.skills && selectedJob.skills.length > 0 && (
                                                    <div className="border-t pt-4">
                                                        <h3 className="font-semibold text-slate-800 mb-2">Skills</h3>
                                                        <div className="flex flex-wrap gap-2">
                                                            {selectedJob.skills.map((skill, idx) => (
                                                                <span key={idx} className="px-3 py-1 bg-blue-50 text-blue-600 rounded-full text-sm font-medium">
                                                                    {skill}
                                                                </span>
                                                            ))}
                                                        </div>
                                                    </div>
                                                )}

                                                {selectedJob?.perks && selectedJob.perks.length > 0 && (
                                                    <div className="border-t pt-4">
                                                        <h3 className="font-semibold text-slate-800 mb-2">Perks & Benefits</h3>
                                                        <div className="flex flex-wrap gap-2">
                                                            {selectedJob.perks.map((perk, idx) => (
                                                                <span key={idx} className="px-3 py-1 bg-emerald-50 text-emerald-600 rounded-full text-sm font-medium">
                                                                    {perk}
                                                                </span>
                                                            ))}
                                                        </div>
                                                    </div>
                                                )}

                                                <div className="border-t pt-4">
                                                    <h3 className="font-semibold text-slate-800 mb-2">Applicants</h3>
                                                    {Array.isArray(selectedJob?.applications) && selectedJob.applications.length > 0 ? (
                                                        <div className="overflow-x-auto">
                                                            <table className="w-full text-sm">
                                                                <thead>
                                                                    <tr className="text-left text-slate-500">
                                                                        <th className="py-2 pr-3 font-medium">Name</th>
                                                                        <th className="py-2 pr-3 font-medium">Email</th>
                                                                        <th className="py-2 pr-3 font-medium">Phone</th>
                                                                        <th className="py-2 pr-3 font-medium">Status</th>
                                                                        <th className="py-2 pr-3 font-medium">Applied</th>
                                                                        <th className="py-2 font-medium">Resume</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody className="divide-y divide-slate-100">
                                                                    {selectedJob.applications.map((app) => (
                                                                        <tr key={app.id} className="align-top">
                                                                            <td className="py-2 pr-3 text-slate-800">{app.candidate_name || app.candidate?.name || '-'}</td>
                                                                            <td className="py-2 pr-3">{app.candidate_email || app.candidate?.email || '-'}</td>
                                                                            <td className="py-2 pr-3">{app.candidate_phone || app.candidate?.phone || '-'}</td>
                                                                            <td className="py-2 pr-3 capitalize">{app.status || '-'}</td>
                                                                            <td className="py-2 pr-3">{app.created_at ? new Date(app.created_at).toLocaleString('en-US') : '-'}</td>
                                                                            <td className="py-2">
                                                                                {app.resume_url ? (
                                                                                    <a
                                                                                        href={(String(app.resume_url).startsWith('/') ? app.resume_url : `/${app.resume_url}`)}
                                                                                        target="_blank"
                                                                                        rel="noreferrer"
                                                                                        className="text-[#5146E6] hover:underline"
                                                                                    >
                                                                                        View
                                                                                    </a>
                                                                                ) : (
                                                                                    <span className="text-slate-400">—</span>
                                                                                )}
                                                                            </td>
                                                                        </tr>
                                                                    ))}
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    ) : (
                                                        <p className="text-sm text-slate-500">No applications yet.</p>
                                                    )}
                                                </div>

                                                <div className="border-t pt-4">
                                                    <h3 className="font-semibold text-slate-800 mb-2">Posted By</h3>
                                                    <p className="text-sm">{selectedJob?.creator?.name || 'Unknown'} on {selectedJob?.created_at ? new Date(selectedJob.created_at).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : 'N/A'}</p>
                                                </div>
                                            </div>
                                        </>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}

                    {editingJob && editForm && (
                        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                            <div className="bg-white rounded-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
                                <form onSubmit={handleEditSubmit} className="p-6">
                                    <div className="flex items-start justify-between mb-5">
                                        <div>
                                            <h2 className="text-xl font-semibold text-slate-900">Edit Job</h2>
                                            <p className="text-slate-500 text-sm">{editingJob.title}</p>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setEditingJob(null);
                                                setEditForm(null);
                                            }}
                                            className="p-2 hover:bg-slate-100 rounded-full transition-colors"
                                        >
                                            <svg className="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-slate-700 mb-1">Job Title</label>
                                            <input
                                                value={editForm.title}
                                                onChange={(e) => setEditForm((p) => ({ ...p, title: e.target.value }))}
                                                className="w-full border border-slate-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-[#5146E6] focus:border-[#5146E6]"
                                                required
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-slate-700 mb-1">Company</label>
                                            <input
                                                value={editForm.company}
                                                onChange={(e) => setEditForm((p) => ({ ...p, company: e.target.value }))}
                                                className="w-full border border-slate-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-[#5146E6] focus:border-[#5146E6]"
                                                required
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-slate-700 mb-1">Location</label>
                                            <input
                                                value={editForm.location}
                                                onChange={(e) => setEditForm((p) => ({ ...p, location: e.target.value }))}
                                                className="w-full border border-slate-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-[#5146E6] focus:border-[#5146E6]"
                                                required
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-slate-700 mb-1">Job Type</label>
                                            <select
                                                value={editForm.job_type}
                                                onChange={(e) => setEditForm((p) => ({ ...p, job_type: e.target.value }))}
                                                className="w-full border border-slate-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-[#5146E6] focus:border-[#5146E6]"
                                                required
                                            >
                                                <option>Full Time</option>
                                                <option>Part Time</option>
                                                <option>Contract</option>
                                                <option>Internship</option>
                                                <option>Freelance</option>
                                                <option>Remote</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-slate-700 mb-1">Experience</label>
                                            <input
                                                value={editForm.experience}
                                                onChange={(e) => setEditForm((p) => ({ ...p, experience: e.target.value }))}
                                                className="w-full border border-slate-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-[#5146E6] focus:border-[#5146E6]"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-slate-700 mb-1">Salary</label>
                                            <input
                                                value={editForm.salary}
                                                onChange={(e) => setEditForm((p) => ({ ...p, salary: e.target.value }))}
                                                className="w-full border border-slate-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-[#5146E6] focus:border-[#5146E6]"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-slate-700 mb-1">Last Date</label>
                                            <input
                                                type="date"
                                                value={editForm.last_date}
                                                onChange={(e) => setEditForm((p) => ({ ...p, last_date: e.target.value }))}
                                                className="w-full border border-slate-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-[#5146E6] focus:border-[#5146E6]"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-slate-700 mb-1">Company Logo</label>
                                            <input
                                                type="file"
                                                accept="image/*"
                                                onChange={(e) => {
                                                    const file = e.target.files?.[0] || null;
                                                    setEditForm((p) => ({
                                                        ...p,
                                                        company_image: file,
                                                        company_image_preview: file ? URL.createObjectURL(file) : p.company_image_preview,
                                                    }));
                                                }}
                                                className="w-full border border-slate-300 rounded-xl px-4 py-3"
                                            />
                                            {editForm.company_image_preview ? (
                                                <img
                                                    src={editForm.company_image_preview}
                                                    alt="Company"
                                                    className="mt-2 w-20 h-20 rounded-xl object-cover border border-slate-200"
                                                />
                                            ) : null}
                                        </div>
                                        <div className="md:col-span-2">
                                            <label className="block text-sm font-medium text-slate-700 mb-1">Skills (comma separated)</label>
                                            <input
                                                value={editForm.skills}
                                                onChange={(e) => setEditForm((p) => ({ ...p, skills: e.target.value }))}
                                                className="w-full border border-slate-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-[#5146E6] focus:border-[#5146E6]"
                                            />
                                        </div>
                                        <div className="md:col-span-2">
                                            <label className="block text-sm font-medium text-slate-700 mb-1">Perks (comma separated)</label>
                                            <input
                                                value={editForm.perks}
                                                onChange={(e) => setEditForm((p) => ({ ...p, perks: e.target.value }))}
                                                className="w-full border border-slate-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-[#5146E6] focus:border-[#5146E6]"
                                            />
                                        </div>
                                        <div className="md:col-span-2">
                                            <label className="block text-sm font-medium text-slate-700 mb-1">Job Description</label>
                                            <textarea
                                                value={editForm.description}
                                                onChange={(e) => setEditForm((p) => ({ ...p, description: e.target.value }))}
                                                rows={4}
                                                className="w-full border border-slate-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-[#5146E6] focus:border-[#5146E6]"
                                            />
                                        </div>
                                        <div className="md:col-span-2">
                                            <label className="block text-sm font-medium text-slate-700 mb-1">Key Responsibilities</label>
                                            <textarea
                                                value={editForm.key_responsibilities}
                                                onChange={(e) => setEditForm((p) => ({ ...p, key_responsibilities: e.target.value }))}
                                                rows={3}
                                                className="w-full border border-slate-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-[#5146E6] focus:border-[#5146E6]"
                                            />
                                        </div>
                                        <div className="md:col-span-2">
                                            <label className="block text-sm font-medium text-slate-700 mb-1">Qualifications</label>
                                            <textarea
                                                value={editForm.qualifications}
                                                onChange={(e) => setEditForm((p) => ({ ...p, qualifications: e.target.value }))}
                                                rows={3}
                                                className="w-full border border-slate-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-[#5146E6] focus:border-[#5146E6]"
                                            />
                                        </div>
                                    </div>

                                    <div className="mt-6 flex items-center justify-end gap-3">
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setEditingJob(null);
                                                setEditForm(null);
                                            }}
                                            className="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50"
                                            disabled={editSaving}
                                        >
                                            Cancel
                                        </button>
                                        <button
                                            type="submit"
                                            className="px-5 py-2.5 rounded-xl bg-[#5146E6] text-white hover:bg-[#4338CA] disabled:opacity-60"
                                            disabled={editSaving}
                                        >
                                            {editSaving ? 'Saving...' : 'Save Changes'}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    )}

                    <ConfirmDialog
                        isOpen={confirmToggleOpen}
                        onClose={() => {
                            setConfirmToggleOpen(false);
                            setConfirmToggleJob(null);
                            setConfirmToggleStatus(null);
                        }}
                        onConfirm={confirmToggle}
                        message={
                            confirmToggleJob
                                ? `${confirmToggleStatus === 'active' ? 'Activate' : 'Deactivate'} "${confirmToggleJob.title}"?`
                                : 'Are you sure you want to change job status?'
                        }
                        confirmText={confirmToggleStatus === 'active' ? 'Yes, Activate' : 'Yes, Deactivate'}
                        cancelText="Cancel"
                        modalSpinnerMessage="Processing Please Wait...."
                    />

                    <ConfirmDialog
                        isOpen={confirmCloseOpen}
                        onClose={() => {
                            setConfirmCloseOpen(false);
                            setConfirmCloseJob(null);
                        }}
                        onConfirm={confirmClose}
                        message={
                            confirmCloseJob
                                ? `Close "${confirmCloseJob.title}"? This action cannot be undone.`
                                : 'Are you sure you want to close this job?'
                        }
                        confirmText="Yes, Close"
                        cancelText="Cancel"
                        modalSpinnerMessage="Closing Please Wait...."
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
