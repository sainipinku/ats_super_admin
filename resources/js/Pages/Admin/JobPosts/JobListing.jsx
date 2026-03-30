import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '../Layouts/AuthenticatedLayout';
import LocationInput from '../../../Components/LocationInput';

// Reusable JobCard Component
const JobCard = ({ job, onViewDetails, onEdit, onDelete }) => {
    return (
        <div className={`bg-white rounded-3xl shadow-sm p-4 border min-h-[240px] relative ${
            job.active ? 'border-blue-300 ring-2 ring-blue-200' : 'border-slate-200'
        }`}>
            {/* Action Icons - Outside Card */}
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

            <div className="flex items-start justify-between mb-3">
                <div className="flex gap-3">
                    <img src={job.companyImage} alt={job.title} className="w-12 h-12 rounded-xl object-cover" />
                    <div>
                        <h2 className="text-[18px] font-semibold text-slate-900">{job.title}</h2>
                        <p className="text-slate-500 text-[14px]">{job.company}</p>
                    </div>
                </div>
                <span className="px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-sm font-medium">
                    {job.type}
                </span>
            </div>

            <div className="space-y-2 text-slate-600 text-[14px]">
                <p>📍 {job.location}</p>
                <p>💼 {job.experience}</p>
            </div>

            <p className="text-[20px] font-bold text-emerald-600 my-2 leading-tight">{job.salary}</p>

            <div className="flex flex-wrap gap-2 mb-3">
                {job.skills.slice(0, 3).map((skill) => (
                    <span key={skill} className="px-3 py-2 rounded-lg bg-blue-50 text-blue-700 text-sm">
                        {skill}
                    </span>
                ))}
                {job.skills.length > 3 && (
                    <span className="px-3 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm">
                        +{job.skills.length - 3} more
                    </span>
                )}
            </div>

            <div className="mb-2">
                <p className="text-[13px] font-medium text-slate-700 mb-1">Perks:</p>
                <p className="text-[12px] text-slate-600 leading-5">
                    {job.perks?.join(' • ') || 'Flexible working hours • Health Insurance • Learning opportunities'}
                </p>
            </div>

            <hr className="my-2" />

            <div className="flex justify-between items-center text-[12px] text-slate-500 mb-3">
                <p>Posted {job.createdAt ? new Date(job.createdAt).toLocaleDateString('en-US', { 
                    month: 'short', 
                    day: 'numeric', 
                    year: 'numeric' 
                }) : 'Just now'} <span className="mx-2">•</span> {job.applicants || 0} applicants</p>
            </div>

            <button 
                onClick={() => onViewDetails(job)}
                className="w-full py-2 rounded-xl text-blue-600 text-[14px] font-semibold hover:bg-blue-50 transition-colors"
            >
                👁 View Details
            </button>
        </div>
    );
};

