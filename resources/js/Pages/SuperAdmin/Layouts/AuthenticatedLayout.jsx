import { useAlerts } from "@/Components/Alerts";
import ApplicationLogo from "@/Components/ApplicationLogo";
import Dropdown from "@/Components/Dropdown";
import { useHelpers } from "@/Components/Helpers";
import NavLink from "@/Components/NavLink";
import ResponsiveNavLink from "@/Components/ResponsiveNavLink";
import { Link, usePage } from "@inertiajs/react";
import { useEffect, useRef, useState, useContext } from "react";
import { Toaster } from "react-hot-toast";
import { IoMoon } from "react-icons/io5";
import { FaChevronDown, FaSun } from "react-icons/fa6";
import { FaBell } from "react-icons/fa";
import { IoSettings } from "react-icons/io5";
import { GiHamburgerMenu } from "react-icons/gi";
import { SettingsProvider, useSettings } from "@/Components/SettingsProvider";
import UserDropdown from "./UserDropdown";
import Sidebar from "./Sidebar";
import { route } from "ziggy-js";
import { SlDocs } from "react-icons/sl";

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import NavDropdown from "@/Components/NavDropdown";

function isValidationError(error) {
    return typeof error == "object" && error !== null;
}

export default function AuthenticatedLayout({ header, children }) {
    const { hasPermissionLike, hasPermission, hasAnyPermission } = useHelpers();
    const user = usePage().props;
    const permissions = usePage().props.auth?.permissions ?? [];

    const { successAlert, errorAlert, warningAlert, infoAlert } = useAlerts();

    const { ziggy, flash, errors, messages } = usePage().props;
    const { url } = usePage();

    useEffect(() => {
        if (flash?.success) successAlert(flash.success);
        if (flash?.error && !isValidationError(flash.error))
            errorAlert(flash.error);
        if (flash?.warning) warningAlert(flash.warning);
        if (flash?.info) infoAlert(flash.info);
        if (messages?.envelopes?.length > 0) {
            messages.envelopes.forEach(({ type, message }) => {
                switch (type) {
                    case "success":
                        successAlert(message);
                        break;
                    case "error":
                        if (!isValidationError(message)) errorAlert(message);
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

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);
    const [darkMode, setDarkMode] = useState(
        () =>
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

    const [openMenu, setOpenMenu] = useState(null);

    const handleMouseEnter = (menu) => setOpenMenu(menu);
    const handleMouseLeave = () => setOpenMenu(null);

    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const handleToggle = () => {
        setIsMenuOpen((prev) => !prev);
    };

    // Dropdown
    const [dropdownOpen, setDropdownOpen] = useState(false);
    const dropdownRef = useRef(null);
    // Close dropdown on outside click
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

    // Sidebar
    const [sidebarOpen, setSidebarOpen] = useState(false);

    const toggleSidebar = () => setSidebarOpen((prev) => !prev);
    const closeSidebar = () => setSidebarOpen(false);
    const [settings, setSettings] = useState(null);
    const [loading, setLoading] = useState(true);

    const isInitialMount = useRef(true);

    // useEffect(() => {
    //     const fetchSettings = async () => {
    //         try {
    //             const response = await axios.get(route("super.settings.list"));
    //             setSettings(response.data.settings);
    //         } catch (error) {
    //             console.error("Error fetching settings:", error);
    //         } finally {
    //             setLoading(false);
    //         }
    //     };

    //     if (isInitialMount.current || settings === null) {
    //         fetchSettings();
    //         isInitialMount.current = false;
    //     }
    // }, [settings]);
    return (
        <>
                <Sidebar isOpen={sidebarOpen} onClose={closeSidebar} />
                <SettingsProvider>
                    <div className="min-h-screen mainbg">
                        <nav className="headerbg fixed top-0 left-0 right-0 z-50 py-[15px] px-[15px] rounded-[10px] print:hidden">
                            <div className="navbg rounded-[10px] border border-[1px]  borderbx ">
                                <div className="mx-auto max-w-full px-[8px] py-[0]  ">
                                    <div className="flex h-16 justify-between border-gray-200 dark:border-gray-700 ">
                                        <div className="flex gap-[45px] items-center">
                                            {/* Logo */}
                                            <button
                                                onClick={toggleSidebar}
                                                className="flex xl:hidden items-center justify-center bg-white dark:bg-[#61CC681A] w-[40px] h-[40px] border-[1px] border-[#0000001A] dark:border-[#61CC681A] rounded-[8px] transition text-[currentColor] dark:text-[currentColor] focus:outline-none hover:bg-gray-100 dark:hover:bg-[#61CC6820]"
                                                aria-label="Toggle sidebar"
                                            >
                                                <svg
                                                    className="w-6 h-6"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                >
                                                    <path
                                                        strokeLinecap="round"
                                                        strokeLinejoin="round"
                                                        strokeWidth={2}
                                                        d="M4 6h16M4 12h16M4 18h16"
                                                    />
                                                </svg>
                                            </button>

                                            {/* Logo */}
                                            <div className="flex shrink-0 items-center">
                                                <Link
                                                    href={route(
                                                        "super.dashboard"
                                                    )}
                                                >
                                                    <ApplicationLogo
                                                        props={settings}
                                                        className="block w-auto fill-current"
                                                    />
                                                </Link>
                                            </div>

                                            {/* Navigation Links */}
                                            <div className="hidden xl:flex items-center gap-[25px] xl:gap-[40px]">
                                                <NavLink
                                                    className=""
                                                    href={route(
                                                        "super.dashboard"
                                                    )}
                                                    active={route().current(
                                                        "super.dashboard"
                                                    )}
                                                >
                                                    <svg
                                                        width="24"
                                                        height="24"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        xmlns="http://www.w3.org/2000/svg"
                                                    >
                                                        <path
                                                            d="M8.807 2.75H4.932C4.67821 2.74974 4.42687 2.79953 4.19235 2.89653C3.95783 2.99353 3.74474 3.13583 3.56528 3.31528C3.38583 3.49474 3.24353 3.70783 3.14653 3.94235C3.04953 4.17687 2.99974 4.42821 3 4.682V8.557C2.99947 9.07048 3.20253 9.56324 3.56468 9.92726C3.92683 10.2913 4.41852 10.4969 4.932 10.499H8.807C9.0621 10.4993 9.31475 10.4492 9.55048 10.3517C9.78622 10.2542 10.0004 10.1112 10.1808 9.93079C10.3612 9.75041 10.5042 9.53622 10.6017 9.30048C10.6992 9.06475 10.7493 8.8121 10.749 8.557V4.682C10.7469 4.16852 10.5413 3.67683 10.1773 3.31468C9.81324 2.95253 9.32048 2.74947 8.807 2.75ZM19.568 2.75H15.693C15.1795 2.74947 14.6868 2.95253 14.3227 3.31468C13.9587 3.67683 13.7531 4.16852 13.751 4.682V8.557C13.7513 9.07197 13.956 9.56577 14.3201 9.92991C14.6842 10.294 15.178 10.4987 15.693 10.499H19.568C20.0815 10.4969 20.5732 10.2913 20.9353 9.92726C21.2975 9.56324 21.5005 9.07048 21.5 8.557V4.682C21.5003 4.42821 21.4505 4.17687 21.3535 3.94235C21.2565 3.70783 21.1142 3.49474 20.9347 3.31528C20.7553 3.13583 20.5422 2.99353 20.3077 2.89653C20.0731 2.79953 19.8218 2.74974 19.568 2.75ZM19.568 13.5H15.693C15.1793 13.4995 14.6864 13.7027 14.3224 14.065C13.9583 14.4274 13.7529 14.9193 13.751 15.433V19.308C13.7507 19.5631 13.8008 19.8158 13.8983 20.0515C13.9958 20.2872 14.1388 20.5014 14.3192 20.6818C14.4996 20.8622 14.7138 21.0052 14.9495 21.1027C15.1853 21.2002 15.4379 21.2503 15.693 21.25H19.568C20.0815 21.2479 20.5732 21.0423 20.9353 20.6783C21.2975 20.3142 21.5005 19.8215 21.5 19.308V15.433C21.5003 15.1792 21.4505 14.9279 21.3535 14.6933C21.2565 14.4588 21.1142 14.2457 20.9347 14.0663C20.7553 13.8868 20.5422 13.7445 20.3077 13.6475C20.0731 13.5505 19.8218 13.5007 19.568 13.501M8.807 13.5H4.932C4.41859 13.5029 3.92721 13.7089 3.5652 14.073C3.20319 14.437 2.99999 14.9296 3 15.443V19.318C2.99974 19.5718 3.04953 19.8231 3.14653 20.0577C3.24353 20.2922 3.38583 20.5053 3.56528 20.6847C3.74474 20.8642 3.95783 21.0065 4.19235 21.1035C4.42687 21.2005 4.67821 21.2503 4.932 21.25H8.807C9.32048 21.2505 9.81324 21.0475 10.1773 20.6853C10.5413 20.3232 10.7469 19.8315 10.749 19.318V15.443C10.7493 15.1879 10.6992 14.9353 10.6017 14.6995C10.5042 14.4638 10.3612 14.2496 10.1808 14.0692C10.0004 13.8888 9.78622 13.7458 9.55048 13.6483C9.31475 13.5508 9.0621 13.5007 8.807 13.501"
                                                            fill="currentColor"
                                                        />
                                                    </svg>
                                                    Dashboard
                                                </NavLink>
                                                <NavLink
                                                    className=""
                                                    href={route(
                                                        "super.role.list"
                                                    )}
                                                    active={route().current(
                                                        "super.role.list"
                                                    )}
                                                >
                                                    <svg
                                                        width="24"
                                                        height="24"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        xmlns="http://www.w3.org/2000/svg"
                                                    >
                                                        <g clip-path="url(#clip0_38_3190)">
                                                            <path
                                                                d="M14.4803 2.40744C14.3642 1.90631 14.0748 1.46231 13.6632 1.15379C13.2516 0.845258 12.7442 0.692034 12.2306 0.721153C11.717 0.750272 11.2302 0.959861 10.8561 1.31294C10.482 1.66601 10.2447 2.13988 10.1859 2.65092C10.1272 3.16196 10.2508 3.67733 10.5351 4.10608C10.8193 4.53482 11.2458 4.8494 11.7394 4.99429C12.233 5.13918 12.7619 5.10507 13.2328 4.89799C13.7037 4.6909 14.0863 4.32414 14.3131 3.86244C15.6938 4.17685 16.9773 4.82241 18.0529 5.74344C19.1285 6.66447 19.9639 7.83332 20.4871 9.14919L21.8183 8.62569C21.2021 7.06494 20.2098 5.68062 18.9296 4.59579C17.6494 3.51096 16.121 2.75924 14.4803 2.40744ZM12.3638 3.63594C12.2664 3.63907 12.1693 3.62258 12.0783 3.58745C11.9874 3.55231 11.9044 3.49926 11.8344 3.43143C11.7643 3.3636 11.7086 3.28238 11.6706 3.1926C11.6326 3.10282 11.613 3.00632 11.613 2.90881C11.613 2.81131 11.6326 2.7148 11.6706 2.62503C11.7086 2.53525 11.7643 2.45403 11.8344 2.3862C11.9044 2.31837 11.9874 2.26532 12.0783 2.23018C12.1693 2.19505 12.2664 2.17856 12.3638 2.18169C12.5568 2.18169 12.7418 2.25834 12.8782 2.39477C13.0147 2.5312 13.0913 2.71624 13.0913 2.90919C13.0913 3.10213 13.0147 3.28718 12.8782 3.42361C12.7418 3.56004 12.5568 3.63594 12.3638 3.63594ZM9.16356 4.24044L8.62581 2.90994C7.06506 3.52618 5.68074 4.51849 4.59591 5.79867C3.51108 7.07885 2.75936 8.60722 2.40756 10.2479C1.90644 10.3641 1.46243 10.6535 1.15391 11.0651C0.84538 11.4767 0.692156 11.9841 0.721275 12.4976C0.750394 13.0112 0.959983 13.498 1.31306 13.8721C1.66613 14.2462 2.14 14.4836 2.65104 14.5423C3.16208 14.6011 3.67745 14.4774 4.1062 14.1932C4.53495 13.9089 4.84952 13.4824 4.99441 12.9888C5.1393 12.4953 5.10519 11.9664 4.89811 11.4955C4.69102 11.0246 4.32427 10.642 3.86256 10.4152C4.17826 9.03284 4.82593 7.7482 5.74955 6.67234C6.67317 5.59648 7.84495 4.76179 9.16356 4.24044ZM2.90856 13.0919C2.81302 13.0919 2.71842 13.0731 2.63016 13.0366C2.54189 13 2.4617 12.9464 2.39414 12.8789C2.32659 12.8113 2.273 12.7311 2.23644 12.6428C2.19988 12.5546 2.18106 12.46 2.18106 12.3644C2.18106 12.2689 2.19988 12.1743 2.23644 12.086C2.273 11.9978 2.32659 11.9176 2.39414 11.85C2.4617 11.7825 2.54189 11.7289 2.63016 11.6923C2.71842 11.6558 2.81302 11.6369 2.90856 11.6369C3.10151 11.6369 3.28655 11.7136 3.42298 11.85C3.55941 11.9865 3.63606 12.1715 3.63606 12.3644C3.63606 12.5574 3.55941 12.7424 3.42298 12.8789C3.28655 13.0153 3.10151 13.0919 2.90856 13.0919ZM12.3638 19.6372C11.9581 19.6385 11.5607 19.7529 11.2164 19.9675C10.8721 20.1821 10.5944 20.4885 10.4146 20.8522C9.03363 20.5378 7.74993 19.8922 6.67418 18.971C5.59843 18.0498 4.76295 16.8808 4.23981 15.5647L2.90931 16.1024C3.52756 17.6658 4.5232 19.0519 5.80739 20.1369C7.09158 21.2219 8.62439 21.9721 10.2691 22.3207C10.3635 22.7211 10.5693 23.0866 10.8625 23.3752C11.1557 23.6637 11.5246 23.8635 11.9264 23.9515C12.3283 24.0395 12.7469 24.0121 13.1339 23.8725C13.5208 23.7329 13.8605 23.4867 14.1136 23.1624C14.3668 22.8382 14.5231 22.4489 14.5646 22.0396C14.6061 21.6303 14.5311 21.2176 14.3482 20.8491C14.1652 20.4806 13.8819 20.1713 13.5308 19.9569C13.1797 19.7425 12.7752 19.6316 12.3638 19.6372ZM12.3638 22.5464C12.1709 22.5464 11.9858 22.4698 11.8494 22.3334C11.713 22.1969 11.6363 22.0119 11.6363 21.8189C11.6363 21.626 11.713 21.441 11.8494 21.3045C11.9858 21.1681 12.1709 21.0914 12.3638 21.0914C12.5568 21.0914 12.7418 21.1681 12.8782 21.3045C13.0147 21.441 13.0913 21.626 13.0913 21.8189C13.0913 22.0119 13.0147 22.1969 12.8782 22.3334C12.7418 22.4698 12.5568 22.5464 12.3638 22.5464ZM24.0001 12.3637C24.0014 11.9743 23.8985 11.5916 23.7021 11.2554C23.5057 10.9191 23.2229 10.6416 22.883 10.4515C22.5431 10.2614 22.1586 10.1657 21.7693 10.1743C21.38 10.1829 21 10.2955 20.6689 10.5005C20.3378 10.7054 20.0676 10.9952 19.8862 11.3398C19.7048 11.6844 19.619 12.0713 19.6376 12.4602C19.6561 12.8492 19.7784 13.2261 19.9917 13.5519C20.2051 13.8777 20.5017 14.1404 20.8508 14.3129C20.5363 15.6937 19.8907 16.9773 18.9695 18.0529C18.0483 19.1285 16.8793 19.9639 15.5633 20.4869L16.1018 21.8182C17.6652 21.1999 19.0512 20.2043 20.1362 18.9201C21.2213 17.6359 21.9715 16.1031 22.3201 14.4584C22.7935 14.3465 23.216 14.0795 23.5203 13.7C23.8247 13.3205 23.9936 12.8501 24.0001 12.3637ZM21.8176 13.0912C21.6246 13.0912 21.4396 13.0145 21.3031 12.8781C21.1667 12.7417 21.0901 12.5566 21.0901 12.3637C21.0901 12.1707 21.1667 11.9857 21.3031 11.8493C21.4396 11.7128 21.6246 11.6362 21.8176 11.6362C22.0105 11.6362 22.1955 11.7128 22.332 11.8493C22.4684 11.9857 22.5451 12.1707 22.5451 12.3637C22.5451 12.5566 22.4684 12.7417 22.332 12.8781C22.1955 13.0145 22.0105 13.0912 21.8176 13.0912Z"
                                                                fill="currentColor"
                                                            />
                                                            <path
                                                                d="M14.3921 12.2625C14.8059 11.8584 15.0898 11.3402 15.2077 10.774C15.3256 10.2078 15.2721 9.61932 15.0539 9.08369C14.8358 8.54805 14.463 8.08959 13.9831 7.76685C13.5031 7.44411 12.9379 7.27173 12.3596 7.27173C11.7812 7.27173 11.216 7.44411 10.7361 7.76685C10.2562 8.08959 9.88332 8.54805 9.66518 9.08369C9.44705 9.61932 9.3935 10.2078 9.5114 10.774C9.62929 11.3402 9.91327 11.8584 10.3271 12.2625C9.83463 12.5953 9.43126 13.0438 9.15225 13.5686C8.87323 14.0934 8.72708 14.6786 8.72656 15.273V16.7272H15.9993V15.2722C15.9981 14.6773 15.8509 14.0917 15.5706 13.5668C15.2904 13.0419 14.8857 12.5946 14.3921 12.2625ZM10.9083 10.1812C10.9083 9.99016 10.9459 9.80096 11.0191 9.62443C11.0922 9.4479 11.1994 9.28751 11.3345 9.1524C11.4696 9.01729 11.63 8.91011 11.8065 8.83699C11.983 8.76387 12.1722 8.72624 12.3633 8.72624C12.5544 8.72624 12.7436 8.76387 12.9201 8.83699C13.0966 8.91011 13.257 9.01729 13.3922 9.1524C13.5273 9.28751 13.6344 9.4479 13.7076 9.62443C13.7807 9.80096 13.8183 9.99016 13.8183 10.1812C13.8183 10.5671 13.665 10.9372 13.3922 11.2101C13.1193 11.4829 12.7492 11.6362 12.3633 11.6362C11.9774 11.6362 11.6073 11.4829 11.3345 11.2101C11.0616 10.9372 10.9083 10.5671 10.9083 10.1812ZM10.1808 15.2722C10.1754 14.9823 10.2279 14.6941 10.3352 14.4247C10.4424 14.1552 10.6023 13.9099 10.8054 13.7029C11.0086 13.4959 11.251 13.3315 11.5184 13.2193C11.7858 13.1071 12.0729 13.0493 12.3629 13.0493C12.653 13.0493 12.9401 13.1071 13.2075 13.2193C13.4749 13.3315 13.7173 13.4959 13.9204 13.7029C14.1236 13.9099 14.2835 14.1552 14.3907 14.4247C14.498 14.6941 14.5504 14.9823 14.5451 15.2722H10.1808Z"
                                                                fill="currentColor"
                                                            />
                                                        </g>
                                                        <defs>
                                                            <clipPath id="clip0_38_3190">
                                                                <rect
                                                                    width="24"
                                                                    height="24"
                                                                    fill="white"
                                                                />
                                                            </clipPath>
                                                        </defs>
                                                    </svg>
                                                    Roles
                                                </NavLink>
                                                <NavLink
                                                    className=""
                                                    href={route(
                                                        "super.departments"
                                                    )}
                                                    active={route().current(
                                                        "super.departments"
                                                    )}
                                                >
                                                    <svg
                                                        width="24"
                                                        height="24"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        xmlns="http://www.w3.org/2000/svg"
                                                    >
                                                        <path
                                                            fill-rule="evenodd"
                                                            clip-rule="evenodd"
                                                            d="M15 6.00023C15.0004 6.62088 14.8082 7.22636 14.45 7.73321C14.0918 8.24007 13.5852 8.62335 13 8.83023V11.0002H16C16.7957 11.0002 17.5587 11.3163 18.1214 11.8789C18.684 12.4415 19 13.2046 19 14.0002V15.1702C19.6675 15.4061 20.2301 15.8704 20.5884 16.481C20.9467 17.0916 21.0775 17.8093 20.9578 18.507C20.8382 19.2048 20.4757 19.8378 19.9344 20.2941C19.3931 20.7504 18.708 21.0007 18 21.0007C17.2921 21.0007 16.6069 20.7504 16.0657 20.2941C15.5244 19.8378 15.1619 19.2048 15.0422 18.507C14.9225 17.8093 15.0534 17.0916 15.4117 16.481C15.7699 15.8704 16.3325 15.4061 17 15.1702V14.0002C17 13.735 16.8947 13.4807 16.7071 13.2931C16.5196 13.1056 16.2652 13.0002 16 13.0002H8.00003C7.73481 13.0002 7.48046 13.1056 7.29292 13.2931C7.10539 13.4807 7.00003 13.735 7.00003 14.0002V15.1702C7.66752 15.4061 8.23012 15.8704 8.58839 16.481C8.94666 17.0916 9.07752 17.8093 8.95784 18.507C8.83817 19.2048 8.47566 19.8378 7.9344 20.2941C7.39314 20.7504 6.70798 21.0007 6.00003 21.0007C5.29208 21.0007 4.60692 20.7504 4.06566 20.2941C3.5244 19.8378 3.16189 19.2048 3.04222 18.507C2.92254 17.8093 3.0534 17.0916 3.41167 16.481C3.76994 15.8704 4.33254 15.4061 5.00003 15.1702V14.0002C5.00003 13.2046 5.3161 12.4415 5.87871 11.8789C6.44132 11.3163 7.20438 11.0002 8.00003 11.0002H11V8.83023C10.4812 8.64707 10.0227 8.32478 9.67464 7.8986C9.3266 7.47242 9.10243 6.95876 9.02664 6.41377C8.95084 5.86879 9.02634 5.31345 9.24488 4.80848C9.46342 4.3035 9.81658 3.86833 10.2658 3.55054C10.7149 3.23274 11.2428 3.04456 11.7918 3.00656C12.3407 2.96855 12.8895 3.0822 13.3782 3.33506C13.8669 3.58793 14.2766 3.97028 14.5627 4.44032C14.8487 4.91036 15 5.45 15 6.00023ZM12 5.00023C11.7348 5.00023 11.4805 5.10559 11.2929 5.29313C11.1054 5.48066 11 5.73502 11 6.00023C11 6.26545 11.1054 6.5198 11.2929 6.70734C11.4805 6.89487 11.7348 7.00023 12 7.00023C12.2652 7.00023 12.5196 6.89487 12.7071 6.70734C12.8947 6.5198 13 6.26545 13 6.00023C13 5.73502 12.8947 5.48066 12.7071 5.29313C12.5196 5.10559 12.2652 5.00023 12 5.00023ZM6.00003 17.0002C5.73481 17.0002 5.48046 17.1056 5.29292 17.2931C5.10539 17.4807 5.00003 17.735 5.00003 18.0002C5.00003 18.2654 5.10539 18.5198 5.29292 18.7073C5.48046 18.8949 5.73481 19.0002 6.00003 19.0002C6.26525 19.0002 6.5196 18.8949 6.70714 18.7073C6.89467 18.5198 7.00003 18.2654 7.00003 18.0002C7.00003 17.735 6.89467 17.4807 6.70714 17.2931C6.5196 17.1056 6.26525 17.0002 6.00003 17.0002ZM18 17.0002C17.7348 17.0002 17.4805 17.1056 17.2929 17.2931C17.1054 17.4807 17 17.735 17 18.0002C17 18.2654 17.1054 18.5198 17.2929 18.7073C17.4805 18.8949 17.7348 19.0002 18 19.0002C18.2652 19.0002 18.5196 18.8949 18.7071 18.7073C18.8947 18.5198 19 18.2654 19 18.0002C19 17.735 18.8947 17.4807 18.7071 17.2931C18.5196 17.1056 18.2652 17.0002 18 17.0002Z"
                                                            fill="currentColor"
                                                        />
                                                    </svg>
                                                    Departments
                                                </NavLink>
                                                <NavLink
                                                    className=""
                                                    href={route(
                                                        "super.designation.list"
                                                    )}
                                                    active={route().current(
                                                        "super.designation.list"
                                                    )}
                                                >
                                                    {" "}
                                                    <svg
                                                        xmlns="http://www.w3.org/2000/svg"
                                                        className="w-[23px]"
                                                        viewBox="0 0 24 24"
                                                        fill="currentColor"
                                                    >
                                                        <path d="M7 5V2C7 1.44772 7.44772 1 8 1H16C16.5523 1 17 1.44772 17 2V5H21C21.5523 5 22 5.44772 22 6V20C22 20.5523 21.5523 21 21 21H3C2.44772 21 2 20.5523 2 20V6C2 5.44772 2.44772 5 3 5H7ZM4 16V19H20V16H4ZM4 14H20V7H4V14ZM9 3V5H15V3H9ZM11 11H13V13H11V11Z"></path>
                                                    </svg>
                                                    Designations
                                                </NavLink>
                                                <NavLink
                                                    className=""
                                                    // href={route(
                                                    //     "super.members.list"
                                                    // )}
                                                    // active={route().current(
                                                    //     "super.members.list"
                                                    // )}
                                                >
                                                    <svg
                                                        width="24"
                                                        height="24"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        xmlns="http://www.w3.org/2000/svg"
                                                    >
                                                        <path
                                                            d="M5.5 7C5.5 6.60603 5.5776 6.21593 5.72836 5.85195C5.87913 5.48797 6.1001 5.15726 6.37868 4.87868C6.65726 4.6001 6.98797 4.37913 7.35195 4.22836C7.71593 4.0776 8.10603 4 8.5 4C8.89397 4 9.28407 4.0776 9.64805 4.22836C10.012 4.37913 10.3427 4.6001 10.6213 4.87868C10.8999 5.15726 11.1209 5.48797 11.2716 5.85195C11.4224 6.21593 11.5 6.60603 11.5 7C11.5 7.79565 11.1839 8.55871 10.6213 9.12132C10.0587 9.68393 9.29565 10 8.5 10C7.70435 10 6.94129 9.68393 6.37868 9.12132C5.81607 8.55871 5.5 7.79565 5.5 7ZM8.5 2C7.17392 2 5.90215 2.52678 4.96447 3.46447C4.02678 4.40215 3.5 5.67392 3.5 7C3.5 8.32608 4.02678 9.59785 4.96447 10.5355C5.90215 11.4732 7.17392 12 8.5 12C9.82608 12 11.0979 11.4732 12.0355 10.5355C12.9732 9.59785 13.5 8.32608 13.5 7C13.5 5.67392 12.9732 4.40215 12.0355 3.46447C11.0979 2.52678 9.82608 2 8.5 2ZM15.5 2H14.5V4H15.5C15.894 4 16.2841 4.0776 16.6481 4.22836C17.012 4.37913 17.3427 4.6001 17.6213 4.87868C17.8999 5.15726 18.1209 5.48797 18.2716 5.85195C18.4224 6.21593 18.5 6.60603 18.5 7C18.5 7.39397 18.4224 7.78407 18.2716 8.14805C18.1209 8.51203 17.8999 8.84274 17.6213 9.12132C17.3427 9.3999 17.012 9.62087 16.6481 9.77164C16.2841 9.9224 15.894 10 15.5 10H14.5V12H15.5C16.8261 12 18.0979 11.4732 19.0355 10.5355C19.9732 9.59785 20.5 8.32608 20.5 7C20.5 5.67392 19.9732 4.40215 19.0355 3.46447C18.0979 2.52678 16.8261 2 15.5 2ZM0 19C0 17.6739 0.526784 16.4021 1.46447 15.4645C2.40215 14.5268 3.67392 14 5 14H12C13.3261 14 14.5979 14.5268 15.5355 15.4645C16.4732 16.4021 17 17.6739 17 19V21H15V19C15 18.2044 14.6839 17.4413 14.1213 16.8787C13.5587 16.3161 12.7956 16 12 16H5C4.20435 16 3.44129 16.3161 2.87868 16.8787C2.31607 17.4413 2 18.2044 2 19V21H0V19ZM24 19C24 18.3434 23.8707 17.6932 23.6194 17.0866C23.3681 16.48 22.9998 15.9288 22.5355 15.4645C22.0712 15.0002 21.52 14.6319 20.9134 14.3806C20.3068 14.1293 19.6566 14 19 14H18V16H19C19.7956 16 20.5587 16.3161 21.1213 16.8787C21.6839 17.4413 22 18.2044 22 19V21H24V19Z"
                                                            fill="currentColor"
                                                        />
                                                    </svg>{" "}
                                                    Members
                                                </NavLink>


                                            </div>
                                        </div>

                                        <div className="flex items-center gap-2 relative">
                                            <button
                                                onClick={() =>
                                                    setDarkMode(!darkMode)
                                                }
                                                className="flex items-center justify-center bg-white dark:bg-[#61CC681A] w-[48px] h-[38px] border-[1px] border-[#0000001A] dark:border-[#61CC681A] rounded-[8px] transition md:flex text-[currentColor] dark:text-[currentColor]"
                                            >
                                                {darkMode ? (
                                                    <FaSun
                                                        size={18}
                                                        className="text-gray-200"
                                                    />
                                                ) : (
                                                    <svg
                                                        width="24"
                                                        height="24"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        xmlns="http://www.w3.org/2000/svg"
                                                    >
                                                        <path
                                                            d="M12.0015 2L14.6365 4.635H19.365V9.363L22 11.998L19.365 14.637V19.365H14.637L12.002 22L9.363 19.365H4.635V14.637L2 11.9985L4.635 9.3635V4.635H9.363L12.0015 2Z"
                                                            stroke="currentColor"
                                                            strokeWidth="2"
                                                            strokeMiterlimit="10"
                                                            strokeLinecap="round"
                                                            strokeLinejoin="round"
                                                        />
                                                        <path
                                                            d="M13.5 8.5C13.5 12.5 11 13 8.5 13C8.5 15 11.75 17 14.5 15C17.25 13 15.5 8.5 13.5 8.5Z"
                                                            stroke="currentColor"
                                                            strokeWidth="2"
                                                            strokeMiterlimit="10"
                                                            strokeLinecap="round"
                                                            strokeLinejoin="round"
                                                        />
                                                    </svg>
                                                )}
                                            </button>

                                            {/* Settings button */}
                                            <div className="relative">
                                                <a

                                                >
                                                    <button
                                                        className="w-[30px] h-[30px] rounded-full p-0 mt-[5px] focus:outline-none overflow-hidden
                bg-transparent text-gray-800 dark:text-gray-100 hover:bg-gray-200 dark:hover:bg-gray-700"
                                                    >
                                                        <svg
                                                            xmlns="http://www.w3.org/2000/svg"
                                                            viewBox="0 0 24 24"
                                                            width="24"
                                                            height="24"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            strokeWidth="1.5"
                                                        >
                                                            <path d="M15.5 12C15.5 13.933 13.933 15.5 12 15.5C10.067 15.5 8.5 13.933 8.5 12C8.5 10.067 10.067 8.5 12 8.5C13.933 8.5 15.5 10.067 15.5 12Z" />
                                                            <path
                                                                d="M21.011 14.0965C21.5329 13.9558 21.7939 13.8854 21.8969 13.7508C22 13.6163 22 13.3998 22 12.9669V11.0332C22 10.6003 22 10.3838 21.8969 10.2493C21.7938 10.1147 21.5329 10.0443 21.011 9.90358C19.0606 9.37759 17.8399 7.33851 18.3433 5.40087C18.4817 4.86799 18.5509 4.60156 18.4848 4.44529C18.4187 4.28902 18.2291 4.18134 17.8497 3.96596L16.125 2.98673C15.7528 2.77539 15.5667 2.66972 15.3997 2.69222C15.2326 2.71472 15.0442 2.90273 14.6672 3.27873C13.208 4.73448 10.7936 4.73442 9.33434 3.27864C8.95743 2.90263 8.76898 2.71463 8.60193 2.69212C8.43489 2.66962 8.24877 2.77529 7.87653 2.98663L6.15184 3.96587C5.77253 4.18123 5.58287 4.28891 5.51678 4.44515C5.45068 4.6014 5.51987 4.86787 5.65825 5.4008C6.16137 7.3385 4.93972 9.37763 2.98902 9.9036C2.46712 10.0443 2.20617 10.1147 2.10308 10.2492C2 10.3838 2 10.6003 2 11.0332V12.9669C2 13.3998 2 13.6163 2.10308 13.7508C2.20615 13.8854 2.46711 13.9558 2.98902 14.0965C4.9394 14.6225 6.16008 16.6616 5.65672 18.5992C5.51829 19.1321 5.44907 19.3985 5.51516 19.5548C5.58126 19.7111 5.77092 19.8188 6.15025 20.0341L7.87495 21.0134C8.24721 21.2247 8.43334 21.3304 8.6004 21.3079C8.76746 21.2854 8.95588 21.0973 9.33271 20.7213C10.7927 19.2644 13.2088 19.2643 14.6689 20.7212C15.0457 21.0973 15.2341 21.2853 15.4012 21.3078C15.5682 21.3303 15.7544 21.2246 16.1266 21.0133L17.8513 20.034C18.2307 19.8187 18.4204 19.711 18.4864 19.5547C18.5525 19.3984 18.4833 19.132 18.3448 18.5991C17.8412 16.6616 19.0609 14.6226 21.011 14.0965Z"
                                                                strokeLinecap="round"
                                                            />
                                                        </svg>
                                                    </button>
                                                </a>
                                            </div>

                                            {/* User dropdown */}
                                            <div
                                                className="relative"
                                                ref={dropdownRef}
                                            >
                                                <button
                                                    onClick={(e) => {
                                                        e.stopPropagation(); // Prevent event bubbling
                                                        setDropdownOpen(
                                                            !dropdownOpen
                                                        );
                                                    }}
                                                    className="w-[30px] h-[30px] rounded-full bg-none p-0 mt-[5px] focus:outline-none overflow-hidden"
                                                >
                                                    <img
                                                        src={
                                                            user?.auth?.user
                                                                ?.profile_photo_url ||
                                                            user?.auth
                                                                ?.profile_photo_url
                                                        }
                                                        alt="User"
                                                        className="w-full h-full object-cover rounded-full"
                                                    />
                                                </button>

                                                {/* Dropdown */}
                                                {dropdownOpen && (
                                                    <div
                                                        className="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded shadow-lg z-50"
                                                        onClick={(e) =>
                                                            e.stopPropagation()
                                                        } // Prevent clicks inside dropdown from closing it
                                                    >
                                                        <ul className="py-1">
                                                            <li className="border-b border-gray-200 dark:border-gray-700">
                                                                <NavLink
                                                                    href={route(
                                                                        "super.profile"
                                                                    )}
                                                                    method="get"
                                                                    as="button"
                                                                    className="block w-full text-left px-4 py-2 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                                                                >
                                                                    Profile
                                                                </NavLink>
                                                            </li>
                                                            <li>
                                                                <NavLink
                                                                    href={route(
                                                                        "super.logout"
                                                                    )}
                                                                    method="post"
                                                                    as="button"
                                                                    className="block w-full text-left px-4 py-2 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                                                                >
                                                                    Logout
                                                                </NavLink>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    className={
                                        (showingNavigationDropdown
                                            ? "block"
                                            : "hidden") + " sm:hidden"
                                    }
                                >
                                    <div className="space-y-1 pb-3 pt-2">
                                        <ResponsiveNavLink
                                            href={route("super.dashboard")}
                                            active={route().current(
                                                "super.dashboard"
                                            )}
                                        >
                                            Dashboard
                                        </ResponsiveNavLink>
                                    </div>

                                    <div className="border-t border-gray-200 pb-1 pt-4 dark:border-gray-600">
                                        <div className="px-4">
                                            <div className="text-base font-medium text-gray-800 dark:text-gray-200">
                                                {user?.name}
                                            </div>
                                            <div className="text-sm font-medium text-gray-500">
                                                {user?.email}
                                            </div>
                                        </div>

                                        <div className="mt-3 space-y-1"></div>
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
                </SettingsProvider>
        </>
    );
}
