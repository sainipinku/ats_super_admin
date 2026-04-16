import React, { useState, useRef } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '../Layouts/AuthenticatedLayout';
import LocationInput from '../../../Components/LocationInput';

// Reusable JobCard Component
const JobCard = ({ job, onViewDetails, onEdit, onDelete, onResend, onStatusChange }) => {
    const [showFullPerks, setShowFullPerks] = useState(false);
    const [showDropdown, setShowDropdown] = useState(false);
    const dropdownRef = useRef(null);
    
    // Close dropdown when clicking outside
    React.useEffect(() => {
        const handleClickOutside = (event) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setShowDropdown(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);
    
    const perksToShow = showFullPerks ? job.perks : job.perks?.slice(0, 2);
    const hasMorePerks = job.perks && job.perks.length > 2;

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

    // Check if job can have status changed (active, inactive, closed)
    const canChangeStatus = ['active', 'inactive', 'closed'].includes(job.status);

    return (
        <div className={`bg-white rounded-3xl shadow-sm p-4 border min-h-[320px] relative flex flex-col border-slate-200 hover:border-blue-300 hover:ring-2 hover:ring-blue-200 transition-all duration-200`}>
            {/* Action Icons - Outside Card - Edit, Delete */}
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
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <span>{job.experience}</span>
                </div>
            </div>

            <p className="text-[18px] font-bold text-emerald-600 mb-2 leading-tight">{job.salary}</p>

            <div className="mb-2">
                <p className="text-[11px] font-semibold text-slate-700 mb-1">Skills:</p>
                <div className="flex flex-wrap gap-1">
                    {job.skills.slice(0, 3).map((skill) => (
                        <span key={skill} className="px-2 py-1 rounded-lg bg-blue-50 text-blue-700 text-[11px]">
                            {skill}
                        </span>
                    ))}
                    {job.skills.length > 3 && (
                        <span className="px-2 py-1 rounded-lg bg-slate-100 text-slate-700 text-[11px]">
                            +{job.skills.length - 3} more
                        </span>
                    )}
                </div>
            </div>

            <div className="mb-2 flex-grow">
                <p className="text-[11px] font-medium text-slate-700 mb-1">Perks:</p>
                <div className="text-[10px] text-slate-600 leading-4">
                    {perksToShow?.join(' • ') || 'Flexible working hours • Health Insurance • Learning opportunities'}
                    {hasMorePerks && (
                        <button 
                            onClick={() => setShowFullPerks(!showFullPerks)}
                            className="text-blue-600 hover:text-blue-800 ml-1 underline"
                        >
                            {showFullPerks ? 'Show less' : `+${job.perks.length - 2} more`}
                        </button>
                    )}
                </div>
            </div>

            <div className="mt-auto">
                <hr className="my-2" />

                {job.status === 'declined' && (
                    <button
                        onClick={() => onResend(job)}
                        className="w-full mb-2 py-2 rounded-xl bg-yellow-500 text-white text-[12px] font-semibold hover:bg-yellow-600 transition-colors"
                    >
                        ↻ Resend for Approval
                    </button>
                )}

                <div className="flex justify-between items-center text-[12px] text-slate-500 mb-3">
                    <p>Posted {job.created_at ? new Date(job.created_at).toLocaleDateString('en-US', { 
                        month: 'short', 
                        day: 'numeric', 
                        year: 'numeric' 
                    }) : (job.createdAt ? new Date(job.createdAt).toLocaleDateString('en-US', { 
                        month: 'short', 
                        day: 'numeric', 
                        year: 'numeric' 
                    }) : 'Just now')} <span className="mx-2">•</span> {job.applicants || 0} applicants</p>
                    
                    {/* Job Status Dropdown - Only for active/inactive/closed jobs */}
                    {canChangeStatus && (
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
                                            // If job is closed, show message to contact Super Admin
                                            if (job.status === 'closed') {
                                                alert('Please contact to Super Admin for active job post');
                                            } else {
                                                onStatusChange(job, 'active');
                                            }
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
                                            // Admin can close active/inactive jobs
                                            if (job.status === 'active' || job.status === 'inactive') {
                                                onStatusChange(job, 'closed');
                                            }
                                            setShowDropdown(false);
                                        }}
                                        disabled={job.status === 'closed'}
                                        className={`w-full text-left px-3 py-2 text-[11px] hover:bg-slate-50 transition-colors ${
                                            job.status === 'closed' ? 'text-red-600 font-semibold bg-red-50' : 'text-red-600'
                                        }`}
                                    >
                                        Close
                                    </button>
                                </div>
                            )}
                        </div>
                    )}
                </div>

                <button 
                    onClick={() => onViewDetails(job)}
                    className="w-full py-2 rounded-xl text-blue-600 text-[14px] font-semibold hover:bg-blue-50 transition-colors"
                >
                    View Details
                </button>
            </div>
        </div>
    );
};

