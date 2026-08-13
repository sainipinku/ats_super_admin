import React from "react";
import { Link, usePage } from "@inertiajs/react";
import {
    FaBuilding,
    FaUsers,
    FaProjectDiagram,
    FaClipboardList,
    FaDraftingCompass,
    FaHardHat,
    FaBoxes,
    FaTruck,
    FaCogs,
    FaHandshake,
    FaTachometerAlt,
    FaCity,
    FaLayerGroup,
    FaRoute,
    FaTools,
    FaMoneyBillWave,
    FaKey,
    FaChevronRight,
} from "react-icons/fa";

const navigation = [
    {
        section: "Overview",
        items: [
            {
                label: "ERP Dashboard",
                href: route("super.dashboard"),
                active: ["super.dashboard", "super.construction.dashboard"],
                icon: FaTachometerAlt,
            },
        ],
    },
    {
        section: "Foundation",
        items: [
            {
                label: "Company Setup",
                href: route("super.construction.companies.index"),
                active: ["super.construction.companies.*"],
                icon: FaBuilding,
            },
            {
                label: "Employee Management",
                href: route("super.employees.list"),
                active: ["super.employees.*"],
                icon: FaUsers,
            },
            {
                label: "Client Registration",
                href: route("super.construction.clients.index"),
                active: ["super.construction.clients.*"],
                icon: FaCity,
            },
        ],
    },
    {
        section: "Project Lifecycle",
        items: [
            {
                label: "Projects & Budget",
                href: route("super.construction.projects.index"),
                active: ["super.construction.projects.*"],
                icon: FaProjectDiagram,
            },
            {
                label: "Survey Planning",
                href: route("super.construction.survey.index"),
                active: ["super.construction.survey.*"],
                icon: FaClipboardList,
            },
            {
                label: "Drawing Approval",
                href: route("super.construction.drafting.index"),
                active: ["super.construction.drafting.*"],
                icon: FaDraftingCompass,
            },
            // {
            //     label: "Construction Execution",
            //     href: route("super.construction.execution.index"),
            //     active: ["super.construction.execution.*"],
            //     icon: FaHardHat,
            // },
            // {
            //     label: "Material Management",
            //     href: route("super.construction.materials.index"),
            //     active: ["super.construction.materials.*"],
            //     icon: FaBoxes,
            // },
        ],
    },
    {
        section: "Fleet & Equipment",
        items: [
            {
                label: "Vehicle Management",
                href: route("super.vehicles.list"),
                active: ["super.vehicles.*"],
                icon: FaTruck,
            },
            {
                label: "Vehicle Tracking",
                href: route("super.construction.vehicles.index"),
                active: ["super.construction.vehicles.*"],
                icon: FaRoute,
            },
            // {
            //     label: "Equipment Allocation",
            //     href: route("super.construction.equipment.index"),
            //     active: ["super.construction.equipment.*"],
            //     icon: FaCogs,
            // },
            {
                label: "Equipment Categories",
                href: route("super.equipment.categories.list"),
                active: ["super.equipment.categories.*"],
                icon: FaLayerGroup,
            },
            {
                label: "Equipment Management",
                href: route("super.equipment.list"),
                active: ["super.equipment.*"],
                icon: FaTools,
            },
        ],
    },
    {
        section: "Finance & Closure",
        items: [
            {
                label: "Accounts & Billing",
                href: route("super.construction.billing.index"),
                active: ["super.construction.billing.*"],
                icon: FaMoneyBillWave,
            },
            {
                label: "Handover & Closure",
                href: route("super.construction.handover.index"),
                active: ["super.construction.handover.*"],
                icon: FaHandshake,
            },
        ],
    },
];

