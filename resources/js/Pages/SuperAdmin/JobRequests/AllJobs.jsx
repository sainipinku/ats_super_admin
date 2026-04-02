import React, { useState, useEffect, useRef } from "react";
import { Head, Link } from "@inertiajs/react";
import AuthenticatedLayout from "../Layouts/AuthenticatedLayout";

// Reusable JobCard Component
const JobCard = ({ job, onStatusChange, onCloseJob, onViewDetails }) => {
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

    return (
        <div className="bg-white rounded-3xl shadow-sm p-4 border min-h-[320px] relative flex flex-col border-slate-200 hover:border-blue-300 hover:ring-2 hover:ring-blue-200 transition-all duration-200">

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
                                        className={`w-full text-left px-3 py-2 text-[11px] hover:bg-slate-50 transition-colors ${
                                            job.status === 'active' ? 'text-green-600 font-semibold bg-green-50' : 'text-slate-700'
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
                                        className={`w-full text-left px-3 py-2 text-[11px] hover:bg-slate-50 transition-colors ${
                                            job.status === 'inactive' ? 'text-gray-600 font-semibold bg-gray-50' : 'text-slate-700'
                                        }`}
                                    >
                                        Deactive
                                    </button>
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
                                </div>
                            )}
                        </div>
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

    const handleStatusChange = async (job, newStatus) => {
        if (job.status === newStatus) return;
        
        const actionText = newStatus === 'active' ? 'activate' : 'deactivate';
        
        if (confirm(`Are you sure you want to ${actionText} "${job.title}"?`)) {
            try {
                const response = await fetch(route('super.job.requests.api.toggle-status', job.id), {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ status: newStatus }),
                });
                const data = await response.json();
                if (data.success) {
                    setJobs(jobs.map(j => j.id === job.id ? { ...j, status: newStatus } : j));
                    alert(`Job ${actionText}d successfully!`);
                } else {
                    alert(data.message || `Failed to ${actionText} job.`);
                }
            } catch (error) {
                console.error('Error changing job status:', error);
                alert(`Failed to ${actionText} job.`);
            }
        }
    };

    const handleCloseJob = async (job) => {
        if (confirm(`Are you sure you want to permanently CLOSE "${job.title}"?\n\nThis action cannot be undone and the job will be marked as closed.`)) {
            try {
                const response = await fetch(route('super.job.requests.api.close', job.id), {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                });
                const data = await response.json();
                if (data.success) {
                    setJobs(jobs.map(j => j.id === job.id ? { ...j, status: 'closed' } : j));
                    alert('Job closed successfully!');
                } else {
                    alert(data.message || 'Failed to close job.');
                }
            } catch (error) {
                console.error('Error closing job:', error);
                alert('Failed to close job.');
            }
        }
    };

    const handleViewDetails = (job) => {
        setSelectedJob(job);
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
                                className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                                    activeTab === tab.key
                                        ? "bg-[#5146E6] text-white"
                                        : "bg-white text-slate-600 hover:bg-slate-50"
                                }`}
                            >
                                {tab.label}
                                <span className={`ml-2 px-2 py-0.5 rounded-full text-xs ${
                                    activeTab === tab.key
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

                    {/* Job Details Modal */}
                    {selectedJob && (
                        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                            <div className="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                                <div className="p-6">
                                    {/* Modal Header */}
                                    <div className="flex items-start justify-between mb-4">
                                        <div className="flex gap-3">
                                            <img 
                                                src={selectedJob.company_image || selectedJob.companyImage} 
                                                alt={selectedJob.title} 
                                                className="w-12 h-12 rounded-xl object-cover" 
                                            />
                                            <div>
                                                <h2 className="text-xl font-semibold text-slate-900">{selectedJob.title}</h2>
                                                <p className="text-slate-500">{selectedJob.company}</p>
                                            </div>
                                        </div>
                                        <button
                                            onClick={() => setSelectedJob(null)}
                                            className="p-2 hover:bg-slate-100 rounded-full transition-colors"
                                        >
                                            <svg className="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>

                                    {/* Job Status Badge */}
                                    <div className="flex items-center gap-2 mb-4">
                                        <span className={`px-3 py-1 rounded-full text-sm font-medium capitalize ${
                                            selectedJob.status === 'active' ? 'bg-green-100 text-green-800' :
                                            selectedJob.status === 'inactive' ? 'bg-gray-100 text-gray-800' :
                                            selectedJob.status === 'closed' ? 'bg-red-100 text-red-800' :
                                            selectedJob.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                                            selectedJob.status === 'declined' ? 'bg-red-100 text-red-800' :
                                            'bg-gray-100 text-gray-800'
                                        }`}>
                                            {selectedJob.status}
                                        </span>
                                        <span className="px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-sm font-medium">
                                            {selectedJob.job_type || selectedJob.type}
                                        </span>
                                    </div>

                                    {/* Job Details */}
                                    <div className="space-y-4 text-slate-600">
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="flex items-center gap-2">
                                                <svg className="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                                <span>{selectedJob.location}</span>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <svg className="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span>{selectedJob.experience}</span>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <svg className="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span className="text-emerald-600 font-medium">{selectedJob.salary}</span>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <svg className="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5 0h-6v-1a6 6 0 00-9 5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                                </svg>
                                                <span>{selectedJob.applicants || 0} applicants</span>
                                            </div>
                                        </div>

                                        <div className="border-t pt-4">
                                            <h3 className="font-semibold text-slate-800 mb-2">Description</h3>
                                            <p className="text-sm leading-relaxed">{selectedJob.description || 'No description available.'}</p>
                                        </div>

                                        {selectedJob.responsibilities && (
                                            <div className="border-t pt-4">
                                                <h3 className="font-semibold text-slate-800 mb-2">Responsibilities</h3>
                                                <ul className="list-disc list-inside text-sm space-y-1">
                                                    {Array.isArray(selectedJob.responsibilities) ? selectedJob.responsibilities.map((item, idx) => (
                                                        <li key={idx}>{item}</li>
                                                    )) : <li>{selectedJob.responsibilities}</li>}
                                                </ul>
                                            </div>
                                        )}

                                        {selectedJob.requirements && (
                                            <div className="border-t pt-4">
                                                <h3 className="font-semibold text-slate-800 mb-2">Requirements</h3>
                                                <ul className="list-disc list-inside text-sm space-y-1">
                                                    {Array.isArray(selectedJob.requirements) ? selectedJob.requirements.map((item, idx) => (
                                                        <li key={idx}>{item}</li>
                                                    )) : <li>{selectedJob.requirements}</li>}
                                                </ul>
                                            </div>
                                        )}

                                        {selectedJob.skills && selectedJob.skills.length > 0 && (
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

                                        {selectedJob.perks && selectedJob.perks.length > 0 && (
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
                                            <h3 className="font-semibold text-slate-800 mb-2">Posted By</h3>
                                            <p className="text-sm">{selectedJob.creator?.name || 'Unknown'} on {selectedJob.created_at ? new Date(selectedJob.created_at).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : 'N/A'}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