export default function JobListing({ auth }) {
    const [jobs, setJobs] = useState([]);
    const [loading, setLoading] = useState(true);
    const [searchQuery, setSearchQuery] = useState('');

    // Filter jobs based on search query
    const filteredJobs = jobs.filter(job => {
        const query = searchQuery.toLowerCase();
        return (
            job.title?.toLowerCase().includes(query) ||
            job.company?.toLowerCase().includes(query)
        );
    });

    // Load jobs from API on component mount
    React.useEffect(() => {
        fetchJobs();
    }, []);

    const fetchJobs = async () => {
        try {
            const response = await fetch(route('admin.api.jobs.list'), {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'Accept': 'application/json',
                },
            });
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

    const [selectedJob, setSelectedJob] = useState(null);

    const handleViewDetails = (job) => {
        setSelectedJob(job);
    };

    const handleEdit = (job) => {
        // Navigate to job post form with full job data for editing
        router.visit(route('admin.job.posts.index'), {
            data: { edit: job.id },
            preserveState: true,
        });
    };

    const handleDelete = async (job) => {
        if (confirm(`Are you sure you want to delete "${job.title}" job post?`)) {
            try {
                const response = await fetch(route('admin.api.jobs.destroy', job.id), {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                        'Accept': 'application/json',
                    },
                });
                const data = await response.json();
                if (data.success) {
                    setJobs(jobs.filter(j => j.id !== job.id));
                    alert('Job post deleted successfully!');
                } else {
                    alert(data.message || 'Failed to delete job.');
                }
            } catch (error) {
                console.error('Error deleting job:', error);
                alert('Failed to delete job.');
            }
        }
    };

    const handleResend = async (job) => {
        if (confirm(`Resend "${job.title}" for approval?`)) {
            try {
                const response = await fetch(route('admin.api.jobs.resend', job.id), {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                        'Accept': 'application/json',
                    },
                });
                const data = await response.json();
                if (data.success) {
                    setJobs(jobs.map(j => j.id === job.id ? data.data : j));
                    alert('Job resent for approval successfully!');
                } else {
                    alert(data.message || 'Failed to resend job.');
                }
            } catch (error) {
                console.error('Error resending job:', error);
                alert('Failed to resend job.');
            }
        }
    };

    const handleStatusChange = async (job, newStatus) => {
        if (job.status === newStatus) return;
        
        // Determine action text based on new status
        let actionText;
        if (newStatus === 'active') {
            actionText = 'activate';
        } else if (newStatus === 'inactive') {
            actionText = 'deactivate';
        } else if (newStatus === 'closed') {
            actionText = 'close';
        } else {
            actionText = 'update';
        }
        
        if (confirm(`Are you sure you want to ${actionText} "${job.title}"?`)) {
            try {
                const response = await fetch(route('admin.api.jobs.toggle-status', job.id), {
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

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Job Listing" />
            
            <div className="min-h-screen bg-slate-100 p-4">
                <h1 className="text-4xl font-bold text-slate-800 mb-6">Listed Jobs Posts</h1>
                
                {/* Search and Post New Job Row */}
                <div className="mb-6 flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
                    {/* Search Bar */}
                    <div className="relative w-full sm:w-96">
                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg className="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input
                            type="text"
                            placeholder="Search by job title or company name..."
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

                    <Link
                        href={route("admin.job.posts.index")}
                        className="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white font-semibold rounded-xl hover:bg-blue-700 transition-colors shadow-md whitespace-nowrap"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                        </svg>
                        Post New Job
                    </Link>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    {loading ? (
                        <div className="col-span-full flex items-center justify-center py-12">
                            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                        </div>
                    ) : filteredJobs.length > 0 ? (
                        filteredJobs.map((job, idx) => (
                            <JobCard 
                                key={idx} 
                                job={job} 
                                onViewDetails={handleViewDetails}
                                onEdit={handleEdit}
                                onDelete={handleDelete}
                                onResend={handleResend}
                                onStatusChange={handleStatusChange}
                            />
                        ))
                    ) : (
                        <div className="col-span-full">
                            <div className="text-center py-12">
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
                                {!searchQuery && (
                                    <Link
                                        href={route("admin.job.posts.index")}
                                        className="inline-flex items-center px-4 py-2 mt-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700"
                                    >
                                        Create First Job Post
                                    </Link>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Job Details Modal */}
            {selectedJob && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                    <div className="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                        <div className="p-6">
                            <div className="flex justify-between items-start mb-6">
                                <div className="flex gap-4">
                                    <img 
                                        src={selectedJob.company_image || selectedJob.companyImage} 
                                        alt={selectedJob.company}
                                        className="w-16 h-16 rounded-xl object-cover"
                                    />
                                    <div>
                                        <h2 className="text-2xl font-bold text-gray-900">{selectedJob.title}</h2>
                                        <p className="text-lg text-gray-600">{selectedJob.company}</p>
                                        <span className={`inline-block mt-1 px-2 py-0.5 rounded-full text-[11px] font-medium capitalize ${
                                            selectedJob.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                                            selectedJob.status === 'active' ? 'bg-green-100 text-green-800' :
                                            selectedJob.status === 'declined' ? 'bg-red-100 text-red-800' :
                                            'bg-gray-100 text-gray-800'
                                        }`}>
                                            {selectedJob.status}
                                        </span>
                                    </div>
                                </div>
                                <button
                                    onClick={() => setSelectedJob(null)}
                                    className="text-gray-400 hover:text-gray-600 text-2xl"
                                >
                                    ×
                                </button>
                            </div>

                            <div className="grid grid-cols-2 gap-6 mb-6">
                                <div>
                                    <p className="text-sm text-gray-500 mb-1">Location</p>
                                    <p className="font-medium">{selectedJob.location}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500 mb-1">Experience</p>
                                    <p className="font-medium">{selectedJob.experience}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500 mb-1">Job Type</p>
                                    <p className="font-medium">{selectedJob.job_type || selectedJob.type}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500 mb-1">Salary</p>
                                    <p className="font-medium text-green-600">{selectedJob.salary}</p>
                                </div>
                            </div>

                            <div className="mb-6">
                                <h3 className="text-lg font-semibold mb-3">Skills Required</h3>
                                <div className="flex flex-wrap gap-2">
                                    {selectedJob.skills.map((skill, index) => (
                                        <span
                                            key={index}
                                            className="px-3 py-2 bg-blue-50 text-blue-700 rounded-lg text-sm"
                                        >
                                            {skill}
                                        </span>
                                    ))}
                                </div>
                            </div>

                            

                            <div className="mb-6">
                                <h3 className="text-lg font-semibold mb-3">Perks & Benefits</h3>
                                <div className="flex flex-wrap gap-2">
                                    {selectedJob.perks.map((perk, index) => (
                                        <span
                                            key={index}
                                            className="px-3 py-2 bg-green-50 text-green-700 rounded-lg text-sm"
                                        >
                                            {perk}
                                        </span>
                                    ))}
                                </div>
                            </div>

                            <div className="mb-6">
                                <h3 className="text-lg font-semibold mb-3">Key Responsibilities</h3>
                                <div className="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 border border-gray-200 dark:border-gray-600">
                                    <p className="text-gray-700 dark:text-gray-300 leading-relaxed">
                                        {selectedJob.key_responsibilities || selectedJob.keyResponsibilities || 'No key responsibilities specified.'}
                                    </p>
                                </div>
                            </div>

                            <div className="mb-6">
                                <h3 className="text-lg font-semibold mb-3">Qualifications</h3>
                                <div className="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 border border-gray-200 dark:border-gray-600">
                                    <p className="text-gray-700 dark:text-gray-300 leading-relaxed">
                                        {selectedJob.qualifications || 'No qualifications specified.'}
                                    </p>
                                </div>
                            </div>

                            <div className="mb-6">
                                <h3 className="text-lg font-semibold mb-3">Job Description</h3>
                                <div className="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 border border-gray-200 dark:border-gray-600">
                                    <p className="text-gray-700 dark:text-gray-300 leading-relaxed">
                                        {selectedJob.description || 'No description provided.'}
                                    </p>
                                </div>
                            </div>

                            <div className="flex justify-between items-center pt-4 border-t">
                                <div className="text-sm text-gray-500">
                                    <p>Posted: {selectedJob.created_at ? new Date(selectedJob.created_at).toLocaleDateString('en-US', { 
                                        month: 'short', 
                                        day: 'numeric', 
                                        year: 'numeric' 
                                    }) : (selectedJob.createdAt ? new Date(selectedJob.createdAt).toLocaleDateString('en-US', { 
                                        month: 'short', 
                                        day: 'numeric', 
                                        year: 'numeric' 
                                    }) : 'Just now')}</p>
                                    <p>Last Date to Apply: {selectedJob.last_date || selectedJob.lastDate || 'Not specified'}</p>
                                    <p>{selectedJob.applicants || 0} applicants</p>
                                </div>
                                <div className="flex gap-3">
                                    <button className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                        Apply Job
                                    </button>
                                    <button className="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                                        Save Job
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
