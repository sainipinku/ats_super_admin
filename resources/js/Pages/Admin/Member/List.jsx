import { Head, usePage, router } from "@inertiajs/react";
import { useState, useEffect } from "react";
import AuthenticatedLayout from "../Layouts/AuthenticatedLayout";
import Modal from "@/Components/Modal";
import NoData from "@/Components/NoData";
import { FaEdit, FaRedo, FaTrash, FaHistory } from "react-icons/fa";
import ConfirmDialog from "@/Components/ConfirmDialog";
import ShowUserProfile from "@/Components/ShowUserProfile";
import { format } from "date-fns";
import { ChevronLeftIcon, ChevronRightIcon } from "@heroicons/react/20/solid";

export default function MembersList({ members, auth, filters }) {
    const [searchTerm, setSearchTerm] = useState(filters.search || "");
    const [statusFilter, setStatusFilter] = useState(filters.status || "");
    const [perPage, setPerPage] = useState(filters.per_page || 10);
    const [hasUserInteracted, setHasUserInteracted] = useState(false);
    const [showConfirmDialog, setShowConfirmDialog] = useState(false);
    const [memberToUpdate, setMemberToUpdate] = useState(null);
    const [newStatus, setNewStatus] = useState(null);

    const updateUrl = (newPage = 1) => {
        const params = {
            search: searchTerm,
            status: statusFilter,
            per_page: perPage,
            page: newPage,
        };
        router.get(route("admin.members.dashboard"), params, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    };

    useEffect(() => {
        if (hasUserInteracted) {
            const debounceTimer = setTimeout(() => {
                updateUrl();
            }, 500);

            return () => clearTimeout(debounceTimer);
        }
    }, [searchTerm, statusFilter, perPage, hasUserInteracted]);

    const handleSearchChange = (e) => {
        setSearchTerm(e.target.value);
        setHasUserInteracted(true);
    };

    const handleStatusFilterChange = (e) => {
        setStatusFilter(e.target.value);
        setHasUserInteracted(true);
    };

    const handlePerPageChange = (e) => {
        setPerPage(e.target.value);
        setHasUserInteracted(true);
    };

    const handlePageChange = (newPage) => {
        if (newPage >= 1 && newPage <= members.last_page) {
            updateUrl(newPage);
        }
    };

    const toggleStatus = (id, currentStatus) => {
        setMemberToUpdate(id);
        setNewStatus(currentStatus == 1 ? 0 : 1);
        setShowConfirmDialog(true);
    };

    const confirmStatusChange = () => {
        router.post(
            route("admin.members.update-status", memberToUpdate),
            { status: newStatus },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setShowConfirmDialog(false);
                    updateUrl(members.current_page);
                },
            }
        );
    };