export default function JobListing({ auth }) {
    const [jobs, setJobs] = useState([]);

    // Load jobs from localStorage on component mount
    React.useEffect(() => {
        console.log('JobListing component mounted');
        const savedJobs = JSON.parse(localStorage.getItem('jobPosts') || '[]');
        console.log('Saved jobs from localStorage:', savedJobs);
        
        // If no saved jobs, use default static jobs
        if (savedJobs.length === 0) {
            console.log('No saved jobs, loading default jobs');
            const defaultJobs = [
                {
                    id: 1,
                    title: 'Senior Developer',
                    company: 'Goscopify',
                    location: 'Jaipur',
                    type: 'Full Time',
                    experience: '3 to 5 yr',
                    salary: '60,000 - 90,000 / month',
                    skills: ['HTML', 'CSS', 'Bootstrap'],
                    perks: ['Friendly work environment', 'Opportunity to learn and grow', 'Flexible working hours'],
                    applicants: 0,
                    companyImage: 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?q=80&w=200&auto=format&fit=crop',
                    description: 'We are looking for a talented Senior Developer...',
                    createdAt: new Date().toISOString(),
                    lastDate: '2024-12-31',
                    active: true
                },
                {
                    id: 2,
                    title: 'Senior Software Engineer',
                    company: 'Tech Solutions Inc.',
                    location: 'Bangalore, Karnataka',
                    type: 'Full Time',
                    experience: '3-5 years',
                    salary: '₹12,00,000 - ₹15,00,000/year',
                    skills: ['React', 'Node.js', 'MongoDB'],
                    perks: ['Health Insurance', 'Work from Home', 'Free Food'],
                    applicants: 45,
                    companyImage: 'https://images.unsplash.com/photo-1504384308090-c894fdcc538d?q=80&w=200&auto=format&fit=crop',
                    description: 'Looking for an experienced Software Engineer...',
                    createdAt: new Date(Date.now() - 2 * 24 * 60 * 60 * 1000).toISOString(),
                    lastDate: '2024-12-25',
                    active: false
                },
                {
                    id: 3,
                    title: 'Frontend Developer',
                    company: 'Digital Agency',
                    location: 'Mumbai, Maharashtra',
                    type: 'Remote',
                    experience: '2-4 years',
                    salary: '₹8,00,000 - ₹12,00,000/year',
                    skills: ['Vue.js', 'TypeScript', 'CSS'],
                    perks: ['Flexible Hours', 'Gym Access', 'Learning Budget'],
                    applicants: 128,
                    companyImage: 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?q=80&w=200&auto=format&fit=crop',
                    description: 'Join our frontend team...',
                    createdAt: new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString(),
                    lastDate: '2024-12-20',
                    active: false
                },
                {
                    id: 4,
                    title: 'Backend Developer',
                    company: 'FinTech Solutions',
                    location: 'Pune, Maharashtra',
                    salary: "₹12,00,000 - ₹18,00,000/year",
                    skills: ["Java", "Spring Boot", "PostgreSQL"],
                    perks: ["Stock options", "Annual bonus"],
                    type: "Full Time",
                    applicants: 18,
                    companyImage: "https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=200&h=200&fit=crop&crop=face&auto=format",
                    description: "Looking for an experienced Backend Developer to join our FinTech team. You'll develop robust APIs and work with database systems. Experience with Java, Spring Boot, and financial systems is highly valued.",
                    createdAt: new Date().toISOString()
                },
                {
                    id: 5,
                    title: "Mobile App Developer",
                    company: "Gaming Studio",
                    location: "Chennai",
                    experience: "2-4 years",
                    salary: "₹7,00,000 - ₹11,00,000/year",
                    skills: ["React Native", "Flutter", "Firebase"],
                    perks: ["Game room", "Flexible timing"],
                    type: "Remote",
                    applicants: 15,
                    companyImage: "https://images.unsplash.com/photo-1519074019808-3bc74e2a3d2A?w=200&h=200&fit=crop&crop=face&auto=format",
                    description: "Exciting opportunity for a Mobile App Developer in the gaming industry! You'll create engaging mobile games using React Native and Flutter. Experience with Firebase and mobile game development is a plus.",
                    createdAt: new Date().toISOString()
                }
            ];
            console.log('Setting default jobs:', defaultJobs);
            setJobs(defaultJobs);
        } else {
            console.log('Loading saved jobs:', savedJobs);
            setJobs(savedJobs);
        }
    }, []);

    const [selectedJob, setSelectedJob] = useState(null);

    const handleViewDetails = (job) => {
        setSelectedJob(job);
    };

    const handleEdit = (job) => {
        console.log("Edit job:", job);
        // Static - no backend logic
    };

    const handleDelete = (job) => {
        if (confirm(`Are you sure you want to delete "${job.title}" job post?`)) {
            // Remove from localStorage
            const existingJobs = JSON.parse(localStorage.getItem('jobPosts') || '[]');
            const updatedJobs = existingJobs.filter(j => j.id !== job.id);
            localStorage.setItem('jobPosts', JSON.stringify(updatedJobs));
            
            // Update state
            setJobs(updatedJobs);
            
            alert('Job post deleted successfully!');
        }
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Job Listing" />
            
            <div className="min-h-screen bg-slate-100 p-4">
                <h1 className="text-4xl font-bold text-slate-800 mb-6">Listed Jobs Posts</h1>

                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    {jobs.length > 0 ? (
                        jobs.map((job, idx) => (
                            <JobCard 
                                key={idx} 
                                job={job} 
                                onViewDetails={handleViewDetails}
                                onEdit={handleEdit}
                                onDelete={handleDelete}
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
                                <h3 className="text-lg font-medium text-gray-900 mb-2">No Job Listings Found</h3>
                                <p className="text-gray-500">No job posts have been created yet.</p>
                                <Link
                                    href={route("admin.job.posts.index")}
                                    className="inline-flex items-center px-4 py-2 mt-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700"
                                >
                                    Create First Job Post
                                </Link>
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
                                        src={selectedJob.companyImage} 
                                        alt={selectedJob.company}
                                        className="w-16 h-16 rounded-xl object-cover"
                                    />
                                    <div>
                                        <h2 className="text-2xl font-bold text-gray-900">{selectedJob.title}</h2>
                                        <p className="text-lg text-gray-600">{selectedJob.company}</p>
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
                                    <p className="font-medium">📍 {selectedJob.location}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500 mb-1">Experience</p>
                                    <p className="font-medium">💼 {selectedJob.experience}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500 mb-1">Job Type</p>
                                    <p className="font-medium">{selectedJob.type}</p>
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
                                <h3 className="text-lg font-semibold mb-3">Job Description</h3>
                                <p className="text-gray-700 leading-relaxed">
                                    {selectedJob.description || 'No description provided.'}
                                </p>
                            </div>

                            <div className="flex justify-between items-center pt-4 border-t">
                                <div className="text-sm text-gray-500">
                                    <p>Posted: {selectedJob.createdAt ? new Date(selectedJob.createdAt).toLocaleDateString('en-US', { 
                                        month: 'short', 
                                        day: 'numeric', 
                                        year: 'numeric' 
                                    }) : 'Just now'}</p>
                                    <p>Last Date to Apply: {selectedJob.lastDate || 'Not specified'}</p>
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
