import React from "react";
import { Link, usePage } from "@inertiajs/react";
import {
    FaTachometerAlt,
    FaProjectDiagram,
    FaClipboardList,
    FaDraftingCompass,
    FaHardHat,
    FaBoxes,
    FaTruck,
    FaCogs,
    FaFileInvoiceDollar,
    FaHandshake,
    FaUserTie,
} from "react-icons/fa";

const adminFlowItems = [
    {
        label: "Project Dashboard",
        href: route("admin.dashboard"),
        active: ["admin.dashboard", "admin.construction.dashboard"],
        permissions: ["dashboard.view"],
        icon: FaTachometerAlt,
    },
    {
        label: "Assigned Projects",
        href: route("admin.construction.projects.index"),
        active: ["admin.construction.projects.*"],
        permissions: ["project.manage", "project.view"],
        icon: FaProjectDiagram,
    },
    {
        label: "Survey Workflow",
        href: route("admin.construction.survey.index"),
        active: ["admin.construction.survey.*"],
        permissions: ["survey_plan.manage", "survey_submission.review"],
        icon: FaClipboardList,
    },
    {
        label: "Drawing Approval",
        href: route("admin.construction.drafting.index"),
        active: ["admin.construction.drafting.*"],
        permissions: ["drafting.manage", "drawing_approval.manage"],
        icon: FaDraftingCompass,
    },
    {
        label: "Construction Execution",
        href: route("admin.construction.execution.index"),
        active: ["admin.construction.execution.*"],
        permissions: ["execution.manage", "execution_task.manage", "dpr.manage", "dpr.review", "attendance.manage", "attendance.review"],
        icon: FaHardHat,
    },
    {
        label: "Material Management",
        href: route("admin.construction.materials.index"),
        active: ["admin.construction.materials.*"],
        permissions: ["material.manage", "purchase_request.manage", "purchase_order.manage", "material_receipt.manage", "material_issue.manage", "material_stock.manage"],
        icon: FaBoxes,
    },
    {
        label: "Vehicle Tracking",
        href: route("admin.construction.vehicles.index"),
        active: ["admin.construction.vehicles.*"],
        permissions: ["vehicle.manage", "vehicle_assignment.manage", "vehicle_tracking.manage"],
        icon: FaTruck,
    },
    {
        label: "Equipment Allocation",
        href: route("admin.construction.equipment.index"),
        active: ["admin.construction.equipment.*"],
        permissions: ["equipment.manage", "equipment_allocation.manage", "equipment_usage.manage"],
        icon: FaCogs,
    },
    {
        label: "Accounts & Billing",
        href: route("admin.construction.billing.index"),
        active: ["admin.construction.billing.*"],
        permissions: ["billing_invoice.manage", "billing_payment.manage"],
        icon: FaFileInvoiceDollar,
    },
    {
        label: "Handover & Closure",
        href: route("admin.construction.handover.index"),
        active: ["admin.construction.handover.*"],
        permissions: ["handover.manage", "project_closure.manage"],
        icon: FaHandshake,
    },
    {
        label: "My Profile",
        href: route("admin.profile"),
        active: ["admin.profile"],
        permissions: [],
        icon: FaUserTie,
    },
];

export default function Sidebar({ isOpen, onClose }) {
    const user = usePage().props.auth?.user;
    const permissions = usePage().props.auth?.permissions ?? [];

    const items = adminFlowItems.filter((item) =>
        item.permissions.length === 0 || item.permissions.some((permission) => permissions.includes(permission))
    );

    return (
        <aside
            className={`fixed top-0 left-0 z-[99] h-full w-[288px] bg-white text-slate-900 shadow-md transition-transform duration-300 dark:bg-[#03011C] dark:text-white overflow-y-auto xl:translate-x-0 ${
                isOpen ? "translate-x-0" : "-translate-x-full"
            }`}
        >
            <div className="border-b border-gray-200 p-4 dark:border-b-[#5146e64a]">
                <div className="flex items-center justify-between">
                    <div>
                        <div className="block dark:hidden">
                            <img className="max-w-[90px] sm:max-w-[110px]" src="/images/logo.png" alt="Logo" />
                        </div>
                        <div className="hidden dark:block">
                            <img className="max-w-[90px] sm:max-w-[110px]" src="/images/logo-dark.png" alt="Logo" />
                        </div>
                    </div>
                    <button
                        onClick={onClose}
                        className="flex h-[38px] w-[48px] items-center justify-center rounded-[8px] border border-[#0000001A] bg-white text-[#000] transition focus:outline-none dark:border-[#61CC681A] dark:bg-[#61CC681A] dark:text-[#fff] md:flex xl:hidden"
                    >
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <div className="border-b border-gray-200 px-4 py-4 dark:border-b-[#5146e64a]">
                <p className="text-[10px] font-semibold uppercase tracking-[0.22em] text-violet-600 dark:text-violet-400">
                    Construction ERP · Admin
                </p>
                <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
                    Manage assigned projects, review workflows, approve drawings &amp; DPR.
                </p>
                {user?.name ? (
                    <div className="mt-3 flex items-center gap-2">
                        <span className="w-8 h-8 rounded-full bg-violet-100 text-violet-700 dark:bg-violet-900/60 dark:text-violet-300 flex items-center justify-center font-bold text-sm overflow-hidden border border-violet-200 dark:border-violet-800">
                            {user.profile_photo_url ? (
                                <img src={user.profile_photo_url} alt={user.name} className="w-full h-full object-cover" />
                            ) : (
                                (user.name || "A").charAt(0).toUpperCase()
                            )}
                        </span>
                        <div className="min-w-0">
                            <p className="text-sm font-semibold text-slate-900 dark:text-white truncate">{user.name}</p>
                            {user.email ? (
                                <p className="text-[11px] text-slate-500 dark:text-slate-400 truncate">{user.email}</p>
                            ) : null}
                        </div>
                    </div>
                ) : null}
            </div>

            <nav className="px-[10px] py-[12px] space-y-1">
                {items.map((item) => {
                    const isActive = item.active.some((pattern) => route().current(pattern));
                    const Icon = item.icon;
                    return (
                        <Link
                            key={item.label}
                            href={item.href}
                            className={`flex items-center gap-3 rounded-xl px-[12px] py-[11px] text-[14px] font-medium transition-all duration-200 ${
                                isActive
                                    ? "bg-violet-600 text-white shadow-md shadow-violet-500/20"
                                    : "text-[#52525B] hover:bg-violet-50 hover:text-violet-700 dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-violet-300"
                            }`}
                        >
                            <Icon className="w-[17px] h-[17px] shrink-0" />
                            <span className="flex-1 truncate">{item.label}</span>
                            {isActive && <span className="ml-auto w-1.5 h-6 rounded-full bg-white/80" />}
                        </Link>
                    );
                })}
            </nav>

            <div className="mt-4 px-4 pb-6">
                <div className="rounded-xl bg-gradient-to-br from-violet-500 to-purple-600 p-4 text-white shadow-md">
                    <p className="text-[10px] uppercase tracking-wider opacity-80">Admin Control</p>
                    <p className="mt-1 text-sm font-semibold">Survey → Execution → Billing</p>
                    <p className="mt-2 text-[11px] opacity-80 leading-relaxed">
                        Projects · Survey · Drawing · Execution · Materials · Billing · Handover
                    </p>
                </div>
            </div>
        </aside>
    );
}
