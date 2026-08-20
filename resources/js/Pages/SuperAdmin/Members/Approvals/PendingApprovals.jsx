import { useEffect, useState, useCallback, useMemo } from 'react';
import { Head, router, Link, usePage } from '@inertiajs/react';
import {
    FaUserCheck, FaUserClock, FaUserTimes, FaSearch, FaCheckCircle,
    FaTimesCircle, FaBuilding, FaMapMarkerAlt, FaMobileAlt, FaEnvelope,
    FaUsers, FaCheckDouble, FaChevronLeft, FaChevronRight,
    FaUserShield, FaUserTie, FaHardHat, FaDraftingCompass, FaTruck,
    FaSpinner, FaClipboardCheck, FaPhoneAlt, FaCity, FaIdBadge,
    FaUserPlus, FaInfoCircle, FaExclamationTriangle, FaBullseye,
} from 'react-icons/fa';
import StatusBadge from '@/Components/StatusBadge';
import Loading from '@/Components/Loading';
import NoData from '@/Components/NoData';
import ReactPagination from '@/Components/ReactPagination';
import { useAlerts } from '@/Components/Alerts';

const CONSTRUCTION_ROLE_ICONS = {
    driver:     { icon: FaTruck,             color: 'text-orange-600 bg-orange-100 dark:bg-orange-900/30 dark:text-orange-400', phase: 'Phase 2 — Driver Allocation' },
    surveyor:   { icon: FaMapMarkerAlt,      color: 'text-blue-600 bg-blue-100 dark:bg-blue-900/30 dark:text-blue-400',     phase: 'Phase 3 — Survey Team Setup' },
    draftsman:  { icon: FaDraftingCompass,   color: 'text-purple-600 bg-purple-100 dark:bg-purple-900/30 dark:text-purple-400', phase: 'Phase 4 — Drafting & Approval' },
    supervisor: { icon: FaHardHat,           color: 'text-green-600 bg-green-100 dark:bg-green-900/30 dark:text-green-400',   phase: 'Phase 5-6 — Execution & Client Review' },
    admin:      { icon: FaUserShield,        color: 'text-slate-600 bg-slate-100 dark:bg-slate-900/30 dark:text-slate-400',   phase: 'All Phases — Management' },
    'super-admin': { icon: FaUserTie,        color: 'text-red-600 bg-red-100 dark:bg-red-900/30 dark:text-red-400',         phase: 'All Phases — Full Access' },
};

function getRoleMeta(slug) {
    const s = (slug || '').toString().toLowerCase();
    if (CONSTRUCTION_ROLE_ICONS[s]) return CONSTRUCTION_ROLE_ICONS[s];
    return { icon: FaUsers, color: 'text-gray-600 bg-gray-100 dark:bg-gray-900/30 dark:text-gray-400', phase: 'Construction Team' };
}

