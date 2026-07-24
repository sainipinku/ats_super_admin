const departmentOptions = [
    'Administration', 'Architecture', 'Planning', 'Engineering', 'Survey',
    'Drafting', 'Accounts', 'HR', 'GIS & Mapping', 'Data Collection',
    'Development', 'Project Management', 'Operations',
];

const designationOptions = [
    'CEO', 'Director', 'General Manager', 'Senior Consultant', 'Project Manager',
    'Team Leader', 'Manager (Planning)', 'Manager (Survey)', 'Manager (Engineering)',
    'Manager (Admin)', 'Manager (Accounts)', 'Manager (Data Collection)',
    'Manager (Drawings)', 'Architect', 'Senior Architect', 'Civil Engineer',
    'Junior Civil Engineer', 'Site Engineer', 'Planning Engineer', 'CAD Engineer',
    'AutoCAD Designer', 'Draftsman', 'Senior Draftsman', 'Junior Draftsman',
    'Surveyor', 'Senior Surveyor', 'Assistant Surveyor', 'GIS Engineer',
    'GIS Analyst', 'Quantity Surveyor', 'HR Manager', 'HR Executive',
    'Accountant', 'Senior Accountant', 'Admin Executive', 'Receptionist',
    'Office Assistant', 'Assistant', 'Supervisor', 'Site Supervisor', 'Driver',
    'Office Boy', 'Store Keeper',
];

export default function EmployeeFilters({
    departmentFilter,
    handleDepartmentFilterChange,
    designationFilter,
    handleDesignationFilterChange,
    statusFilter,
    handleStatusFilterChange,
    perPage,
    handlePerPageChange,
    searchTerm,
    handleSearchChange,
    filterSelectClass,
    handleCreate,
    departmentOptions: deptOptions,
    designationOptions: desigOptions,
}) {
    return (
        <div className="flex justify-between flex-wrap md:flex-nowrap px-[15px] pt-[5px] pb-[15px]">
            <div className="flex items-center flex-col md:flex-row gap-[15px] w-full md:w-auto">
                <select value={departmentFilter} onChange={handleDepartmentFilterChange} className={filterSelectClass}>
                    <option value="">All Departments</option>
                    {deptOptions.map((dept) => (
                        <option key={dept} value={dept}>{dept}</option>
                    ))}
                </select>

                <select value={designationFilter} onChange={handleDesignationFilterChange} className={filterSelectClass}>
                    <option value="">All Designations</option>
                    {desigOptions.map((desig) => (
                        <option key={desig} value={desig}>{desig}</option>
                    ))}
                </select>

                <select value={statusFilter} onChange={handleStatusFilterChange} className={filterSelectClass}>
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>

                <select value={perPage} onChange={handlePerPageChange} className={filterSelectClass}>
                    <option value="10">10 per page</option>
                    <option value="25">25 per page</option>
                    <option value="50">50 per page</option>
                    <option value="100">100 per page</option>
                </select>

                <input type="text"
                    className="w-full md:w-auto sm:min-w-[120px] text-sm rounded-md px-4 py-3 focus:outline-none focus:ring-2 transition-all bg-white text-gray-800 placeholder-gray-500 border border-gray-300 hover:border-gray-400 focus:border-blue-500 focus:ring-blue-200 dark:bg-gray-900 dark:text-white dark:placeholder-gray-400 dark:border-gray-700 dark:hover:border-gray-600 dark:focus:border-blue-600 dark:focus:ring-blue-900/30"
                    placeholder="Search Employee..."
                    value={searchTerm}
                    onChange={handleSearchChange}
                />
            </div>

            <div className="flex items-center space-x-1 mt-[10px] md:mt-[0]">
                <button onClick={handleCreate} className="flex items-center gap-[5px] px-[20px] py-[12px] text-[15px] text-white rounded-[10px] bluebtbg">
                    Add Employee
                </button>
            </div>
        </div>
    );
}