const getRoleName = (roleId) => {
  const roleMap = {
    "1": "Admin",
    "2": "Super Admin",
    "3": "Doer",
  };
  return roleMap[roleId] || `Role ${roleId}`;
};
const getRoleBadgeColor = (roleId) => {
  const colorMap = {
    "1": "bg-blue-600 text-white",
    "2": "bg-purple-600 text-white",
    "3": "bg-green-600 text-white",
  };
  return colorMap[roleId] || "bg-gray-600 text-white";
};
    const getStatusDisplay = (status) => {
        const statusMap = {
            1: {
                text: "Active",
                class: "bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200",
            },
            0: {
                text: "Inactive",
                class: "bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200",
            },
        };
        return statusMap[status] || statusMap[1];
    };

    return (
        <AuthenticatedLayout>
            <Head title="Members" />
            <div className="min-h-screen py-[40px] memberbg">
                <div className="mt-[64px]">
                    <div className="flex justify-between flex-wrap md:flex-nowrap px-[15px] pt-[5px] pb-[15px]">
                        <div className="flex items-center flex-col md:flex-row gap-[15px] w-full md:w-auto">
                            <select
                                value={statusFilter}
                                onChange={handleStatusFilterChange}
                                className="w-full md:w-auto sm:min-w-[120px] text-sm selectbg border rounded-md px-[25px] py-[12px] focus:outline-none box-shadow-none"
                            >
                                <option value="">All Status</option>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>



                            <input
                                type="text"
                                className="w-full md:w-auto sm:min-w-[120px] text-sm selectbg border rounded-md px-[25px] py-[12px] focus:outline-none box-shadow-none"
                                placeholder="Search Members..."
                                value={searchTerm}
                                onChange={handleSearchChange}
                            />
                        </div>
                    </div>

                    <div class="p-[15px]">
                        <div className="overflow-x-auto tablebxbg p-[15px] rounded-[15px]">
                            <table className="min-w-full text-black rounded-2xl dark:text-white">
                                <thead className="bg-gray-100 dark:bg-gray-800">
                                    <tr className="bg-gray-100 dark:bg-[#0a0e25]">
                                        <th className="p-3 text-left">SR No.</th>
                                        <th className="p-3 text-left">Name</th>
                                        <th className="p-3 text-left">Phone</th>
                                        <th className="p-3 text-left">Departments</th>
                                        <th className="p-3 text-left">Designations</th>
                                         <th className="p-3 text-left">Roles</th>
                                        <th className="p-3 text-left">Status</th>
                                        <th className="p-3 text-left">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {members.data.length > 0 ? (
                                        members.data.map((member, index) => (
                                            <tr
                                                key={member.id}
                                                className="text-left hover:bg-gray-100 dark:hover:bg-[#0a0e25]"
                                            >
                                                <td className="p-3 text-left">{index + 1}</td>
                                                <td className="p-3">
                                                    <div className="flex items-center">
                                                        {member.profile_photo_url ? (
                                                            <img
                                                                src={
                                                                    member.profile_photo_url
                                                                }
                                                                alt={member.name}
                                                                className="w-8 h-8 rounded-full mr-2"
                                                            />
                                                        ) : (
                                                            <ShowUserProfile
                                                                user={member}
                                                                className="!w-8 !h-8 mr-2"
                                                            />
                                                        )}
                                                        <span>{member.name}</span>
                                                    </div>
                                                </td>
                                                <td className="p-3 text-left">
                                                    {member.phone || "-"}
                                                </td>
                                                <td className="p-3 text-left">
                                                    <div className="relative group">
                                                        <div className="flex flex-wrap gap-1">
                                                            {member.departments_data
                                                                ?.slice(0, 2)
                                                                .map((dept) => (
                                                                    <span
                                                                        key={dept.id}
                                                                        className="text-xs bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 px-2 py-0.5 rounded"
                                                                    >
                                                                        {dept.name}
                                                                    </span>
                                                                ))}
                                                            {member.departments_data
                                                                ?.length > 2 && (
                                                                <span className="text-xs bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 px-2 py-0.5 rounded">
                                                                    +
                                                                    {member
                                                                        .departments_data
                                                                        .length - 2}
                                                                </span>
                                                            )}
                                                        </div>
                                                        {member.departments_data
                                                            ?.length > 2 && (
                                                            <div className="absolute z-10 hidden group-hover:block bottom-full left-0 mb-2 w-max max-w-xs bg-white dark:bg-gray-800 shadow-lg rounded-lg p-2 border border-gray-200 dark:border-gray-700">
                                                                <div className="flex flex-wrap gap-1">
                                                                    {member.departments_data?.map(
                                                                        (dept) => (
                                                                            <span
                                                                                key={
                                                                                    dept.id
                                                                                }
                                                                                className="text-xs bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 px-2 py-0.5 rounded"
                                                                            >
                                                                                {
                                                                                    dept.name
                                                                                }
                                                                            </span>
                                                                        )
                                                                    )}
                                                                </div>
                                                                <div className="absolute w-3 h-3 bg-white dark:bg-gray-800 border-b border-r border-gray-200 dark:border-gray-700 transform rotate-45 -bottom-1.5 left-3"></div>
                                                            </div>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="p-3 text-left">
                                                    <div className="relative group">
                                                        <div className="flex flex-wrap gap-1">
                                                            {member.designations_data
                                                                ?.slice(0, 2)
                                                                .map((designation) => (
                                                                    <span
                                                                        key={
                                                                            designation.id
                                                                        }
                                                                        className="text-xs bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 px-2 py-0.5 rounded transition-colors duration-200"
                                                                    >
                                                                        {
                                                                            designation.name
                                                                        }
                                                                    </span>
                                                                ))}
                                                            {member.designations_data
                                                                ?.length > 2 && (
                                                                <span className="text-xs bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 px-2 py-0.5 rounded cursor-pointer">
                                                                    +
                                                                    {member
                                                                        .designations_data
                                                                        .length - 2}
                                                                </span>
                                                            )}
                                                        </div>

                                                        {/* Enhanced Tooltip */}
                                                        {member.designations_data
                                                            ?.length > 2 && (
                                                            <div className="absolute z-20 hidden group-hover:block bottom-full left-0 mb-2 min-w-[200px] max-w-xs bg-white dark:bg-gray-800 shadow-lg rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                                                                <div className="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                                                                    All Designations (
                                                                    {
                                                                        member
                                                                            .designations_data
                                                                            .length
                                                                    }
                                                                    )
                                                                </div>
                                                                <div className="flex flex-wrap gap-2">
                                                                    {member.designations_data.map(
                                                                        (
                                                                            designation
                                                                        ) => (
                                                                            <span
                                                                                key={
                                                                                    designation.id
                                                                                }
                                                                                className="text-xs bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 px-2.5 py-1 rounded-full flex items-center"
                                                                            >
                                                                                {
                                                                                    designation.name
                                                                                }
                                                                            </span>
                                                                        )
                                                                    )}
                                                                </div>
                                                                <div className="absolute w-3 h-3 bg-white dark:bg-gray-800 border-b border-r border-gray-200 dark:border-gray-700 transform rotate-45 -bottom-1.5 left-4"></div>
                                                            </div>
                                                        )}
                                                    </div>
                                                </td>
                                                 <td className="p-3">
  <div className="flex justify-center gap-2 flex-wrap">
    {member.roles && member.roles.length > 0 ? (
      member.roles.map((roleId) => (
        <span
          key={roleId}
          className={`inline-flex items-center gap-x-1 py-1 px-3 rounded-full text-xs font-medium ${getRoleBadgeColor(
            roleId
          )}`}
        >
          {getRoleName(roleId)}
        </span>
      ))
    ) : (
      <span className="text-gray-400">No roles assigned</span>
    )}
  </div>
</td>
                                                <td className="p-3 text-left">
                                                    <span
                                                        className={`px-2 py-1 rounded-full text-xs ${
                                                            getStatusDisplay(
                                                                member.status
                                                            ).class
                                                        }`}
                                                    >
                                                        {
                                                            getStatusDisplay(
                                                                member.status
                                                            ).text
                                                        }
                                                    </span>
                                                </td>
                                                <td className="p-3 text-left">
                                                    <div className="flex gap-2">
                                                    <button
                                                        onClick={() => toggleStatus(member.id, member.status)}
                                                        className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-300 focus:outline-none ${
                                                            member.status == 1 ? 'bg-green-500' : 'bg-red-500'
                                                        }`}
                                                        >
                                                        <span
                                                            className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform duration-300 ${
                                                            member.status == 1 ? 'translate-x-6' : 'translate-x-1'
                                                            }`}
                                                        />
                                                        </button>

                                                    <a href={route("admin.members.details", { uuid: member.uuid, })} className="flex items-center gap-[8px] text-black dark:text-white">
                                                    <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 576 512" height="20" width="20" xmlns="http://www.w3.org/2000/svg"><path d="M572.52 241.4C518.29 135.59 410.93 64 288 64S57.68 135.64 3.48 241.41a32.35 32.35 0 0 0 0 29.19C57.71 376.41 165.07 448 288 448s230.32-71.64 284.52-177.41a32.35 32.35 0 0 0 0-29.19zM288 400a144 144 0 1 1 144-144 143.93 143.93 0 0 1-144 144zm0-240a95.31 95.31 0 0 0-25.31 3.79 47.85 47.85 0 0 1-66.9 66.9A95.78 95.78 0 1 0 288 160z"></path></svg>
                                                    </a></div>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan="8" className="p-4 text-center">
                                                <NoData message="No members found" />
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Pagination */}
                  {members.data.length > 0 && (
    <div className="mt-4 flex justify-between items-center flex-wrap gap-4 p-3 rounded-lg bg-[rgb(228_228_244)] dark:bg-[#5146E64D]">
        <div className="flex items-center">
                   <select
                       value={perPage}
                       onChange={handlePerPageChange}
                       className={`
                           w-full md:w-auto min-w-[120px] text-sm border rounded-md px-4 py-2.5
                           focus:outline-none focus:ring-2 transition-all cursor-pointer appearance-none
                           bg-white text-gray-800 border-gray-300
                           hover:bg-gray-100
                           focus:border-blue-500 focus:ring-blue-200
                           dark:bg-gray-900 dark:text-white dark:border-gray-700
                           dark:hover:bg-[#0a0e25]
                       `}
                   >
                       <option value="10">10 per page</option>
                       <option value="25">25 per page</option>
                       <option value="50">50 per page</option>
                       <option value="100">100 per page</option>
                   </select>
               </div>

        <div className="flex items-center gap-4">
            {/* Replace the static "10/Page" box with the select dropdown */}


            <div className="flex items-center gap-2">
                <button
                    onClick={() => handlePageChange(members.current_page - 1)}
                    disabled={members.current_page == 1}
                    className={`flex items-center justify-center gap-1 px-3 py-1 rounded-full text-sm text-white ${
                        members.current_page == 1
                            ? "opacity-50 cursor-not-allowed bg-[rgb(74_91_127)]"
                            : "bg-[rgb(82_70_230)] hover:bg-[rgb(82_70_230)/0.9]"
                    }`}
                >
                    <ChevronLeftIcon className="size-4" />
                    <span>BACK</span>
                </button>
<div className="flex items-center gap-1">
                    {Array.from({ length: members.last_page }, (_, i) => i + 1).map((page) => {
                        if (
                            page == 1 ||
                            page == 2 ||
                            page == members.last_page - 1 ||
                            page == members.last_page ||
                            (page >= members.current_page - 1 && page <= members.current_page + 1)
                        ) {
                            return (
                                <button
                                    key={page}
                                    onClick={() => handlePageChange(page)}
                                    className={`flex items-center justify-center w-8 h-8 rounded-full text-sm text-white ${
                                        page === members.current_page
                                            ? "bg-[rgb(82_70_230)]"
                                            : "bg-[rgb(74_91_127)] hover:bg-[rgb(74_91_127)/0.9]"
                                    }`}
                                >
                                    {page}
                                </button>
                            );
                        }
                        if (
                            (page == 3 && members.current_page > 4) ||
                            (page == members.last_page - 2 && members.current_page < members.last_page - 3)
                        ) {
                            return (
                                <span
                                    key={`ellipsis-${page}`}
                                    className="flex items-center justify-center w-8 h-8 rounded-full text-sm text-gray-500"
                                >
                                    ...
                                </span>
                            );
                        }
                        return null;
                    })}
                </div>

                <button
                    onClick={() => handlePageChange(members.current_page + 1)}
                    disabled={members.current_page == members.last_page}
                    className={`flex items-center justify-center gap-1 px-3 py-1 rounded-full text-sm text-white ${
                        members.current_page == members.last_page
                            ? "opacity-50 cursor-not-allowed bg-[rgb(74_91_127)]"
                            : "bg-[rgb(82_70_230)] hover:bg-[rgb(82_70_230)/0.9]"
                    }`}
                >
                    <span>NEXT</span>
                    <ChevronRightIcon className="size-4" />
                </button>
            </div>
        </div>
    </div>
)}
                </div>
            </div>

            <ConfirmDialog
                isOpen={showConfirmDialog}
                onClose={() => setShowConfirmDialog(false)}
                onConfirm={confirmStatusChange}
                title="Confirm Status Change"
                message={`Are you sure you want to ${
                    newStatus == 1 ? "activate" : "deactivate"
                } this member?`}
                confirmText="Confirm"
                cancelText="Cancel"
            />
        </AuthenticatedLayout>
    );
}