export default function PendingApprovals({ stats, roles, departments, assignable_admins, activeTab = 'pending' }) {
    const { flash } = usePage().props;
    const { successAlert, errorAlert } = useAlerts();
    const [tab, setTab] = useState(activeTab);
    const [search, setSearch] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const [members, setMembers] = useState([]);
    const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0, per_page: 15 });
    const [loading, setLoading] = useState(true);
    const [modalMember, setModalMember] = useState(null);
    const [modalType, setModalType] = useState(null);
    const [submitting, setSubmitting] = useState(false);

    const [approveForm, setApproveForm] = useState({
        roles: [],
        departments: [],
        designations: [],
        assigned_admin_id: null,
        approval_remark: '',
    });

    const [rejectForm, setRejectForm] = useState({ approval_remark: '' });
    const [selectedIds, setSelectedIds] = useState([]);
    const [showBulkApprove, setShowBulkApprove] = useState(false);
    const [bulkForm, setBulkForm] = useState({
        roles: [],
        departments: [],
        designations: [],
        assigned_admin_id: null,
    });

    useEffect(() => {
        if (flash?.success) successAlert(flash.success);
        if (flash?.error)   errorAlert(flash.error);
    }, [flash, successAlert, errorAlert]);

    useEffect(() => {
        const t = setTimeout(() => setDebouncedSearch(search), 300);
        return () => clearTimeout(t);
    }, [search]);

    const loadMembers = useCallback((page = 1) => {
        setLoading(true);
        const endpointMap = {
            pending:  route('super.members.approvals.api.pending'),
            approved: route('super.members.approvals.api.approved'),
            rejected: route('super.members.approvals.api.rejected'),
        };

        const params = new URLSearchParams({
            page,
            per_page: pagination.per_page,
        });
        if (debouncedSearch.trim()) params.set('search', debouncedSearch.trim());

        axios.get(`${endpointMap[tab]}?${params.toString()}`)
            .then(({ data }) => {
                const d = data.members || {};
                setMembers(d.data || []);
                setPagination({
                    current_page: d.current_page || 1,
                    last_page:    d.last_page    || 1,
                    total:        d.total        || 0,
                    per_page:     d.per_page     || 15,
                });
                setSelectedIds([]);
            })
            .catch((err) => {
                errorAlert(err.response?.data?.message || 'Failed to load data.');
            })
            .finally(() => setLoading(false));
    }, [tab, debouncedSearch, pagination.per_page, errorAlert]);

    useEffect(() => {
        loadMembers(1);
    }, [tab, loadMembers]);

    const openApproveModal = (member) => {
        setModalMember(member);
        setModalType('approve');
        setApproveForm({
            roles: [],
            departments: [],
            designations: [],
            assigned_admin_id: null,
            approval_remark: '',
        });
    };

    const openRejectModal = (member) => {
        setModalMember(member);
        setModalType('reject');
        setRejectForm({ approval_remark: '' });
    };

    const submitApprove = () => {
        if (!modalMember || !approveForm.roles.length) {
            errorAlert('Please assign at least one construction role.');
            return;
        }
        setSubmitting(true);
        const form = { ...approveForm, roles: approveForm.roles };
        if (!form.departments.length) delete form.departments;
        if (!form.designations.length) delete form.designations;
        if (!form.assigned_admin_id)  delete form.assigned_admin_id;

        axios.post(route('super.members.approvals.api.approve', modalMember.id), form)
            .then(({ data }) => {
                successAlert(data.message || 'Member approved successfully.');
                setModalMember(null);
                setModalType(null);
                loadMembers(pagination.current_page);
            })
            .catch((err) => {
                errorAlert(err.response?.data?.message || 'Approval failed.');
            })
            .finally(() => setSubmitting(false));
    };

    const submitReject = () => {
        if (!rejectForm.approval_remark || rejectForm.approval_remark.length < 5) {
            errorAlert('Please provide a rejection reason (min 5 chars).');
            return;
        }
        setSubmitting(true);
        axios.post(route('super.members.approvals.api.reject', modalMember.id), rejectForm)
            .then(({ data }) => {
                successAlert(data.message || 'Registration rejected.');
                setModalMember(null);
                setModalType(null);
                loadMembers(pagination.current_page);
            })
            .catch((err) => {
                errorAlert(err.response?.data?.message || 'Rejection failed.');
            })
            .finally(() => setSubmitting(false));
    };

    const submitBulkApprove = () => {
        if (!bulkForm.roles.length || !selectedIds.length) {
            errorAlert('Select members and assign at least one role.');
            return;
        }
        setSubmitting(true);
        const form = { ...bulkForm, member_ids: selectedIds };
        if (!form.departments.length) delete form.departments;
        if (!form.designations.length) delete form.designations;
        if (!form.assigned_admin_id)  delete form.assigned_admin_id;

        axios.post(route('super.members.approvals.api.bulk-approve', 'bulk'), form)
            .then(({ data }) => {
                successAlert(data.message || 'Bulk approve finished.');
                setShowBulkApprove(false);
                loadMembers(pagination.current_page);
            })
            .catch((err) => {
                errorAlert(err.response?.data?.message || 'Bulk approve failed.');
            })
            .finally(() => setSubmitting(false));
    };

    const toggleSelect = (id) => {
        setSelectedIds((prev) => prev.includes(id) ? prev.filter(i => i !== id) : [...prev, id]);
    };

    const toggleSelectAll = () => {
        if (selectedIds.length === members.length) setSelectedIds([]);
        else setSelectedIds(members.map(m => m.id));
    };

    const totalPending = stats?.pending  || 0;
    const totalApproved = stats?.approved || 0;
    const totalRejected = stats?.rejected || 0;

    const availableDesignations = useMemo(() => {
        if (!approveForm.departments.length) return [];
        return departments.flatMap(d =>
            (d.designations || []).filter(des => approveForm.departments.includes(d.id))
        );
    }, [approveForm.departments, departments]);

    const statsCards = [
        {
            key: 'pending', label: 'Pending Approvals', value: totalPending,
            icon: FaUserClock, color: 'from-amber-500 to-yellow-500',
            badge: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
            badgeText: 'Awaiting review',
        },
        {
            key: 'approved', label: 'Active Members', value: totalApproved,
            icon: FaUserCheck, color: 'from-green-500 to-emerald-500',
            badge: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
            badgeText: 'Can login',
        },
        {
            key: 'rejected', label: 'Rejected', value: totalRejected,
            icon: FaUserTimes, color: 'from-red-500 to-rose-500',
            badge: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
            badgeText: 'Registration denied',
        },
        {
            key: 'self_reg', label: 'Self Registered', value: stats?.self_registered || 0,
            icon: FaUserPlus, color: 'from-blue-500 to-indigo-500',
            badge: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
            badgeText: `${stats?.total || 0} total`,
        },
    ];

    const tabs = [
        { key: 'pending',  label: 'Pending',   icon: FaUserClock, count: totalPending,  color: 'text-amber-600' },
        { key: 'approved', label: 'Approved',  icon: FaUserCheck, count: totalApproved, color: 'text-green-600' },
        { key: 'rejected', label: 'Rejected',  icon: FaUserTimes, count: totalRejected, color: 'text-red-600' },
    ];

    return (
        <div className="min-h-screen bg-slate-50 dark:bg-[#070628] p-4 md:p-6 lg:p-8">
            <Head title={`${tab === 'pending' ? 'Pending Approvals' : tab === 'approved' ? 'Approved Members' : 'Rejected Registrations'} | CadMax SuperAdmin`} />

            <div className="max-w-[1600px] mx-auto space-y-6">
                <header className="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 mb-2">
                            <FaClipboardCheck className="text-blue-600" />
                            <span>Construction ERP · Members · Approvals Workflow</span>
                        </div>
                        <h1 className="text-2xl md:text-3xl font-extrabold text-slate-800 dark:text-white tracking-tight">
                            Registration &amp; Approval Center
                        </h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Approve self-registered users and assign construction roles (Driver · Surveyor · Draftsman · Supervisor) for Phase 2-6 workflow.
                        </p>
                    </div>
                    <nav className="flex flex-wrap items-center gap-2 text-xs">
                        <Link href={route('super.construction.dashboard')} className="px-3 py-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition inline-flex items-center gap-1.5">
                            <FaChevronLeft size={10} /> ERP Dashboard
                        </Link>
                        <Link href={route('super.departments')} className="px-3 py-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition inline-flex items-center gap-1.5">
                            <FaBuilding size={11} /> Departments
                        </Link>
                        <Link href={route('super.role.list')} className="px-3 py-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition inline-flex items-center gap-1.5">
                            <FaIdBadge size={11} /> Roles
                        </Link>
                    </nav>
                </header>

                <section className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {statsCards.map(card => (
                        <button
                            key={card.key}
                            onClick={() => { setTab(card.key === 'self_reg' ? 'pending' : card.key); }}
                            className={`group relative text-left overflow-hidden rounded-2xl p-5 bg-white dark:bg-slate-800 border transition-all hover:shadow-lg hover:-translate-y-0.5 ${
                                tab === card.key
                                    ? 'border-blue-500 ring-2 ring-blue-500/30 shadow-lg shadow-blue-500/10'
                                    : 'border-slate-200 dark:border-slate-700'
                            }`}
                        >
                            <div className={`absolute -right-4 -top-4 w-24 h-24 rounded-full opacity-15 bg-gradient-to-br ${card.color} group-hover:opacity-25 transition`} />
                            <div className="flex items-start justify-between relative">
                                <div className={`w-11 h-11 rounded-xl bg-gradient-to-br ${card.color} text-white flex items-center justify-center shadow-md`}>
                                    <card.icon size={20} />
                                </div>
                                <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full border ${card.badge}`}>
                                    {card.badgeText}
                                </span>
                            </div>
                            <div className="mt-4">
                                <div className="text-3xl font-black text-slate-800 dark:text-white tabular-nums">
                                    {card.value}
                                </div>
                                <div className="text-sm text-slate-500 dark:text-slate-400 font-medium mt-0.5">
                                    {card.label}
                                </div>
                            </div>
                        </button>
                    ))}
                </section>

                <section className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                    <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 p-4 lg:p-5 border-b border-slate-100 dark:border-slate-700">
                        <div className="flex flex-wrap gap-1 bg-slate-100 dark:bg-slate-900/60 p-1 rounded-xl w-fit">
                            {tabs.map(t => (
                                <button
                                    key={t.key}
                                    onClick={() => setTab(t.key)}
                                    className={`px-3.5 py-2 rounded-lg text-sm font-semibold transition inline-flex items-center gap-2 ${
                                        tab === t.key
                                            ? 'bg-white dark:bg-slate-800 text-slate-800 dark:text-white shadow-sm ring-1 ring-slate-200 dark:ring-slate-700'
                                            : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300'
                                    }`}
                                >
                                    <t.icon size={14} className={tab === t.key ? t.color : ''} />
                                    {t.label}
                                    <span className={`text-[10px] font-bold px-1.5 py-0.5 rounded-full ${
                                        tab === t.key
                                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'
                                            : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300'
                                    }`}>{t.count}</span>
                                </button>
                            ))}
                        </div>

                        <div className="flex flex-col sm:flex-row lg:items-center gap-2.5">
                            <div className="relative">
                                <FaSearch className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" size={14} />
                                <input
                                    type="text"
                                    placeholder="Search name, phone, email, company..."
                                    value={search}
                                    onChange={e => setSearch(e.target.value)}
                                    className="w-full sm:w-80 pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 text-sm text-slate-700 dark:text-slate-200 placeholder-slate-400 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 outline-none transition"
                                />
                            </div>
                            {tab === 'pending' && selectedIds.length > 0 && (
                                <button
                                    onClick={() => {
                                        setBulkForm({ roles: [], departments: [], designations: [], assigned_admin_id: null });
                                        setShowBulkApprove(true);
                                    }}
                                    className="px-4 py-2.5 rounded-xl bg-gradient-to-r from-green-600 to-emerald-600 text-white text-sm font-bold hover:from-green-700 hover:to-emerald-700 shadow-md shadow-green-500/20 transition inline-flex items-center gap-2"
                                >
                                    <FaCheckDouble /> Bulk Approve ({selectedIds.length})
                                </button>
                            )}
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-slate-50 dark:bg-slate-900/40 text-slate-500 dark:text-slate-400">
                                <tr>
                                    {tab === 'pending' && (
                                        <th className="w-10 pl-5 pr-2 py-3.5 text-left">
                                            <input
                                                type="checkbox"
                                                className="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800"
                                                checked={members.length > 0 && selectedIds.length === members.length}
                                                onChange={toggleSelectAll}
                                            />
                                        </th>
                                    )}
                                    <th className="px-3 py-3.5 text-left font-semibold">Member</th>
                                    <th className="px-3 py-3.5 text-left font-semibold">Contact</th>
                                    <th className="px-3 py-3.5 text-left font-semibold">Location</th>
                                    <th className="px-3 py-3.5 text-left font-semibold">Registered</th>
                                    <th className="px-3 py-3.5 text-left font-semibold">Status / Role</th>
                                    <th className="px-4 py-3.5 text-right font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-700/60">
                                {loading ? (
                                    <tr><td colSpan={7} className="py-20 text-center"><Loading className="!h-8 !w-8" /></td></tr>
                                ) : members.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="py-16">
                                            <NoData message={
                                                tab === 'pending'
                                                    ? 'No pending approvals 🎉  Everyone is approved!'
                                                    : tab === 'approved'
                                                        ? 'No approved members yet.'
                                                        : 'No rejected registrations.'
                                            } />
                                        </td>
                                    </tr>
                                ) : members.map(m => (
                                    <tr key={m.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition">
                                        {tab === 'pending' && (
                                            <td className="pl-5 pr-2 py-4">
                                                <input
                                                    type="checkbox"
                                                    className="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800"
                                                    checked={selectedIds.includes(m.id)}
                                                    onChange={() => toggleSelect(m.id)}
                                                />
                                            </td>
                                        )}
                                        <td className="px-3 py-4">
                                            <div className="flex items-center gap-3 min-w-[240px]">
                                                <div className="relative">
                                                    <div className="w-11 h-11 rounded-xl bg-gradient-to-br from-slate-200 to-slate-300 dark:from-slate-600 dark:to-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-200 font-black shadow-sm overflow-hidden">
                                                        {m.profile_photo
                                                            ? <img src={m.profile_photo} alt="" className="w-full h-full object-cover" />
                                                            : m.name?.split(' ').slice(0,2).map(s => s[0]).join('').toUpperCase().slice(0,2)
                                                        }
                                                    </div>
                                                    <span className={`absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 rounded-full border-2 border-white dark:border-slate-800 ${
                                                        m.registration_source === 'mobile_api' ? 'bg-indigo-500' : 'bg-blue-400'
                                                    }`} title={`Registered via ${m.registration_source === 'mobile_api' ? 'Mobile App' : 'Web Portal'}`} />
                                                </div>
                                                <div className="min-w-0">
                                                    <div className="font-bold text-slate-800 dark:text-slate-100 truncate">
                                                        {m.name}
                                                    </div>
                                                    <div className="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1.5 flex-wrap">
                                                        {m.company_name && <><FaBuilding size={10} /> <span className="truncate max-w-[180px]">{m.company_name}</span><span className="text-slate-300 dark:text-slate-600">·</span></>}
                                                        <span className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400">{m.registration_source === 'mobile_api' ? 'MOBILE' : 'WEB'}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-3 py-4">
                                            <div className="space-y-1">
                                                <div className="flex items-center gap-2 text-slate-600 dark:text-slate-300 text-xs">
                                                    <FaPhoneAlt size={11} className="text-green-500 flex-shrink-0" />
                                                    <span className="font-mono font-medium">{m.phone}</span>
                                                </div>
                                                {m.email && (
                                                    <div className="flex items-center gap-2 text-slate-500 dark:text-slate-400 text-xs max-w-[220px]">
                                                        <FaEnvelope size={11} className="text-blue-500 flex-shrink-0" />
                                                        <span className="truncate">{m.email}</span>
                                                    </div>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-3 py-4">
                                            {m.state || m.city ? (
                                                <div className="space-y-0.5 text-xs">
                                                    <div className="flex items-center gap-1.5 text-slate-600 dark:text-slate-300">
                                                        <FaCity size={10} className="text-slate-400" />
                                                        <span className="font-medium">{m.city || '—'}</span>
                                                    </div>
                                                    <div className="flex items-center gap-1.5 text-slate-500 dark:text-slate-400">
                                                        <FaMapMarkerAlt size={10} className="text-rose-500" />
                                                        <span>{m.state || '—'}</span>
                                                    </div>
                                                </div>
                                            ) : (
                                                <span className="text-xs text-slate-400 italic">Not specified</span>
                                            )}
                                        </td>
                                        <td className="px-3 py-4">
                                            <div className="text-xs">
                                                <div className="font-semibold text-slate-700 dark:text-slate-200">
                                                    {m.created_at ? m.created_at.split(' ')[0] : '—'}
                                                </div>
                                                <div className="text-slate-400 dark:text-slate-500 text-[10px] mt-0.5">
                                                    {m.approved_at
                                                        ? <span className="text-green-600 dark:text-green-400 font-medium">Approved {m.approved_at.split(' ')[0]}</span>
                                                        : m.rejected_at
                                                            ? <span className="text-red-600 dark:text-red-400 font-medium">Rejected {m.rejected_at.split(' ')[0]}</span>
                                                            : m.approved_by_name
                                                                ? `By ${m.approved_by_name}`
                                                                : m.phone_verified ? 'Phone verified' : 'Awaiting action'}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-3 py-4">
                                            <div className="space-y-2 max-w-[220px]">
                                                <StatusBadge
                                                    status={tab === 'approved' ? 'active' : tab === 'rejected' ? 'inactive' : 'pending'}
                                                    label={m.status_text}
                                                    className="!text-xs"
                                                />
                                                {(tab === 'approved' || tab === 'rejected') && m.role_names !== 'Not Assigned' ? (
                                                    <div className="flex flex-wrap gap-1">
                                                        {(m.role_names || '').split(',').map((r, i) => {
                                                            const found = roles.find(rl => rl.name.trim() === r.trim());
                                                            const meta = getRoleMeta(found?.slug);
                                                            const Icon = meta.icon;
                                                            return (
                                                                <span key={i} className={`inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[10px] font-bold ${meta.color}`}>
                                                                    <Icon size={9} />{r.trim()}
                                                                </span>
                                                            );
                                                        })}
                                                    </div>
                                                ) : tab === 'pending' ? (
                                                    <div className="flex items-center gap-1 text-[10px] text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 rounded px-2 py-0.5 border border-amber-200/70 dark:border-amber-800/40 w-fit">
                                                        <FaBullseye size={10} /> No role assigned yet
                                                    </div>
                                                ) : null}
                                                {m.approval_remark && (
                                                    <div className="text-[10px] text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/40 rounded px-2 py-1 max-w-[220px] truncate border border-slate-100 dark:border-slate-700" title={m.approval_remark}>
                                                        <FaInfoCircle className="inline mr-1" size={9} />{m.approval_remark}
                                                    </div>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-4">
                                            <div className="flex items-center justify-end gap-2">
                                                {tab === 'pending' && (
                                                    <>
                                                        <button
                                                            onClick={() => openApproveModal(m)}
                                                            className="px-3 py-1.5 rounded-lg bg-gradient-to-r from-green-500 to-emerald-500 text-white text-xs font-bold hover:from-green-600 hover:to-emerald-600 shadow-sm transition inline-flex items-center gap-1.5"
                                                            title="Approve & assign roles"
                                                        >
                                                            <FaCheckCircle size={11} /> Approve
                                                        </button>
                                                        <button
                                                            onClick={() => openRejectModal(m)}
                                                            className="px-3 py-1.5 rounded-lg bg-white dark:bg-slate-700 border border-red-200 dark:border-red-900/50 text-red-600 dark:text-red-400 text-xs font-bold hover:bg-red-50 dark:hover:bg-red-900/20 transition inline-flex items-center gap-1.5"
                                                            title="Reject registration"
                                                        >
                                                            <FaTimesCircle size={11} /> Reject
                                                        </button>
                                                    </>
                                                )}
                                                {tab === 'approved' && (
                                                    <button
                                                        onClick={() => openApproveModal(m)}
                                                        className="px-3 py-1.5 rounded-lg bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-600 transition inline-flex items-center gap-1.5"
                                                    >
                                                        <FaUsers size={11} /> Edit Roles
                                                    </button>
                                                )}
                                                {tab === 'rejected' && (
                                                    <button
                                                        onClick={() => openApproveModal(m)}
                                                        className="px-3 py-1.5 rounded-lg bg-gradient-to-r from-blue-500 to-indigo-500 text-white text-xs font-bold hover:from-blue-600 hover:to-indigo-600 shadow-sm transition inline-flex items-center gap-1.5"
                                                        title="Re-approve (override rejection)"
                                                    >
                                                        <FaUserCheck size={11} /> Re-approve
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {!loading && pagination.total > 0 && (
                        <div className="px-4 py-4 border-t border-slate-100 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div className="text-xs text-slate-500 dark:text-slate-400">
                                Showing <b>{Math.min((pagination.current_page - 1) * pagination.per_page + 1, pagination.total)}</b> to{' '}
                                <b>{Math.min(pagination.current_page * pagination.per_page, pagination.total)}</b> of{' '}
                                <b>{pagination.total}</b> entries
                            </div>
                            <ReactPagination
                                currentPage={pagination.current_page}
                                lastPage={pagination.last_page}
                                total={pagination.total}
                                perPage={pagination.per_page}
                                onPageChange={loadMembers}
                            />
                        </div>
                    )}
                </section>

                <footer className="py-6 text-center text-xs text-slate-400 dark:text-slate-500">
                    CadMax Construction ERP · Phase 1: Registration &amp; User Approval → Phase 2: Driver Allocation → Phase 3: Survey Teams → Phase 4: Drafting → Phase 5: Billing → Phase 6: Client Review → Handover
                </footer>
            </div>

            {modalMember && modalType === 'approve' && (
                <ApproveRejectModal
                    open
                    onClose={() => { setModalMember(null); setModalType(null); }}
                    type="approve"
                    member={modalMember}
                    roles={roles}
                    departments={departments}
                    assignable_admins={assignable_admins}
                    form={approveForm}
                    setForm={setApproveForm}
                    submitting={submitting}
                    onSubmit={submitApprove}
                />
            )}
            {modalMember && modalType === 'reject' && (
                <ApproveRejectModal
                    open
                    onClose={() => { setModalMember(null); setModalType(null); }}
                    type="reject"
                    member={modalMember}
                    roles={roles}
                    departments={departments}
                    assignable_admins={assignable_admins}
                    form={rejectForm}
                    setForm={setRejectForm}
                    submitting={submitting}
                    onSubmit={submitReject}
                />
            )}
            {showBulkApprove && (
                <ApproveRejectModal
                    open
                    bulk
                    count={selectedIds.length}
                    onClose={() => setShowBulkApprove(false)}
                    type="approve"
                    member={{ name: `${selectedIds.length} selected members` }}
                    roles={roles}
                    departments={departments}
                    assignable_admins={assignable_admins}
                    form={bulkForm}
                    setForm={setBulkForm}
                    submitting={submitting}
                    onSubmit={submitBulkApprove}
                />
            )}
        </div>
    );
}

function ApproveRejectModal({
    open, onClose, type, member, roles, departments, assignable_admins,
    form, setForm, submitting, onSubmit, bulk = false, count = 0,
}) {
    if (!open) return null;

    const availableDesignations = useMemo(() => {
        if (!form.departments?.length) return [];
        const filtered = departments.filter(d => form.departments.includes(d.id));
        const result = [];
        filtered.forEach(d => (d.designations || []).forEach(des => result.push({ ...des, department_name: d.name })));
        return result;
    }, [form.departments, departments]);

    const toggleArray = (key, value) => {
        setForm(prev => {
            const arr = Array.isArray(prev[key]) ? [...prev[key]] : [];
            const idx = arr.indexOf(value);
            if (idx >= 0) arr.splice(idx, 1);
            else arr.push(value);
            return { ...prev, [key]: arr };
        });
    };

    const isReject = type === 'reject';
    const title = isReject
        ? `Reject Registration · ${member.name}`
        : bulk
            ? `Bulk Approve · ${count} Members`
            : member.status === 2
                ? `Re-approve · ${member.name}`
                : `Approve & Assign Roles · ${member.name}`;

    return (
        <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/60 backdrop-blur-sm">
            <div className="w-full sm:max-w-2xl max-h-[92vh] overflow-hidden rounded-t-3xl sm:rounded-2xl bg-white dark:bg-[#0a0833] shadow-2xl ring-1 ring-black/5 flex flex-col animate-[slideup_.2s_ease-out]">
                <div className={`px-6 py-4.5 border-b border-slate-100 dark:border-slate-800 flex items-start justify-between gap-4 ${
                    isReject ? 'bg-gradient-to-r from-red-50 to-rose-50 dark:from-red-950/40 dark:to-rose-950/40'
                            : 'bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-950/40 dark:to-emerald-950/40'
                }`}>
                    <div className="flex items-start gap-3">
                        <div className={`w-11 h-11 rounded-xl flex items-center justify-center shadow-sm flex-shrink-0 ${
                            isReject ? 'bg-gradient-to-br from-red-500 to-rose-500' : 'bg-gradient-to-br from-green-500 to-emerald-500'
                        } text-white`}>
                            {isReject ? <FaTimesCircle size={20} /> : bulk ? <FaCheckDouble size={20} /> : <FaCheckCircle size={20} />}
                        </div>
                        <div>
                            <h3 className="font-black text-lg text-slate-800 dark:text-white leading-tight">{title}</h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                {isReject
                                    ? 'Rejection reason will be shown to member in status check.'
                                    : bulk
                                        ? 'Assign roles once — applied to every selected member.'
                                        : 'Assign construction role(s) to plug this member into Phase 2-6 ERP flow.'}
                            </p>
                        </div>
                    </div>
                    <button onClick={onClose} className="p-2 -m-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-md hover:bg-black/5 dark:hover:bg-white/5 transition">
                        <FaTimesCircle size={16} />
                    </button>
                </div>

                <div className="overflow-y-auto flex-1 px-6 py-5 space-y-5">
                    {!bulk && !isReject && member && (
                        <div className="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-900/30 p-3.5 flex items-center gap-3">
                            <div className="w-11 h-11 rounded-xl bg-gradient-to-br from-slate-200 to-slate-300 dark:from-slate-600 dark:to-slate-700 flex items-center justify-center font-black text-slate-600 dark:text-slate-200 overflow-hidden">
                                {member.profile_photo
                                    ? <img src={member.profile_photo} alt="" className="w-full h-full object-cover" />
                                    : member.name?.split(' ').slice(0,2).map(s=>s[0]).join('').toUpperCase().slice(0,2)}
                            </div>
                            <div className="min-w-0 flex-1 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-0.5 text-xs">
                                <div className="font-bold text-slate-700 dark:text-slate-100 sm:col-span-2">{member.name}</div>
                                <div className="text-slate-500 dark:text-slate-400 flex items-center gap-1.5"><FaMobileAlt size={10}/><span className="font-mono">{member.phone}</span></div>
                                {member.email && <div className="text-slate-500 dark:text-slate-400 flex items-center gap-1.5 truncate"><FaEnvelope size={10}/><span className="truncate">{member.email}</span></div>}
                                {(member.state || member.city) && <div className="text-slate-500 dark:text-slate-400 flex items-center gap-1.5 sm:col-span-2"><FaMapMarkerAlt size={10} className="text-rose-500"/>{member.city}{member.city && member.state ? ', ' : ''}{member.state}</div>}
                                {member.company_name && <div className="text-slate-500 dark:text-slate-400 flex items-center gap-1.5 sm:col-span-2"><FaBuilding size={10}/>{member.company_name}</div>}
                            </div>
                        </div>
                    )}

                    {!isReject && (
                        <div>
                            <label className="text-xs font-bold text-slate-700 dark:text-slate-200 inline-flex items-center gap-1.5 mb-2.5">
                                <FaUsers className="text-blue-500" size={12} /> Construction Roles
                                <span className="text-red-500">*</span>
                                <span className="ml-auto text-[10px] font-normal text-slate-400 dark:text-slate-500">Select at least 1</span>
                            </label>
                            <div className="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                                {roles.map(r => {
                                    const meta = getRoleMeta(r.slug);
                                    const Icon = meta.icon;
                                    const selected = form.roles?.includes(r.id);
                                    return (
                                        <button
                                            type="button"
                                            key={r.id}
                                            onClick={() => toggleArray('roles', r.id)}
                                            className={`text-left rounded-xl p-3 border-2 transition-all ${
                                                selected
                                                    ? 'border-blue-500 ring-2 ring-blue-500/20 bg-blue-50 dark:bg-blue-950/30'
                                                    : 'border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600 bg-white dark:bg-slate-800/50'
                                            }`}
                                        >
                                            <div className="flex items-start gap-2.5">
                                                <div className={`w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 ${meta.color}`}>
                                                    <Icon size={16} />
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <div className="font-bold text-sm text-slate-800 dark:text-slate-100 leading-tight flex items-center gap-1.5">
                                                        {r.name}
                                                        {selected && <FaCheckCircle className="text-blue-500" size={12} />}
                                                    </div>
                                                    <div className="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5 leading-snug">{meta.phase}</div>
                                                </div>
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                            {form.roles && !form.roles.length && (
                                <div className="mt-2 text-[11px] text-amber-600 dark:text-amber-400 inline-flex items-center gap-1 bg-amber-50 dark:bg-amber-950/30 border border-amber-200/60 dark:border-amber-900/50 rounded-md px-2 py-1">
                                    <FaExclamationTriangle size={10} /> No role selected — choose at least one.
                                </div>
                            )}
                        </div>
                    )}

                    {!isReject && (
                        <div>
                            <label className="text-xs font-bold text-slate-700 dark:text-slate-200 inline-flex items-center gap-1.5 mb-2.5">
                                <FaBuilding className="text-slate-500" size={12} /> Departments
                                <span className="ml-auto text-[10px] font-normal text-slate-400 dark:text-slate-500">Optional · multi-select</span>
                            </label>
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                {departments.map(d => {
                                    const selected = form.departments?.includes(d.id);
                                    return (
                                        <button
                                            key={d.id}
                                            type="button"
                                            onClick={() => toggleArray('departments', d.id)}
                                            className={`text-left rounded-lg px-3 py-2 text-xs font-semibold border transition ${
                                                selected
                                                    ? 'border-indigo-400 bg-indigo-50 text-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-800'
                                                    : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700'
                                            }`}
                                        >
                                            <div className="flex items-center justify-between gap-2">
                                                <span className="truncate">{d.name}</span>
                                                {selected && <FaCheckCircle className="text-indigo-500 flex-shrink-0" size={11} />}
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    {!isReject && form.departments?.length > 0 && availableDesignations.length > 0 && (
                        <div>
                            <label className="text-xs font-bold text-slate-700 dark:text-slate-200 inline-flex items-center gap-1.5 mb-2.5">
                                <FaIdBadge className="text-purple-500" size={12} /> Designations
                                <span className="ml-auto text-[10px] font-normal text-slate-400 dark:text-slate-500">
                                    Based on {form.departments.length} selected department(s)
                                </span>
                            </label>
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                {availableDesignations.map(des => {
                                    const selected = form.designations?.includes(des.id);
                                    return (
                                        <button
                                            key={des.id}
                                            type="button"
                                            onClick={() => toggleArray('designations', des.id)}
                                            className={`text-left rounded-lg px-3 py-2 text-xs font-semibold border transition ${
                                                selected
                                                    ? 'border-purple-400 bg-purple-50 text-purple-800 dark:bg-purple-950/40 dark:text-purple-300 dark:border-purple-800'
                                                    : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700'
                                            }`}
                                        >
                                            <div className="flex items-start justify-between gap-2">
                                                <div className="min-w-0">
                                                    <div className="truncate">{des.name}</div>
                                                    {des.department_name && <div className="text-[9px] font-normal opacity-70 mt-0.5">{des.department_name}</div>}
                                                </div>
                                                {selected && <FaCheckCircle className="text-purple-500 mt-0.5 flex-shrink-0" size={11} />}
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    {!isReject && assignable_admins && assignable_admins.length > 0 && (
                        <div>
                            <label htmlFor="assigned_admin" className="text-xs font-bold text-slate-700 dark:text-slate-200 inline-flex items-center gap-1.5 mb-2">
                                <FaUserShield className="text-slate-500" size={12} /> Reporting Admin <span className="font-normal text-slate-400 dark:text-slate-500">(Optional)</span>
                            </label>
                            <select
                                id="assigned_admin"
                                value={form.assigned_admin_id || ''}
                                onChange={(e) => setForm(prev => ({ ...prev, assigned_admin_id: e.target.value ? parseInt(e.target.value) : null }))}
                                className="w-full rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-3.5 py-2.5 text-sm text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 outline-none"
                            >
                                <option value="">— No assigned admin —</option>
                                {assignable_admins.map(a => (
                                    <option key={a.id} value={a.id}>{a.name} · ID #{a.id}</option>
                                ))}
                            </select>
                        </div>
                    )}

                    <div>
                        <label htmlFor="approval_remark" className="text-xs font-bold text-slate-700 dark:text-slate-200 inline-flex items-center gap-1.5 mb-2">
                            <FaInfoCircle className={isReject ? 'text-red-500' : 'text-slate-400'} size={12} />
                            {isReject ? 'Rejection Reason' : 'Approval Note'}
                            {isReject && <span className="text-red-500">*</span>}
                            <span className="ml-auto text-[10px] font-normal text-slate-400 dark:text-slate-500">
                                {isReject ? form.approval_remark?.length || 0 : form.approval_remark?.length || 0}/1000
                            </span>
                        </label>
                        <textarea
                            id="approval_remark"
                            rows={isReject ? 3 : 2}
                            maxLength={1000}
                            value={form.approval_remark || ''}
                            onChange={e => setForm(prev => ({ ...prev, approval_remark: e.target.value }))}
                            placeholder={isReject
                                ? 'Explain why this registration is being rejected (shown to user in status check)'
                                : 'Optional note for internal audit trail...'}
                            className="w-full rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-3.5 py-2.5 text-sm text-slate-700 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-500 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 outline-none resize-none"
                        />
                        {isReject && (!form.approval_remark || form.approval_remark.length < 5) && (
                            <div className="mt-1.5 text-[11px] text-red-500 dark:text-red-400 inline-flex items-center gap-1">
                                <FaExclamationTriangle size={10} /> Minimum 5 characters required.
                            </div>
                        )}
                    </div>
                </div>

                <div className="px-6 py-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/30 flex items-center justify-end gap-2.5">
                    <button
                        onClick={onClose}
                        disabled={submitting}
                        className="px-4 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-sm font-semibold hover:bg-slate-100 dark:hover:bg-slate-700 transition disabled:opacity-50"
                    >
                        Cancel
                    </button>
                    <button
                        onClick={onSubmit}
                        disabled={submitting || (isReject && (!form.approval_remark || form.approval_remark.length < 5))}
                        className={`px-5 py-2 rounded-xl text-white text-sm font-bold shadow-md hover:shadow-lg transition disabled:opacity-60 disabled:cursor-not-allowed inline-flex items-center gap-2 ${
                            isReject
                                ? 'bg-gradient-to-r from-red-500 to-rose-500 hover:from-red-600 hover:to-rose-600 shadow-red-500/20'
                                : 'bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 shadow-green-500/20'
                        }`}
                    >
                        {submitting && <Loading className="!h-4 !w-4 !border-2 !border-white !border-t-transparent" />}
                        {isReject ? <><FaTimesCircle size={13}/> Confirm Reject</> : <><FaCheckCircle size={13}/> {bulk ? `Bulk Approve ${count}` : 'Approve & Assign Roles'}</>}
                    </button>
                </div>
            </div>
        </div>
    );
}