export default function Sidebar({ isOpen, onClose }) {
    const { props } = usePage();
    const user = props?.auth?.user;

    return (
        <>
            {/* Backdrop overlay - closes sidebar when clicking outside */}
            {isOpen && (
                <div
                    className="fixed inset-0 z-30 bg-black/40 backdrop-blur-sm xl:bg-transparent xl:backdrop-blur-none"
                    onClick={onClose}
                />
            )}

            <aside
                className={`fixed left-0 top-0 z-40 flex h-screen w-[288px] flex-col bg-white text-slate-900 shadow-2xl shadow-slate-900/10 transition-transform duration-300 ease-in-out dark:bg-[#03011C] dark:text-white dark:shadow-black/40 ${
                    isOpen ? "translate-x-0" : "-translate-x-full"
                }`}
            >
                {/* ── Brand Header ─────────────────────────────── */}
                <div className="relative flex shrink-0 items-center gap-3 border-b border-gray-200/80 px-5 py-4 dark:border-[#5146e64a]">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 shadow-lg shadow-indigo-500/30">
                        <FaKey className="text-white" size={18} />
                    </div>
                    <div className="min-w-0">
                        <p className="truncate text-[15px] font-bold tracking-tight text-slate-900 dark:text-white">
                            CadMax ERP
                        </p>
                        <p className="truncate text-[11px] font-medium uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-300">
                            Super Admin
                        </p>
                    </div>
                </div>

                {/* ── Scrollable Navigation ────────────────────── */}
                <nav className="flex-1 overflow-y-auto overflow-x-hidden px-3 py-4 sidebar-scroll">
                    {navigation.map((group) => (
                        <div key={group.section} className="mb-5 last:mb-0">
                            <p className="mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">
                                {group.section}
                            </p>
                            <div className="space-y-1">
                                {group.items.map((item) => {
                                    const isActive = item.active.some((pattern) =>
                                        route().current(pattern)
                                    );
                                    const Icon = item.icon;

                                    return (
                                        <Link
                                            key={item.label}
                                            href={item.href}
                                            className={`group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13.5px] font-medium transition-all duration-200 ${
                                                isActive
                                                    ? "bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-lg shadow-indigo-600/25"
                                                    : "text-slate-600 hover:bg-indigo-50 hover:text-indigo-700 dark:text-slate-300 dark:hover:bg-[#5146E61A] dark:hover:text-indigo-300"
                                            }`}
                                        >
                                            {/* Active indicator bar */}
                                            {isActive && (
                                                <span className="absolute left-0 top-1/2 h-5 w-[3px] -translate-y-1/2 rounded-r-full bg-white/90" />
                                            )}

                                            <span
                                                className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-lg transition-colors ${
                                                    isActive
                                                        ? "bg-white/15 text-white"
                                                        : "bg-slate-100 text-slate-500 group-hover:bg-indigo-100 group-hover:text-indigo-600 dark:bg-[#5146E61A] dark:text-slate-400 dark:group-hover:bg-[#5146E633] dark:group-hover:text-indigo-300"
                                                }`}
                                            >
                                                <Icon size={14} />
                                            </span>

                                            <span className="flex-1 truncate">
                                                {item.label}
                                            </span>

                                            <FaChevronRight
                                                size={10}
                                                className={`shrink-0 transition-all duration-200 ${
                                                    isActive
                                                        ? "translate-x-0 opacity-100"
                                                        : "-translate-x-1 opacity-0 group-hover:translate-x-0 group-hover:opacity-100"
                                                }`}
                                            />
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </nav>

                {/* ── Footer / User Card ───────────────────────── */}
                <div className="shrink-0 border-t border-gray-200/80 p-3 dark:border-[#5146e64a]">
                    <div className="flex items-center gap-3 rounded-xl bg-gradient-to-r from-indigo-50 to-violet-50 p-3 dark:from-[#5146E61A] dark:to-[#5146E60D]">
                        <div className="relative shrink-0">
                            <img
                                src={
                                    user?.profile_photo_url ||
                                    "/images/profileimg.png"
                                }
                                alt="User"
                                className="h-9 w-9 rounded-full border-2 border-white object-cover shadow-sm dark:border-[#5146E6]"
                                onError={(e) => {
                                    e.currentTarget.src = "/images/profileimg.png";
                                }}
                            />
                            <span className="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-white bg-emerald-500 dark:border-[#03011C]" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-[13px] font-semibold text-slate-900 dark:text-white">
                                {user?.name || "Super Admin"}
                            </p>
                            <p className="truncate text-[11px] text-slate-500 dark:text-slate-400">
                                {user?.email || "admin@cadmax.in"}
                            </p>
                        </div>
                    </div>
                </div>
            </aside>

            {/* Custom scrollbar styles */}
            <style>{`
                .sidebar-scroll::-webkit-scrollbar {
                    width: 4px;
                }
                .sidebar-scroll::-webkit-scrollbar-track {
                    background: transparent;
                }
                .sidebar-scroll::-webkit-scrollbar-thumb {
                    background: rgba(81, 70, 230, 0.3);
                    border-radius: 9999px;
                }
                .sidebar-scroll::-webkit-scrollbar-thumb:hover {
                    background: rgba(81, 70, 230, 0.5);
                }
                .sidebar-scroll {
                    scrollbar-width: thin;
                    scrollbar-color: rgba(81, 70, 230, 0.3) transparent;
                }
            `}</style>
        </>
    );
}