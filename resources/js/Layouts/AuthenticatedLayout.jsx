import { useAlerts } from '@/Components/Alerts';
import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import { useHelpers } from '@/Components/Helpers';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import RoleUsersDynamicDropdown from '@/Components/RoleUsersDynamicDropdown';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Toaster } from 'react-hot-toast';
import { IoMoon } from "react-icons/io5";
import { FaChevronDown, FaSun } from "react-icons/fa6";
import { FaBell } from "react-icons/fa";
import { IoSettings } from "react-icons/io5";
import { MdDashboard } from 'react-icons/md';
import { HiDesktopComputer } from "react-icons/hi";
import { FaUserGraduate } from "react-icons/fa";
import { FaFile } from "react-icons/fa6";
import { FaImages } from "react-icons/fa6";
import { RiAccountBoxFill } from "react-icons/ri";
import { MdContactSupport } from "react-icons/md";
import { GiHamburgerMenu } from "react-icons/gi";
import { IoCloseSharp } from "react-icons/io5";
import Sidebar from '@/Pages/Admin/Layouts/Sidebar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"



export default function AuthenticatedLayout({ header, children }) {
    const { hasPermissionLike, hasPermission, hasAnyPermission } = useHelpers();
    const user = usePage().props.auth.user;
    const permissions = usePage().props.auth?.permissions ?? [];

    const { successAlert, errorAlert, warningAlert, infoAlert } = useAlerts();

    const { ziggy, flash, errors, messages } = usePage().props;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    useEffect(() => {
        if (errors && !isValidationError(errors)) {
            Object.entries(errors).forEach(([key, value]) => {
                errorAlert(value);
            });
        }

        // 1. Standard Laravel flash messages
        if (flash?.success) successAlert(flash.success);
        if (flash?.error) errorAlert(flash.error);
        if (flash?.warning) warningAlert(flash.warning);
        if (flash?.info) infoAlert(flash.info);

        // 2. Laravel Flasher messages
        if (messages?.envelopes?.length > 0) {
            messages.envelopes.forEach(({ type, message }) => {
                switch (type) {
                    case 'success':
                        successAlert(message);
                        break;
                    case 'error':
                        errorAlert(message);
                        break;
                    case 'warning':
                        warningAlert(message);
                        break;
                    case 'info':
                        infoAlert(message);
                        break;
                    default:
                        console.warn('Unknown message type:', type);
                }
            });
        }
    }, [messages, flash, errors]);
function isValidationError(errors) {
    return errors &&
           (errors.message == 'Validation failed' ||
            errors.message == 'The given data was invalid' ||
            Object.values(errors).some(error => Array.isArray(error)));
}
    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);


    // for dark and light mode:
    const [darkMode, setDarkMode] = useState(() =>
        localStorage.theme == "dark" ||
        (!("theme" in localStorage) &&
            window.matchMedia("(prefers-color-scheme: dark)").matches)
    );

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

    const [openMenu, setOpenMenu] = useState(null)

    const handleMouseEnter = (menu) => setOpenMenu(menu)
    const handleMouseLeave = () => setOpenMenu(null)


    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const handleToggle = () => {
        setIsMenuOpen(prev => !prev);
    };
    return (
        <>
        <div className="min-h-screen bg-gray-100 dark:bg-gray-900">
            {/* Sidebar */}
            <Sidebar isOpen={sidebarOpen} onClose={() => setSidebarOpen(false)} />
            
            {/* Main Content */}
            <div className={`transition-all duration-300 ${sidebarOpen ? 'ml-[288px]' : 'ml-0'}`}>
                <nav className="border-b border-gray-100 bg-white dark:border-gray-700 dark:bg-gray-800 fixed top-0 left-0 right-0 z-50 print:hidden">
                <div className="mx-auto max-w-full px-4 sm:px-6 lg:px-8 ">
                    <div className="flex h-16 justify-between border-gray-200 dark:border-gray-700 ">
                        <div className="flex">
                            {/* Sidebar Toggle Button */}
                            <button
                                onClick={() => setSidebarOpen(!sidebarOpen)}
                                className="p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500"
                            >
                                <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            </button>
                            
                            <div className="flex shrink-0 items-center">
                                <Link href="/dashboard">
                                    <ApplicationLogo className="block w-auto fill-current text-gray-800 dark:text-gray-200" />
                                </Link>
                            </div>

                            {/* <div className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex"> */}
                                {/* <NavLink
                                    href={route('dashboard')}
                                    active={route().current('dashboard')}
                                >
                                    Dashboard
                                </NavLink> */}

                                {/* {hasAnyPermission(permissions, ['create.branch', 'edit.branch', 'view.branch', 'delete.branch']) && (
                                    <NavLink href={route('branch.list')} active={route().current('branch.*')}>
                                        Branches
                                    </NavLink>
                                )} */}

                                {/* {hasAnyPermission(permissions, ['create.role', 'edit.role', 'view.role', 'delete.role']) && (
                                    <NavLink href={route('role.list')} active={route().current('role.*')}>
                                        Designations
                                    </NavLink>
                                )} */}


                                {/* {user?.branch_id && (
                                    <div className="hidden sm:ms-6 sm:flex sm:items-center" >
                                        <div className="relative ms-3">
                                            <RoleUsersDynamicDropdown />
                                        </div>
                                    </div>
                                )} */}

                                {/* {hasAnyPermission(permissions, ['create.card', 'edit.card', 'view.card', 'delete.card']) && (
                                    <NavLink
                                        href={route('card.list')}
                                        active={route().current('card.list') || route().current('card.add') || route().current('card.import')}
                                    >
                                        Cards
                                    </NavLink>
                                )} */}

                                {/* <NavLink href={route('card.order.list')} active={route().current('card.order.*')}>
                                    Orders
                                </NavLink> */}

                                {/* {hasAnyPermission(permissions, ['create.aadhar_verify', 'edit.aadhar_verify', 'view.aadhar_verify', 'delete.aadhar_verify']) && (
                                    <NavLink href={route('aadhar.list')} active={route().current('aadhar.*')}>
                                        Aadhar Verification
                                    </NavLink>
                                )} */}
                            {/* </div> */}
                        </div>


                        {/* // for the top right content (Dropdown and theme button) */}
                        <div className="hidden sm:ms-6 sm:flex sm:items-center gap-2 relative" >

                            {/* Bell Icon */}
                            <div className='md:block hidden' onMouseEnter={() => handleMouseEnter("bell")} onMouseLeave={handleMouseLeave}  >
                                <DropdownMenu open={openMenu == "bell"} onOpenChange={() => { }}>
                                    <DropdownMenuTrigger asChild>
                                        <button className="flex items-center  bg-gray-200 dark:bg-gray-700 px-4 py-2 rounded-xl text-xl text-gray-800 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                                            <FaBell />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent className="w-48">
                                        <DropdownMenuLabel>New One...</DropdownMenuLabel>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem>new login</DropdownMenuItem>
                                        <DropdownMenuItem>KYC complete</DropdownMenuItem>
                                        <DropdownMenuItem>Registration was success</DropdownMenuItem>
                                        <DropdownMenuItem>Delivered</DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>


                            {/* Dropdown content */}
                            <div className="relative ms-3" onMouseEnter={() => handleMouseEnter("icn")} onMouseLeave={handleMouseLeave} >
                                <Dropdown open={openMenu == "icn"} onOpenChange={() => { }}>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className="flex items-center gap-2 bg-gray-200 dark:bg-gray-700 px-4 py-2 rounded-xl text-sm hover:bg-gray-300 dark:hover:bg-gray-600 transition text-gray-800 dark:text-gray-200"
                                            >
                                                {user.name}

                                                <FaChevronDown />
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        <Dropdown.Link
                                            href={route('profile.edit')}
                                        >
                                            Profile
                                        </Dropdown.Link>
                                        {/* <Dropdown.Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </Dropdown.Link> */}
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>


                            {/* Dark/Light Toggle */}
                            <button
                                onClick={() => setDarkMode(!darkMode)}
                                className=" items-center gap-2 bg-gray-200 dark:bg-gray-700 px-4 py-2 rounded-xl text-sm hover:bg-gray-300 dark:hover:bg-gray-600 transition hidden md:block "
                            >
                                {darkMode ? (
                                    <FaSun size={18} className="text-gray-200" />
                                ) : (
                                    <IoMoon size={18} className="text-gray-800" />
                                )}
                                <span className="text-gray-800 dark:text-gray-100">
                                    {darkMode ? "Light" : "Dark"}
                                </span>
                            </button>


                            {/* Button for setting */}
                            <div className=' items-center gap-2 bg-gray-200 dark:bg-gray-700 px-4 py-2 rounded-xl text-xl text-gray-800 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600 transition cursor-pointer md:block hidden '>
                                <IoSettings />
                            </div>
                        </div>

                        <div className="-me-2 flex items-center sm:hidden gap-2">
                            <div className='flex items-center gap-2 bg-gray-200 dark:bg-gray-700 px-1 rounded-xl text-xl text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600 transition cursor-pointer '>

                            </div>

                            <button
                                onClick={() =>
                                    setShowingNavigationDropdown(
                                        (previousState) => !previousState,
                                    )
                                }
                                className="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none dark:text-gray-500 dark:hover:bg-gray-900 dark:hover:text-gray-400 dark:focus:bg-gray-900 dark:focus:text-gray-400"
                            >
                                <GiHamburgerMenu className='h-6 w-6 text-gray-700 dark:text-gray-200' />

                            </button>
                        </div>
                    </div>


                    {/* second header  */}

                    {/* <div className='items-center justify-start gap-2 md:block md:flex hidden '>
                        <div className='flex items-center gap-2 py-2 text-gray-800 dark:text-gray-200' onMouseEnter={() => handleMouseEnter("dashboard")} onMouseLeave={handleMouseLeave} >
                            <DropdownMenu open={openMenu == "dashboard"} onOpenChange={() => { }}>
                                <DropdownMenuTrigger asChild>
                                    <button className="flex items-center gap-1 px-3 py-2 rounded-md dark:bg-gray-800 text-sm font-medium text-gray-800 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-600 transition">
                                        <HiDesktopComputer className="w-4 h-4" />
                                        Dashboard
                                        <FaChevronDown className="w-3 h-3" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent className="w-48">
                                    <DropdownMenuLabel>My Account</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem>Profile</DropdownMenuItem>
                                    <DropdownMenuItem>Billing</DropdownMenuItem>
                                    <DropdownMenuItem>Team</DropdownMenuItem>
                                    <DropdownMenuItem>Subscription</DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>



                        <div className='flex items-center gap-2 py-2 text-gray-800 dark:text-gray-200' onMouseEnter={() => handleMouseEnter("advocate")} onMouseLeave={handleMouseLeave}>
                            <DropdownMenu open={openMenu == "advocate"} onOpenChange={() => { }}>
                                <DropdownMenuTrigger asChild>
                                    <button className="flex items-center gap-1 px-3 py-2 rounded-md dark:bg-gray-800 text-sm font-medium text-gray-800 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-600 transition">
                                        <FaUserGraduate className="w-4 h-4" />
                                        Advocate
                                        <FaChevronDown className="w-3 h-3" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent className="w-48">
                                    <DropdownMenuLabel>Advocate Management</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem>View All Advocates</DropdownMenuItem>
                                    <DropdownMenuItem>Add New Advocate</DropdownMenuItem>
                                    <DropdownMenuItem>Verified Advocates</DropdownMenuItem>
                                    <DropdownMenuItem>Unverified Advocates</DropdownMenuItem>
                                    <DropdownMenuItem>Aadhaar Verification</DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>


                        <div className='flex items-center gap-2 py-2 text-gray-800 dark:text-gray-200' onMouseEnter={() => handleMouseEnter("reports")} onMouseLeave={handleMouseLeave}>
                            <DropdownMenu open={openMenu == "reports"} onOpenChange={() => { }}>
                                <DropdownMenuTrigger asChild>
                                    <button className="flex items-center gap-1 px-3 py-2 rounded-md dark:bg-gray-800 text-sm font-medium text-gray-800 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-600 transition">
                                        <FaFile className="w-4 h-4" />
                                        Reports
                                        <FaChevronDown className="w-3 h-3" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent className="w-48">
                                    <DropdownMenuLabel>Reports & Analytics</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem>Advocate Stats</DropdownMenuItem>
                                    <DropdownMenuItem>Card Printing Reports</DropdownMenuItem>
                                    <DropdownMenuItem>Aadhaar Verification Stats</DropdownMenuItem>
                                    <DropdownMenuItem>Delivery Status Overview</DropdownMenuItem>
                                    <DropdownMenuItem>Sticker Distribution</DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>


                        <div className='flex items-center gap-2 py-2 text-gray-800 dark:text-gray-200' onMouseEnter={() => handleMouseEnter("stickers")} onMouseLeave={handleMouseLeave}>
                            <DropdownMenu open={openMenu == "stickers"} onOpenChange={() => { }}>
                                <DropdownMenuTrigger asChild>
                                    <button className="flex items-center gap-1 px-3 py-2 rounded-md dark:bg-gray-800 text-sm font-medium text-gray-800 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-600 transition">
                                        <FaImages className="w-4 h-4" />
                                        Stickers
                                        <FaChevronDown className="w-3 h-3" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent className="w-48">
                                    <DropdownMenuLabel>Sticker Management</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem>All Stickers</DropdownMenuItem>
                                    <DropdownMenuItem> Car Stickers</DropdownMenuItem>
                                    <DropdownMenuItem>Bike Stickers</DropdownMenuItem>
                                    <DropdownMenuItem>Printed Stickers</DropdownMenuItem>
                                    <DropdownMenuItem>Remaining Stickers</DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>



                        <div className='flex items-center gap-2 py-2 text-gray-800 dark:text-gray-200' onMouseEnter={() => handleMouseEnter("account")} onMouseLeave={handleMouseLeave}>
                            <DropdownMenu open={openMenu == "account"} onOpenChange={() => { }}>
                                <DropdownMenuTrigger asChild>
                                    <button className="flex items-center gap-1 px-3 py-2 rounded-md dark:bg-gray-800 text-sm font-medium text-gray-800 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-600 transition">
                                        <RiAccountBoxFill className="w-4 h-4" />
                                        Account
                                        <FaChevronDown className="w-3 h-3" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent className="w-48">
                                    <DropdownMenuLabel>Account & Settings</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem>Profile</DropdownMenuItem>
                                    <DropdownMenuItem> Change Password</DropdownMenuItem>
                                    <DropdownMenuItem>Settings</DropdownMenuItem>
                                    <DropdownMenuItem>Dark/Light Mode</DropdownMenuItem>
                                    <DropdownMenuItem>Logout</DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>



                        <div className='flex items-center gap-2 py-2 text-gray-800 dark:text-gray-200' onMouseEnter={() => handleMouseEnter("support")} onMouseLeave={handleMouseLeave}>
                            <DropdownMenu open={openMenu == "support"} onOpenChange={() => { }}>
                                <DropdownMenuTrigger asChild>
                                    <button className="flex items-center gap-1 px-3 py-2 rounded-md dark:bg-gray-800 text-sm font-medium text-gray-800 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-600 transition">
                                        <MdContactSupport className="w-4 h-4" />
                                        Support
                                        <FaChevronDown className="w-3 h-3" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent className="w-48">
                                    <DropdownMenuLabel>Help & Support</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem>How to use the portal</DropdownMenuItem>
                                    <DropdownMenuItem>Contact Admin</DropdownMenuItem>
                                    <DropdownMenuItem>Report an issue</DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>

                    <div className='block md:hidden my-4 flex items-center justify-between'>
                        <button onClick={handleToggle} >{isMenuOpen ? <IoCloseSharp className='h-6 w-6 text-gray-700 dark:text-gray-200' /> : <GiHamburgerMenu className='h-6 w-6 text-gray-700 dark:text-gray-200' />}</button>
                        <div className='flex items-center gap-2'>
                            <button onClick={() => setDarkMode(!darkMode)} className="flex items-center gap-2 bg-gray-200 dark:bg-gray-700 px-4 py-2 rounded-xl text-sm hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                                {darkMode ? (
                                    <FaSun size={18} className="text-gray-200" />
                                ) : (
                                    <IoMoon size={18} className="text-gray-800" />
                                )}
                                <span className="text-gray-800 dark:text-gray-100">
                                    {darkMode ? "Light" : "Dark"}
                                </span>
                            </button>


                            <div className='md:hidden block'  >
                                <DropdownMenu >
                                    <DropdownMenuTrigger asChild>
                                        <button className="flex items-center  bg-gray-200 dark:bg-gray-700 px-4 py-2 rounded-xl text-xl text-gray-800 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                                            <FaBell />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent className="w-48">
                                        <DropdownMenuLabel>New One...</DropdownMenuLabel>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem>new login</DropdownMenuItem>
                                        <DropdownMenuItem>KYC complete</DropdownMenuItem>
                                        <DropdownMenuItem>Registration was success</DropdownMenuItem>
                                        <DropdownMenuItem>Delivered</DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>
                    </div>
                    <div className='flex items-center gap-2 flex-col w-full justify-center  block md:hidden'>

                        {isMenuOpen && (
                            <div className="flex items-center gap-2 flex-col w-full justify-center  block md:hidden">
                                <div className='w-full' >
                                    <DropdownMenu >
                                        <DropdownMenuTrigger asChild>
                                            <button className="w-full flex items-center justify-center gap-1 px-3 py-2 rounded-md dark:bg-gray-800 text-sm font-medium text-gray-800 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-600 transition">
                                                <HiDesktopComputer className="w-4 h-4" />
                                                Dashboard
                                                <FaChevronDown className="w-3 h-3" />
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent className="w-48">
                                            <DropdownMenuLabel>My Account</DropdownMenuLabel>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem>Profile</DropdownMenuItem>
                                            <DropdownMenuItem>Billing</DropdownMenuItem>
                                            <DropdownMenuItem>Team</DropdownMenuItem>
                                            <DropdownMenuItem>Subscription</DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>

                                <div className='w-full' >
                            <DropdownMenu >
                                <DropdownMenuTrigger asChild>
                                    <button className="w-full flex items-center justify-center gap-1 px-3 py-2 rounded-md dark:bg-gray-800 text-sm font-medium text-gray-800 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-600 transition">
                                        <FaUserGraduate className="w-4 h-4" />
                                        Advocate
                                        <FaChevronDown className="w-3 h-3" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent className="w-48">
                                    <DropdownMenuLabel>Advocate Management</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem>View All Advocates</DropdownMenuItem>
                                    <DropdownMenuItem>Add New Advocate</DropdownMenuItem>
                                    <DropdownMenuItem>Verified Advocates</DropdownMenuItem>
                                    <DropdownMenuItem>Unverified Advocates</DropdownMenuItem>
                                    <DropdownMenuItem>Aadhaar Verification</DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>


                        <div className='w-full flex items-center gap-2 py-2 text-gray-800 dark:text-gray-200' >
                            <DropdownMenu >
                                <DropdownMenuTrigger asChild>
                                    <button className="w-full flex items-center justify-center gap-1 px-3 py-2 rounded-md dark:bg-gray-800 text-sm font-medium text-gray-800 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-600 transition">
                                        <FaFile className="w-4 h-4" />
                                        Reports
                                        <FaChevronDown className="w-3 h-3" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent className="w-48">
                                    <DropdownMenuLabel>Reports & Analytics</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem>Advocate Stats</DropdownMenuItem>
                                    <DropdownMenuItem>Card Printing Reports</DropdownMenuItem>
                                    <DropdownMenuItem>Aadhaar Verification Stats</DropdownMenuItem>
                                    <DropdownMenuItem>Delivery Status Overview</DropdownMenuItem>
                                    <DropdownMenuItem>Sticker Distribution</DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>


                        <div className='w-full' >
                            <DropdownMenu >
                                <DropdownMenuTrigger asChild>
                                    <button className="w-full flex items-center justify-center gap-1 px-3 py-2 rounded-md dark:bg-gray-800 text-sm font-medium text-gray-800 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-600 transition">
                                        <FaImages className="w-4 h-4" />
                                        Stickers
                                        <FaChevronDown className="w-3 h-3" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent className="w-48">
                                    <DropdownMenuLabel>Sticker Management</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem>All Stickers</DropdownMenuItem>
                                    <DropdownMenuItem> Car Stickers</DropdownMenuItem>
                                    <DropdownMenuItem>Bike Stickers</DropdownMenuItem>
                                    <DropdownMenuItem>Printed Stickers</DropdownMenuItem>
                                    <DropdownMenuItem>Remaining Stickers</DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>


                                <div className="w-full">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <button className="w-full flex items-center justify-center gap-1 px-3 py-2 rounded-md dark:bg-gray-800 text-sm font-medium text-gray-800 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-600 transition">
                                                <MdContactSupport className="w-4 h-4" />
                                                Account
                                                <FaChevronDown className="w-3 h-3" />
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent className="w-48">
                                            <DropdownMenuLabel>Account & Settings</DropdownMenuLabel>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem>How to use the portal</DropdownMenuItem>
                                            <DropdownMenuItem>Contact Admin</DropdownMenuItem>
                                            <DropdownMenuItem>Report an issue</DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>


                                <div className="w-full">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <button className="flex w-full items-center justify-center  gap-1 px-3 py-2 rounded-md dark:bg-gray-800 text-sm font-medium text-gray-800 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-600 transition">
                                                <MdContactSupport className="w-4 h-4" />
                                                Support
                                                <FaChevronDown className="w-3 h-3" />
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent className="w-48">
                                            <DropdownMenuLabel>Help & Support</DropdownMenuLabel>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem>How to use the portal</DropdownMenuItem>
                                            <DropdownMenuItem>Contact Admin</DropdownMenuItem>
                                            <DropdownMenuItem>Report an issue</DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>


                            </div>
                        )}

                    </div> 
                    
                </div>


                <div
                    className={
                        (showingNavigationDropdown ? 'block' : 'hidden') +
                        ' sm:hidden'
                    }
                >
                    <div className="space-y-1 pb-3 pt-2">
                        {/* <ResponsiveNavLink
                            href={route('dashboard')}
                            active={route().current('dashboard')}
                        >
                            Dashboard
                        </ResponsiveNavLink>

                        <ResponsiveNavLink
                            href={route('dashboard')}
                            active={route().current('branch')}
                        >
                            Branches
                        </ResponsiveNavLink>

                        <ResponsiveNavLink
                            href={route('dashboard')}
                            active={route().current('designation')}
                        >
                            Designation
                        </ResponsiveNavLink>

                        <ResponsiveNavLink
                            href={route('dashboard')}
                            active={route().current('cards')}
                        >
                            Cards
                        </ResponsiveNavLink>

                        <ResponsiveNavLink
                            href={route('dashboard')}
                            active={route().current('adharverification')}
                        >
                            Adhar Verification
                        </ResponsiveNavLink>

                        <ResponsiveNavLink
                            href={route('dashboard')}
                            active={route().current('adharverification')}
                        >
                            Setting's
                        </ResponsiveNavLink> */}
                    </div>

                    <div className="border-t border-gray-200 pb-1 pt-4 dark:border-gray-600">
                        <div className="px-4">
                            <div className="text-base font-medium text-gray-800 dark:text-gray-200">
                                {user.name}
                            </div>
                            <div className="text-sm font-medium text-gray-500">
                                {user.email}
                            </div>
                        </div>

                        <div className="mt-3 space-y-1">
                            {/* <ResponsiveNavLink href={route('profile.edit')}>
                                Profile
                            </ResponsiveNavLink> */}

                        </div>
                    </div>
                </div>
            </nav>

            {header && (
                <header className="bg-white shadow dark:bg-gray-800">
                    <div className="mx-auto max-w-full px-4 py-6 sm:px-6 lg:px-8">
                        {header}
                    </div>
                </header>
            )}
<main>{children}</main>

<Toaster
    position="top-right"
    reverseOrder={false}
    gutter={8}
/>
</div> 
</div>

</>
    );
}
