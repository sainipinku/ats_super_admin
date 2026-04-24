import { useAlerts } from "@/Components/Alerts";
import ApplicationLogo from "@/Components/ApplicationLogo";
import NavLink from "@/Components/NavLink";
import { useHelpers } from "@/Components/Helpers";
import { Link, usePage } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";
import { Toaster } from "react-hot-toast";
import { FaSun } from "react-icons/fa6";

import UserDropdown from "./UserDropdown";
import Sidebar from "./Sidebar";
import { route } from "ziggy-js";

export default function AuthenticatedLayout({ header, children }) {
    const { hasPermissionLike, hasPermission, hasAnyPermission } = useHelpers();
    const user = usePage().props;

    const { successAlert, errorAlert, warningAlert, infoAlert } = useAlerts();
    const { flash, errors, messages } = usePage().props;

    const [darkMode, setDarkMode] = useState(
        () =>
            localStorage.theme === "dark" ||
            (!("theme" in localStorage) &&
                window.matchMedia("(prefers-color-scheme: dark)").matches)
    );

    const [dropdownOpen, setDropdownOpen] = useState(false);
    const dropdownRef = useRef(null);
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [settings, setSettings] = useState(null);

    useEffect(() => {
        if (flash?.success) successAlert(flash.success);
        if (flash?.error) errorAlert(flash.error);
        if (flash?.warning) warningAlert(flash.warning);
        if (flash?.info) infoAlert(flash.info);

        if (messages?.envelopes?.length > 0) {
            messages.envelopes.forEach(({ type, message }) => {
                switch (type) {
                    case "success":
                        successAlert(message);
                        break;
                    case "error":
                        errorAlert(message);
                        break;
                    case "warning":
                        warningAlert(message);
                        break;
                    case "info":
                        infoAlert(message);
                        break;
                    default:
                        console.warn("Unknown message type:", type);
                }
            });
        }
    }, [messages, flash, errors]);

    useEffect(() => {
        const root = window.document.documentElement;
        if (darkMode) {
            root.classList.add("dark");
            localStorage.setItem("theme", "dark");
        } else {
            root.classList.remove("dark");
            localStorage.setItem("theme", "light");
        }
    }, [darkMode]);

    useEffect(() => {
        function handleClickOutside(event) {
            if (
                dropdownRef.current &&
                !dropdownRef.current.contains(event.target)
            ) {
                setDropdownOpen(false);
            }
        }

        document.addEventListener("mousedown", handleClickOutside);
        return () =>
            document.removeEventListener("mousedown", handleClickOutside);
    }, []);

    const toggleSidebar = () => setSidebarOpen((prev) => !prev);
    const closeSidebar = () => setSidebarOpen(false);

    return (
        <>
            <Sidebar isOpen={sidebarOpen} onClose={closeSidebar} />

            <div className="min-h-screen mainbg">
                <nav className="headerbg fixed top-0 left-0 right-0 z-50 py-[15px] px-[15px] rounded-[10px] print:hidden">
                    <div className="navbg rounded-[10px] border border-[1px] borderbx">
                        <div className="mx-auto max-w-full px-[8px] py-[0]">
                            <div className="flex h-16 justify-between items-center border-gray-200 dark:border-gray-700">
                                
                                {/* Left Section */}
                                <div className="flex gap-[45px] items-center">
                                    <button
                                        onClick={toggleSidebar}
                                        className="flex xl:hidden items-center justify-center bg-white dark:bg-[#61CC681A] w-[48px] h-[38px] border border-[#0000001A] dark:border-[#61CC681A] rounded-[8px]"
                                    >
                                        <svg
                                            className="w-6 h-6"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                strokeWidth={2}
                                                d="M4 6h16M4 12h16M4 18h16"
                                            />
                                        </svg>
                                    </button>

                                    <div className="flex shrink-0 items-center">
                                        <Link href={route("admin.dashboard")}>
                                            <ApplicationLogo
                                                props={settings}
                                                className="block w-auto fill-current text-gray-800 dark:text-gray-200"
                                            />
                                        </Link>
                                    </div>

                                    <div className="hidden xl:flex items-center gap-[25px] xl:gap-[40px]">
                                        <NavLink
                                            href={route("admin.dashboard")}
                                            active={route().current("admin.dashboard")}
                                        >
                                            Dashboard
                                        </NavLink>

                                        <NavLink
                                            href={route("admin.job.posts.listing")}
                                            active={route().current("admin.job.posts.listing")}
                                        >
                                            Job Listing
                                        </NavLink>

                                        <NavLink
                                            href={route("admin.job.applications.index")}
                                            active={route().current("admin.job.applications.index")}
                                        >
                                            Job Applicants
                                        </NavLink>

                                        <NavLink
                                            href={route("admin.members.dashboard")}
                                            active={
                                                route().current("admin.members.dashboard") ||
                                                route().current("admin.members.details")
                                            }
                                        >
                                            Members
                                        </NavLink>
                                    </div>
                                </div>

                                {/* Right Section */}
                                <div className="flex items-center gap-2 relative">
                                    <button
                                        onClick={() => setDarkMode(!darkMode)}
                                        className="flex items-center justify-center bg-white dark:bg-[#61CC681A] w-[48px] h-[38px] border border-[#0000001A] dark:border-[#61CC681A] rounded-[8px]"
                                    >
                                        {darkMode ? (
                                            <FaSun
                                                size={18}
                                                className="text-gray-200"
                                            />
                                        ) : (
                                            <svg width="24"height="24"viewBox="0 0 24 24"fill="none">
                                                <path
                                                    d="M12.0015 2L14.6365 4.635H19.365V9.363L22 11.998L19.365 14.637V19.365H14.637L12.002 22L9.363 19.365H4.635V14.637L2 11.9985L4.635 9.3635V4.635H9.363L12.0015 2Z"
                                                    stroke="currentColor"
                                                    strokeWidth="2"
                                                    strokeMiterlimit="10"
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                />
                                            </svg>
                                        )}
                                    </button>

                                    <div className="relative" ref={dropdownRef}>
                                        <button
                                            onClick={() =>
                                                setDropdownOpen(!dropdownOpen)
                                            }
                                            className="w-[40px] h-[40px] rounded-[70px] bg-none p-0 mt-[5px]"
                                        >
                                            <img
                                                src={
                                                    user?.auth?.user
                                                        ?.profile_photo_url
                                                }
                                                alt="User"
                                            />
                                        </button>

                                        <UserDropdown open={dropdownOpen} />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </nav>

                {header && (
                    <header className="bg-white shadow dark:bg-gray-800 mt-16">
                        <div className="mx-auto max-w-full px-4 py-6 sm:px-6 lg:px-8">
                            {header}
                        </div>
                    </header>
                )}

                <main className="mt-8">{children}</main>

                <Toaster position="top-right" reverseOrder={false} gutter={8} />
            </div>
        </>
    );
}
