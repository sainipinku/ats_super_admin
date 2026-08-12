import ConstructionShell from "@/Pages/Construction/Components/ConstructionShell";
import EmptyState from "@/Pages/Construction/Components/EmptyState";
import SectionCard from "@/Pages/Construction/Components/SectionCard";
import StatCard from "@/Pages/Construction/Components/StatCard";
import StatusBadge from "@/Pages/Construction/Components/StatusBadge";
import Modal from "@/Components/Modal";
import { useForm } from "@inertiajs/react";
import { useState } from "react";

const emptyCompanyForm = {
    name: "",
    legal_name: "",
    email: "",
    phone: "",
    gst_number: "",
    address: "",
    status: "active",
};

export default function CompaniesIndex({ companies }) {
    const [editingCompany, setEditingCompany] = useState(null);

    const form = useForm(emptyCompanyForm);

    const editForm = useForm(emptyCompanyForm);

    const openEditModal = (company) => {
        editForm.clearErrors();
        editForm.setData({
            name: company.name || "",
            legal_name: company.legal_name || "",
            email: company.email || "",
            phone: company.phone || "",
            gst_number: company.gst_number || "",
            address: company.address || "",
            status: company.status || "active",
        });
        setEditingCompany(company);
    };

    const closeEditModal = () => {
        editForm.clearErrors();
        editForm.reset();
        setEditingCompany(null);
    };

    const submitEdit = (e) => {
        e.preventDefault();
        editForm.put(route("super.construction.companies.update", editingCompany.id), {
            preserveScroll: true,
            onSuccess: () => closeEditModal(),
        });
    };

    return (
        <ConstructionShell title="Companies" description="Company master data comes first in Phase 1 and anchors clients, projects, and permissions." variant="super">
            <div className="grid gap-4 md:grid-cols-3">
                <StatCard label="Companies" value={companies.length} />
                <StatCard label="Active" value={companies.filter((company) => company.status === "active").length} />
                <StatCard label="Inactive" value={companies.filter((company) => company.status === "inactive").length} />
            </div>

            <div className="grid gap-6 xl:grid-cols-[420px,1fr]">
                <SectionCard title="Add Company" description="Create the legal and operational company record before downstream setup.">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route("super.construction.companies.store"), { preserveScroll: true, onSuccess: () => form.reset() });
                        }}
                        className="space-y-4"
                    >
                        <InputField form={form} name="name" label="Company Name" />
                        <InputField form={form} name="legal_name" label="Legal Name" />
                        <InputField form={form} name="email" label="Email" />
                        <InputField form={form} name="phone" label="Phone" />
                        <InputField form={form} name="gst_number" label="GST Number" />
                        <TextAreaField form={form} name="address" label="Address" rows={4} />
                        <SelectField form={form} name="status" label="Status" options={[
                            { value: "active", label: "Active" },
                            { value: "inactive", label: "Inactive" },
                        ]} />
                        <button type="submit" disabled={form.processing} className="w-full rounded-xl bg-indigo-600 px-4 py-3 font-medium text-white hover:bg-indigo-500 disabled:opacity-60">
                            {form.processing ? "Saving..." : "Create Company"}
                        </button>
                    </form>
                </SectionCard>

                <SectionCard title="Company Register" description="Current companies available for client and project onboarding.">
                    {companies.length ? (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-left text-sm">
                                <thead className="text-slate-500">
                                    <tr>
                                        <th className="pb-3">Company</th>
                                        <th className="pb-3">Contact</th>
                                        <th className="pb-3">GST</th>
                                        <th className="pb-3">Status</th>
                                        <th className="pb-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {companies.map((company) => (
                                        <tr key={company.id}>
                                            <td className="py-3">
                                                <div className="font-medium text-slate-900 dark:text-white">{company.name}</div>
                                                <div className="text-xs text-slate-500">{company.legal_name || "-"}</div>
                                            </td>
                                            <td className="py-3 text-slate-600 dark:text-slate-300">
                                                <div>{company.email || "-"}</div>
                                                <div className="text-xs text-slate-500">{company.phone || "-"}</div>
                                            </td>
                                            <td className="py-3 text-slate-600 dark:text-slate-300">{company.gst_number || "-"}</td>
                                            <td className="py-3"><StatusBadge value={company.status} /></td>
                                            <td className="py-3">
                                                <button
                                                    type="button"
                                                    onClick={() => openEditModal(company)}
                                                    className="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                                                >
                                                    Edit
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <EmptyState title="No companies yet." description="Create the first company to start Phase 1 setup." />
                    )}
                </SectionCard>
            </div>

            <Modal show={editingCompany !== null} onClose={closeEditModal} maxWidth="2xl">
                <div className="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h3 className="text-lg font-semibold text-slate-900 dark:text-white">Edit Company</h3>
                    <p className="mt-1 text-sm text-slate-500">Update the company master data.</p>
                </div>
                <form onSubmit={submitEdit} className="space-y-4 p-5">
                    <InputField form={editForm} name="name" label="Company Name" />
                    <InputField form={editForm} name="legal_name" label="Legal Name" />
                    <InputField form={editForm} name="email" label="Email" />
                    <InputField form={editForm} name="phone" label="Phone" />
                    <InputField form={editForm} name="gst_number" label="GST Number" />
                    <TextAreaField form={editForm} name="address" label="Address" rows={4} />
                    <SelectField form={editForm} name="status" label="Status" options={[
                        { value: "active", label: "Active" },
                        { value: "inactive", label: "Inactive" },
                    ]} />
                    <div className="flex items-center justify-end gap-3 pt-2">
                        <button
                            type="button"
                            onClick={closeEditModal}
                            disabled={editForm.processing}
                            className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:opacity-60 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={editForm.processing}
                            className="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-500 disabled:opacity-60"
                        >
                            {editForm.processing ? "Updating..." : "Update Company"}
                        </button>
                    </div>
                </form>
            </Modal>
        </ConstructionShell>
    );
}

function InputField({ form, name, label }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</label>
            <input value={form.data[name]} onChange={(e) => form.setData(name, e.target.value)} className="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
            {form.errors[name] ? <p className="mt-1 text-xs text-rose-600">{form.errors[name]}</p> : null}
        </div>
    );
}

function TextAreaField({ form, name, label, rows = 4 }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</label>
            <textarea rows={rows} value={form.data[name]} onChange={(e) => form.setData(name, e.target.value)} className="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
            {form.errors[name] ? <p className="mt-1 text-xs text-rose-600">{form.errors[name]}</p> : null}
        </div>
    );
}

function SelectField({ form, name, label, options }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">{label}</label>
            <select value={form.data[name]} onChange={(e) => form.setData(name, e.target.value)} className="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                {options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
            </select>
        </div>
    );
}