import { useState, useRef, useEffect } from "react";
import Loading from "@/Components/Loading";
import NoData from "@/Components/NoData";

export default function EmployeeTable({
    employees,
    isLoading,
    getStatusDisplay,
    handleEdit,
    handleDelete,
    toggleStatus,
}) {
    const [openDropdownId, setOpenDropdownId] = useState(null);
    const [position, setPosition] = useState({ top: 0, left: 0 });

    const handleToggleDropdown = (employeeId, buttonElement) => {
        if (openDropdownId === employeeId) {
            setOpenDropdownId(null);
        } else {
            const rect = buttonElement.getBoundingClientRect();
            const dropdownWidth = 120;
            setPosition({ 
                top: rect.bottom + 5, 
                left: Math.max(0, rect.left - dropdownWidth + 24) 
            });
            setOpenDropdownId(employeeId);
        }
    };

    const handleCloseDropdown = () => {
        setOpenDropdownId(null);
    };

    useEffect(() => {
        const handleClickOutside = (event) => {
            if (openDropdownId && !event.target.closest('.dropdown-container')) {
                setOpenDropdownId(null);
            }
        };
        document.addEventListener("mousedown", handleClickOutside);
        return () => document.removeEventListener("mousedown", handleClickOutside);
    }, [openDropdownId]);

    const getStatusBadge = (status) => {
        const statusMap = {
            1: { text: "Active", class: "bg-green-100 text-green-800" },
            0: { text: "Inactive", class: "bg-red-100 text-red-800" },
        };
        const statusInfo = statusMap[status] || statusMap[0];
        return (
            <span className={`px-2 py-1 rounded-full text-xs ${statusInfo.class}`}>
                {statusInfo.text}
            </span>
        );
    };

    return (
        <div className="p-[15px]">
            <div className="overflow-x-auto tablebxbg p-[15px] rounded-[15px]">
                <table className="min-w-full text-black rounded-2xl dark:text-white">
                    <thead>
                        <tr className="whitespace-nowrap text-left">
                            <th className="p-3">SR No.</th>
                            <th className="p-3">Photo</th>
                            <th className="p-3">Employee ID</th>
                            <th className="p-3">Full Name</th>
                            <th className="p-3">Email</th>
                            <th className="p-3">Phone</th>
                            <th className="p-3">Role</th>
                            <th className="p-3">Department</th>
                            <th className="p-3">Designation</th>
                            <th className="p-3">Status</th>
                            <th className="p-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {isLoading ? (
                            <tr>
                                <td colSpan="11" className="text-center py-10"><Loading /></td>
                            </tr>
                        ) : employees.data.length > 0 ? (
                            employees.data.map((employee, index) => {
                                const member = employee.member || {};
                                const isDropdownOpen = openDropdownId === employee.id;

                                return (
                                    <tr key={employee.id} className="hover:bg-gray-100 dark:hover:bg-[#0a0e25]">
                                        <td className="p-3">{index + 1}</td>
                                        <td className="p-3">
                                            <img src={member.profile_photo_url} alt={member.name} className="w-10 h-10 rounded-full object-cover" />
                                        </td>
                                        <td className="p-3 font-medium">{employee.employee_id}</td>
                                        <td className="p-3">{member.name || '-'}</td>
                                        <td className="p-3">{member.email || '-'}</td>
                                        <td className="p-3">{member.phone || '-'}</td>
                                        <td className="p-3">{member.role_name || '-'}</td>
                                        <td className="p-3">{member.single_department || '-'}</td>
                                        <td className="p-3">{member.single_designation || '-'}</td>
                                        <td className="p-3">
                                            {getStatusBadge(member.status)}
                                        </td>
                                        <td className="p-3">
                                            <div className="flex items-center justify-center gap-2 relative dropdown-container">
                                                <button onClick={(e) => handleToggleDropdown(employee.id, e.currentTarget)} className="text-sm bg-none text-white p-[0]">
                                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <rect x="0.5" y="0.5" width="23" height="23" rx="4.5" stroke="#727272" />
                                                        <path d="M5 13C5.55228 13 6 12.5523 6 12C6 11.4477 5.55228 11 5 11C4.44772 11 4 11.4477 4 12C4 12.5523 4.44772 13 5 13Z" stroke="#727272" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                                                        <path d="M11.9004 13C12.4527 13 12.9004 12.5523 12.9004 12C12.9004 11.4477 11.9004 11C11.3481 11 10.9004 11.4477 10.9004 12C10.9004 12.5523 11.9004 13Z" stroke="#727272" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                                                        <path d="M18.8008 13C19.3531 13 19.8008 12.5523 19.8008 12C19.8008 11.4477 19.3531 11 18.8008 11C17.2485 11 17.8008 11.4477 17.8008 12C17.8008 12.5523 18.2485 13 18.8008 13Z" stroke="#727272" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                                                    </svg>
                                                </button>

                                                {isDropdownOpen && (
                                                    <div className="fixed min-w-[120px] z-50 px-[10px] py-[8px] dropDown rounded-[8px] shadow-md bg-white" style={{ top: `${position.top}px`, left: `${position.left}px` }}>
                                                        <ul>
                                                            <li className="flex items-center gap-[5px] p-2 text-[12px] text-black hover:bg-gray-100 cursor-pointer border-b border-b-[#f2f2f2]">
                                                                <button className="flex items-center gap-[8px]" onClick={() => { handleEdit(employee); handleCloseDropdown(); }}>
                                                                    <svg className="w-[18px]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                                                        <path d="M16.7574 2.99678L14.7574 4.99678H5V18.9968H19V9.23943L21 7.23943V19.9968C21 20.5491 20.5523 20.9968 20 20.9968H4C3.44772 20.9968 3 20.5491 3 19.9968V3.99678C3 3.4445 3.44772 2.99678 4 2.99678H16.7574ZM20.4853 2.09729L21.8995 3.5115L12.7071 12.7039L11.2954 12.7064L11.2929 11.2897L20.4853 2.09729Z" />
                                                                    </svg>
                                                                    Edit
                                                                </button>
                                                            </li>
                                                            <li className="flex items-center gap-[5px] p-2 text-[12px] text-black hover:bg-gray-100 cursor-pointer border-b border-b-[#f2f2f2]">
                                                                <button onClick={() => toggleStatus(employee.uuid, member.status)} className="flex items-center gap-[8px]">
                                                                    {member.status == 0 ? (
                                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" className="w-[18px]" color="currentColor" fill="none">
                                                                            <path d="M5 14.5C5 14.5 6.5 14.5 8.5 18C8.5 18 14.0588 8.83333 19 7" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                                                                        </svg>
                                                                    ) : (
                                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" className="w-[18px]" color="currentColor" fill="none">
                                                                            <path d="M10.2471 6.7402C11.0734 7.56657 11.4866 7.97975 12.0001 7.97975C12.5136 7.97975 12.9268 7.56658 13.7531 6.74022L13.7532 6.7402L15.5067 4.98669L15.5067 4.98668C15.9143 4.5791 16.1182 4.37524 16.3302 4.25283C17.3966 3.63716 18.2748 4.24821 19.0133 4.98669C19.7518 5.72518 20.3628 6.60345 19.7472 7.66981C19.6248 7.88183 19.421 8.08563 19.0134 8.49321L17.26 10.2466C16.4336 11.073 16.0202 11.4864 16.0202 11.9999C16.0202 12.5134 16.4334 12.9266 17.2598 13.7529L19.0133 15.5065C19.4209 15.9141 19.6248 16.1179 19.7472 16.3299C20.3628 17.3963 19.7518 18.2746 19.0133 19.013C18.2749 19.7516 17.3965 20.3626 16.3302 19.7469C16.1182 19.6246 15.9143 19.4208 15.5067 19.013L13.7534 17.2598L13.7533 17.2597C12.9272 16.4336 12.5136 16.02 12.0001 16.02C11.4867 16.02 11.073 16.4336 10.2469 17.2598L10.2469 17.2598L8.49353 19.013C8.0859 19.4208 7.88208 19.6246 7.67005 19.7469C6.60377 20.3626 5.72534 19.7516 4.98693 19.013C4.2484 18.2746 3.63744 17.3963 4.25307 16.3299C4.37549 16.1179 4.5793 15.9141 4.98693 15.5065L6.74044 13.7529C7.56681 12.9266 7.98 12.5134 7.98 11.9999C7.98 11.4864 7.5666 11.073 6.74022 10.2466L4.98685 8.49321C4.57928 8.08563 4.37548 7.88183 4.25307 7.66981C3.63741 6.60345 4.24845 5.72518 4.98693 4.98669C5.72542 4.24821 6.60369 3.63716 7.67005 4.25283C7.88207 4.37524 8.08593 4.5791 8.49352 4.98668L8.49353 4.98669L10.2471 6.7402Z" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                                                                    </svg>
                                                                )}
                                                                {member.status == 0 ? "Activate" : "Deactivate"}
                                                            </button>
                                                        </li>
                                                        <li className="flex items-center gap-[5px] p-2 text-[12px] text-black hover:bg-gray-100 cursor-pointer">
                                                            <button onClick={() => { handleDelete(employee.uuid); handleCloseDropdown(); }} className="flex items-center gap-[8px]">
                                                                <svg xmlns="http://www.w3.org/2000/svg" className="w-[18px] text-red-600" viewBox="0 0 24 24" width="22" height="22" color="currentColor" fill="none">
                                                                    <path d="M19.5 5.5L18.8803 15.5251C18.7219 18.0864 18.6428 19.3671 18.0008 20.2879C17.6833 20.7431 17.2747 21.1273 16.8007 21.416C15.8421 22 14.559 22 11.9927 22C9.42312 22 8.1383 22 7.17905 21.4149C6.7048 21.1257 6.296 20.7408 5.97868 20.2848C5.33688 19.3626 5.25945 18.0801 5.10461 15.5152L4.5 5.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                                                                    <path d="M3 5.5H21M16.0557 5.5L15.3731 4.09173C14.9196 3.15626 14.6928 2.68852 14.3017 2.39681C14.215 2.3321 14.1231 2.27454 14.027 2.2247C13.5939 2 13.0741 2 12.0345 2C10.9688 2 10.436 2 9.99568 2.23412C9.8981 2.28601 9.80498 2.3459 9.71728 2.41317C9.32164 2.7167 9.10063 3.20155 8.65861 4.17126L8.05292 5.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                                                                    <path d="M9.5 16.5L9.5 10.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                                                                    <path d="M14.5 16.5L14.5 10.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                                                                </svg>
                                                                Delete
                                                            </button>
                                                        </li>
                                                    </ul>
                                                </div>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            );
                        })
                    ) : (
                        <tr>
                            <td colSpan="11" className="p-0">
                                <NoData message="No Employees found" iconSize={48} className="w-full" />
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    </div>
);
}
