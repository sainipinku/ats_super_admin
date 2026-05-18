import { Head, router } from "@inertiajs/react";
import { useEffect, useState } from "react";
import AuthenticatedLayout from "../Layouts/AuthenticatedLayout";
import Modal from "@/Components/Modal";
import ConfirmDialog from "@/Components/ConfirmDialog";

const emptyForm = {
    name: "",
    phone: "",
    email: "",
    password: "",
    confirm_password: "",
    status: "1",
};

export default function Index({ members, filters }) {
    const [search, setSearch] = useState(filters.search || "");
    const [status, setStatus] = useState(filters.status || "");
    const [perPage, setPerPage] = useState(filters.per_page || 10);
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [form, setForm] = useState(emptyForm);
    const [errors, setErrors] = useState({});
    const [isCreating, setIsCreating] = useState(false);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [selectedMember, setSelectedMember] = useState(null);
    const [targetStatus, setTargetStatus] = useState(false);
    const [hasInteracted, setHasInteracted] = useState(false);

    useEffect(() => {
        if (!hasInteracted) return;

        const timeout = setTimeout(() => {
            router.get(
                route("admin.calling-team.index"),
                {
                    search,
                    status,
                    per_page: perPage,
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                }
            );
        }, 400);

        return () => clearTimeout(timeout);
    }, [search, status, perPage, hasInteracted]);

    const handlePageChange = (page) => {
        router.get(
            route("admin.calling-team.index"),
            {
                search,
                status,
                per_page: perPage,
                page,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const openStatusConfirm = (member) => {
        setSelectedMember(member);
        setTargetStatus(Number(member.status) === 1 ? 0 : 1);
        setConfirmOpen(true);
    };

    const updateStatus = () => {
        if (!selectedMember) return;

        router.post(
            route("admin.calling-team.status", selectedMember.id),
            { status: targetStatus },
            {
                preserveScroll: true,
                onSuccess: () => setConfirmOpen(false),
            }
        );
    };

    const submit = (event) => {
        event.preventDefault();
        setIsCreating(true);

        router.post(route("admin.calling-team.store"), form, {
            preserveScroll: true,
            onSuccess: () => {
                setShowCreateModal(false);
                setForm(emptyForm);
                setErrors({});
            },
            onError: (nextErrors) => setErrors(nextErrors),
            onFinish: () => setIsCreating(false),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Calling Team" />

            <div className="min-h-screen bg-slate-100 p-4 sm:p-6">
                <div className="max-w-7xl mx-auto space-y-6">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h1 className="text-2xl sm:text-3xl font-bold text-slate-800">
                                Calling Team
                            </h1>
                            <p className="text-slate-500 mt-1">
                                Create calling team members and manage portal access.
                            </p>
                        </div>

                        <button
                            type="button"
                            onClick={() => {
                                setShowCreateModal(true);
                                setErrors({});
                            }}
                            className="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700"
                        >
                            Create Calling Team Member
                        </button>
                    </div>

                    <div className="grid grid-cols-1 gap-4 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-4">
                        <input
                            type="text"
                            value={search}
                            onChange={(event) => {
                                setSearch(event.target.value);
                                setHasInteracted(true);
                            }}
                            placeholder="Search name, email, phone, username"
                            className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm outline-none focus:border-blue-500"
                        />

                        <select
                            value={status}
                            onChange={(event) => {
                                setStatus(event.target.value);
                                setHasInteracted(true);
                            }}
                            className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm outline-none focus:border-blue-500"
                        >
                            <option value="">All Status</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>

                        <select
                            value={perPage}
                            onChange={(event) => {
                                setPerPage(event.target.value);
                                setHasInteracted(true);
                            }}
                            className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm outline-none focus:border-blue-500"
                        >
                            <option value="10">10 per page</option>
                            <option value="25">25 per page</option>
                            <option value="50">50 per page</option>
                        </select>

                        <button
                            type="button"
                            onClick={() => {
                                setSearch("");
                                setStatus("");
                                setPerPage(10);
                                setHasInteracted(true);
                            }}
                            className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Reset Filters
                        </button>
                    </div>

                    <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-slate-50 text-left text-slate-600">
                                    <tr>
                                        <th className="px-5 py-3 font-medium">Name</th>
                                        <th className="px-5 py-3 font-medium">Phone</th>
                                        <th className="px-5 py-3 font-medium">Email</th>
                                        <th className="px-5 py-3 font-medium">Username</th>
                                        <th className="px-5 py-3 font-medium">Status</th>
                                        <th className="px-5 py-3 font-medium">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {members.data.length > 0 ? (
                                        members.data.map((member) => (
                                            <tr key={member.id}>
                                                <td className="px-5 py-4 font-medium text-slate-900">
                                                    {member.name}
                                                </td>
                                                <td className="px-5 py-4 text-slate-600">
                                                    {member.phone || "-"}
                                                </td>
                                                <td className="px-5 py-4 text-slate-600">
                                                    {member.email || "-"}
                                                </td>
                                                <td className="px-5 py-4 text-slate-600">
                                                    {member.username}
                                                </td>
                                                <td className="px-5 py-4">
                                                    <span
                                                        className={`inline-flex rounded-full px-3 py-1 text-xs font-medium ${
                                                            Number(member.status) === 1
                                                                ? "bg-green-100 text-green-800"
                                                                : "bg-red-100 text-red-800"
                                                        }`}
                                                    >
                                                        {Number(member.status) === 1 ? "Active" : "Inactive"}
                                                    </span>
                                                </td>
                                                <td className="px-5 py-4">
                                                    <button
                                                        type="button"
                                                        onClick={() => openStatusConfirm(member)}
                                                        className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                                    >
                                                        {Number(member.status) === 1 ? "Deactivate" : "Activate"}
                                                    </button>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan="6" className="px-5 py-12 text-center text-slate-500">
                                                No calling team members found.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {members.last_page > 1 && (
                        <div className="flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                            <p>
                                Page {members.current_page} of {members.last_page}
                            </p>
                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    disabled={members.current_page === 1}
                                    onClick={() => handlePageChange(members.current_page - 1)}
                                    className="rounded-lg border border-slate-200 px-4 py-2 disabled:opacity-50"
                                >
                                    Previous
                                </button>
                                <button
                                    type="button"
                                    disabled={members.current_page === members.last_page}
                                    onClick={() => handlePageChange(members.current_page + 1)}
                                    className="rounded-lg border border-slate-200 px-4 py-2 disabled:opacity-50"
                                >
                                    Next
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <Modal
                show={showCreateModal}
                onClose={() => {
                    setShowCreateModal(false);
                    setForm(emptyForm);
                    setErrors({});
                }}
                maxWidth="2xl"
                topCloseButton
                handleTopClose={() => {
                    setShowCreateModal(false);
                    setForm(emptyForm);
                    setErrors({});
                }}
            >
                <div className="p-6">
                    <h2 className="text-2xl font-bold text-slate-800 mb-6">
                        Create Calling Team Member
                    </h2>

                    <form onSubmit={submit} className="grid gap-4">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                Name
                            </label>
                            <input
                                type="text"
                                value={form.name}
                                onChange={(event) =>
                                    setForm((prev) => ({ ...prev, name: event.target.value }))
                                }
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm outline-none focus:border-blue-500"
                            />
                            {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Phone
                                </label>
                                <input
                                    type="text"
                                    value={form.phone}
                                    onChange={(event) =>
                                        setForm((prev) => ({ ...prev, phone: event.target.value }))
                                    }
                                    className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm outline-none focus:border-blue-500"
                                />
                                {errors.phone && (
                                    <p className="mt-1 text-sm text-red-600">{errors.phone}</p>
                                )}
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Email
                                </label>
                                <input
                                    type="email"
                                    value={form.email}
                                    onChange={(event) =>
                                        setForm((prev) => ({ ...prev, email: event.target.value }))
                                    }
                                    className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm outline-none focus:border-blue-500"
                                />
                                {errors.email && (
                                    <p className="mt-1 text-sm text-red-600">{errors.email}</p>
                                )}
                            </div>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Password
                                </label>
                                <input
                                    type="password"
                                    value={form.password}
                                    onChange={(event) =>
                                        setForm((prev) => ({ ...prev, password: event.target.value }))
                                    }
                                    className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm outline-none focus:border-blue-500"
                                />
                                {errors.password && (
                                    <p className="mt-1 text-sm text-red-600">{errors.password}</p>
                                )}
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Confirm Password
                                </label>
                                <input
                                    type="password"
                                    value={form.confirm_password}
                                    onChange={(event) =>
                                        setForm((prev) => ({
                                            ...prev,
                                            confirm_password: event.target.value,
                                        }))
                                    }
                                    className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm outline-none focus:border-blue-500"
                                />
                                {errors.confirm_password && (
                                    <p className="mt-1 text-sm text-red-600">
                                        {errors.confirm_password}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">
                                Status
                            </label>
                            <select
                                value={form.status}
                                onChange={(event) =>
                                    setForm((prev) => ({ ...prev, status: event.target.value }))
                                }
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm outline-none focus:border-blue-500"
                            >
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>

                        <div className="flex justify-end gap-3 pt-4">
                            <button
                                type="button"
                                onClick={() => {
                                    setShowCreateModal(false);
                                    setForm(emptyForm);
                                    setErrors({});
                                }}
                                className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={isCreating}
                                className="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                            >
                                {isCreating ? "Creating..." : "Create Member"}
                            </button>
                        </div>
                    </form>
                </div>
            </Modal>

            <ConfirmDialog
                isOpen={confirmOpen}
                onClose={() => setConfirmOpen(false)}
                onConfirm={updateStatus}
                title="Confirm Status Change"
                message={`Are you sure you want to ${
                    targetStatus ? "activate" : "deactivate"
                } this calling team member?`}
                confirmText="Confirm"
                cancelText="Cancel"
            />
        </AuthenticatedLayout>
    );
}
