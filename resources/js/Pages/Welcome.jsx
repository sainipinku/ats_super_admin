import { Head, Link } from '@inertiajs/react';
import GuestLayout from "@/Layouts/GuestLayout";
import { useState, useEffect } from "react";
import { FaChartLine, FaUsers, FaFileAlt, FaClock } from "react-icons/fa";

export default function Welcome({ auth, laravelVersion, phpVersion }) {
    const [days, setDays] = useState(0);
    const [hours, setHours] = useState(0);
    const [minutes, setMinutes] = useState(0);
    const [seconds, setSeconds] = useState(0);

    // Set launch date (adjust as needed)
    const launchDate = new Date("2025-01-15T00:00:00").getTime();

    useEffect(() => {
        const timer = setInterval(() => {
            const now = new Date().getTime();
            const distance = launchDate - now;

            if (distance < 0) {
                clearInterval(timer);
                setDays(0);
                setHours(0);
                setMinutes(0);
                setSeconds(0);
            } else {
                setDays(Math.floor(distance / (1000 * 60 * 60 * 24)));
                setHours(Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)));
                setMinutes(Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60)));
                setSeconds(Math.floor((distance % (1000 * 60)) / 1000));
            }
        }, 1000);

        return () => clearInterval(timer);
    }, []);

    const features = [
        { icon: FaChartLine, title: "Analytics Dashboard", description: "Real-time recruitment metrics and insights" },
        { icon: FaUsers, title: "Candidate Management", description: "Streamlined applicant tracking and filtering" },
        { icon: FaFileAlt, title: "Automated Screening", description: "AI-powered resume parsing and evaluation" },
        { icon: FaClock, title: "Interview Scheduling", description: "Smart calendar integration and reminders" },
    ];

    return (
        <GuestLayout>
            <Head title="ATS - Coming Soon" />

            {/* Hero Section */}
            <div className="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-purple-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900">
                <div className="container mx-auto px-4 py-16">
                    {/* Logo/Brand */}
                    <div className="mb-12 text-center">
                        <h1 className="text-5xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent dark:from-indigo-400 dark:to-purple-400">
                            ATS
                        </h1>
                        <p className="mt-2 text-gray-600 dark:text-gray-400">Applicant Tracking System</p>
                    </div>

                    {/* Main Content */}
                    <div className="mx-auto max-w-4xl text-center">
                        <div className="mb-8">
                            <span className="inline-block rounded-full bg-indigo-100 px-4 py-2 text-sm font-semibold text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400">
                                Coming Soon
                            </span>
                        </div>

                        <h2 className="mb-4 text-4xl font-bold text-gray-900 dark:text-white md:text-5xl lg:text-6xl">
                            Revolutionizing Recruitment
                        </h2>
                        <p className="mx-auto mb-8 max-w-2xl text-xl text-gray-600 dark:text-gray-300">
                            The next-generation platform for seamless talent acquisition,
                            candidate management, and data-driven hiring decisions.
                        </p>

                        {/* Countdown Timer */}
                        <div className="mb-12">
                            <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                                <div className="rounded-lg bg-white p-4 shadow-lg dark:bg-gray-800">
                                    <div className="text-3xl font-bold text-indigo-600 dark:text-indigo-400 md:text-4xl">
                                        {String(days).padStart(2, '0')}
                                    </div>
                                    <div className="text-sm text-gray-600 dark:text-gray-400">Days</div>
                                </div>
                                <div className="rounded-lg bg-white p-4 shadow-lg dark:bg-gray-800">
                                    <div className="text-3xl font-bold text-indigo-600 dark:text-indigo-400 md:text-4xl">
                                        {String(hours).padStart(2, '0')}
                                    </div>
                                    <div className="text-sm text-gray-600 dark:text-gray-400">Hours</div>
                                </div>
                                <div className="rounded-lg bg-white p-4 shadow-lg dark:bg-gray-800">
                                    <div className="text-3xl font-bold text-indigo-600 dark:text-indigo-400 md:text-4xl">
                                        {String(minutes).padStart(2, '0')}
                                    </div>
                                    <div className="text-sm text-gray-600 dark:text-gray-400">Minutes</div>
                                </div>
                                <div className="rounded-lg bg-white p-4 shadow-lg dark:bg-gray-800">
                                    <div className="text-3xl font-bold text-indigo-600 dark:text-indigo-400 md:text-4xl">
                                        {String(seconds).padStart(2, '0')}
                                    </div>
                                    <div className="text-sm text-gray-600 dark:text-gray-400">Seconds</div>
                                </div>
                            </div>
                        </div>

                        {/* Email Signup Form */}
                        <div className="mx-auto mb-12 max-w-md">
                            <div className="flex gap-2">
                                <input
                                    type="email"
                                    placeholder="Enter your email for early access"
                                    className="flex-1 rounded-lg border border-gray-300 px-4 py-3 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                />
                                <button className="rounded-lg bg-indigo-600 px-6 py-3 font-semibold text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                    Notify Me
                                </button>
                            </div>
                            <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                Be the first to know when we launch. No spam, ever.
                            </p>
                        </div>
                    </div>

                    {/* Features Section */}
                    <div className="mt-20">
                        <h3 className="mb-12 text-center text-3xl font-bold text-gray-900 dark:text-white">
                            What to Expect
                        </h3>
                        <div className="grid gap-8 md:grid-cols-2 lg:grid-cols-4">
                            {features.map((feature, index) => {
                                const Icon = feature.icon;
                                return (
                                    <div
                                        key={index}
                                        className="rounded-xl bg-white p-6 text-center shadow-lg transition transform hover:-translate-y-1 dark:bg-gray-800"
                                    >
                                        <div className="mb-4 inline-block rounded-lg bg-indigo-100 p-3 dark:bg-indigo-900/30">
                                            <Icon className="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                                        </div>
                                        <h4 className="mb-2 text-xl font-semibold text-gray-900 dark:text-white">
                                            {feature.title}
                                        </h4>
                                        <p className="text-gray-600 dark:text-gray-400">
                                            {feature.description}
                                        </p>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Footer */}
                    <div className="mt-20 border-t border-gray-200 pt-8 text-center dark:border-gray-700">
                        <p className="text-gray-600 dark:text-gray-400">
                            © {new Date().getFullYear()} ATS. All rights reserved.
                        </p>
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}
