import React from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import { FaLock, FaArrowLeft } from "react-icons/fa";

export default function PermissionDenied({
    required_permission,
    permission_name,
    module_name,
}) {
    const { auth } = usePage().props;

    const getDashboardUrl = () => {
        try {
            const guard = auth?.guard;
            if (guard === "superadmin") return route("super.dashboard");
            if (guard === "admin") return route("admin.dashboard");
            if (guard === "member") return route("member.dashboard");
            return "/";
        } catch (err) {
            return "/";
        }
    };

    return (
        <div className="min-h-screen bg-slate-900 text-white flex items-center justify-center p-4">
            <Head title="Access Denied - Permission Required" />

            <div className="max-w-md w-full bg-slate-800/90 border border-slate-700/80 rounded-2xl p-6 sm:p-8 shadow-2xl backdrop-blur-md text-center relative overflow-hidden">
                {/* Background ambient glow */}
                <div className="absolute -top-24 -left-24 w-48 h-48 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none" />
                <div className="absolute -bottom-24 -right-24 w-48 h-48 bg-violet-500/20 rounded-full blur-3xl pointer-events-none" />

                {/* Shield Icon */}
                <div className="relative inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-gradient-to-tr from-amber-500/20 to-red-500/20 border border-amber-500/30 text-amber-400 mb-6 shadow-lg shadow-amber-500/10">
                    <FaLock size={32} />
                    <span className="absolute -bottom-1 -right-1 flex h-6 w-6 items-center justify-center rounded-full bg-amber-500 text-slate-950 font-bold text-xs shadow">
                        !
                    </span>
                </div>

                <h1 className="text-2xl font-bold tracking-tight text-white mb-2">
                    Access Denied
                </h1>

                <p className="text-sm text-slate-300 mb-6 leading-relaxed">
                    You don't have the required permission to access <strong className="text-amber-300 font-semibold">{permission_name || required_permission}</strong> under the <span className="text-indigo-300 font-semibold">{module_name}</span> module.
                </p>

                {/* Info Card */}
                <div className="bg-slate-900/60 border border-slate-700/60 rounded-xl p-3.5 mb-6 text-left space-y-1.5 text-xs font-mono">
                    <div className="flex justify-between">
                        <span className="text-slate-400">Module:</span>
                        <span className="text-indigo-300 font-semibold">{module_name}</span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-slate-400">Permission:</span>
                        <span className="text-amber-400 font-bold">{required_permission}</span>
                    </div>
                </div>

                {/* Action Buttons */}
                <div className="flex flex-col gap-3">
                    <Link
                        href={getDashboardUrl()}
                        className="w-full flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-xs font-semibold bg-slate-700/60 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-600/60 transition"
                    >
                        <FaArrowLeft size={12} />
                        Return to Dashboard
                    </Link>
                </div>
            </div>
        </div>
    );
}
