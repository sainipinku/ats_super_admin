import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import AuthenticatedLayout from "./Layouts/AuthenticatedLayout";
// import ActivityLogSectionAdmin from "@/Components/ActivityLogSectionAdmin";
// import AdminPasswordLogSection from "@/Components/AdminPasswordLogSection";
// import AdminCalendar from "@/Components/AdminCalendar";
import {
    BarChart,
    Bar,
    XAxis,
    YAxis,
    ResponsiveContainer,
    PieChart,
    Pie,
    Cell,
    Tooltip,
    Legend,
} from "recharts";

export default function Dashboard({
    stats,
    recentTasks,
    auth,
    initialFilters,
    activityLogs,
    members,
    passwordLogs,
    checkCheckoutToday,
    checkCheckoutList,
}) {
    const isAdmin = auth.guard == "admin";
    const [loading, setLoading] = useState(false);
    const [filters, setFilters] = useState({
        year: initialFilters?.year || new Date().getFullYear(),
        month: initialFilters?.month || new Date().getMonth() + 1,
        member_id: initialFilters?.member_id || null,
    });

    // Generate year options (current year and previous 5 years)
    const yearOptions = Array.from({ length: 6 }, (_, i) => {
        const year = new Date().getFullYear() - i;
        return { value: year, label: year.toString() };
    });

    const monthOptions = [
        { value: 1, label: "January" },
        { value: 2, label: "February" },
        { value: 3, label: "March" },
        { value: 4, label: "April" },
        { value: 5, label: "May" },
        { value: 6, label: "June" },
        { value: 7, label: "July" },
        { value: 8, label: "August" },
        { value: 9, label: "September" },
        { value: 10, label: "October" },
        { value: 11, label: "November" },
        { value: 12, label: "December" },
    ];

    const memberOptions = [
        { value: "", label: "All Members" },
        ...(members || []).map(member => ({
            value: member.id,
            label: member.name
        }))
    ];

    const handleFilterChange = async (newFilters) => {
        setLoading(true);
        try {
            await router.get(
                route("admin.dashboard"),
                {
                    year: newFilters.year,
                    month: newFilters.month,
                    member_id: newFilters.member_id
                },
                {
                    preserveState: true,
                    replace: true,
                    only: ["stats", "recentTasks", "initialFilters", "activityLogs"],
                }
            );
        } catch (error) {
            console.error("Filter change error:", error);
        } finally {
            setLoading(false);
        }
    };

    const updateFilters = (type, value) => {
        const newFilters = {
            ...filters,
            [type]: type === "year" || type === "month" || type === "member_id"
                ? (value === "" ? null : parseInt(value))
                : value,
        };
        setFilters(newFilters);
        handleFilterChange(newFilters);
    };

    // Commented out tasks data
    // const tasksData = [
    //     { name: "Completed", value: stats.completedTasks },
    //     { name: "Pending", value: stats.pendingTasks },
    //     { name: "In Progress", value: stats.inProgressTasks },
    //     { name: "Overdue", value: stats.overdueTasks },
    // ];

    const COLORS = ["#0088FE", "#FFBB28", "#00C49F", "#FF5733"];
    const BAR_COLORS = ["#8884d8", "#82ca9d", "#ffc658", "#ff8042", "#a4de6c"];

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />
            <div className="mt-[75px]">
                <div className="min-h-screen py-[40px] px-[15px]">
                    {/* Member Count Card Only */}
                    <div className="grid grid-cols-1 md:grid-cols-1 gap-4 mb-8">
                        <div className="cards border borderbx rounded-lg p-4 shadow-sm">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Total Members
                                    </h3>
                                    <h2 className="text-3xl font-bold mt-2 text-gray-900 dark:text-white">
                                        {members?.length || 0}
                                    </h2>
                                    <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        Active members in the system
                                    </p>
                                </div>
                                <div className="taskrunning px-[13px] py-[10px] rounded-lg border">
                                    <svg
                                        className="h-8 w-8 text-[#5146E6]"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth={2}
                                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
                                        />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Commented out all other sections */}
                    {/*
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                        <a href={route("admin.task.dashboard", {
                            member_id: initialFilters?.member_id,
                        })}>
                            <div className="cards border borderbx rounded-lg p-4 shadow-sm">
                                <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Total Tasks
                                </h3>
                                <h2 className="text-3xl font-bold mt-2 text-gray-900 dark:text-white">
                                    {stats.totalTasks}
                                </h2>
                                <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {
                                        monthOptions.find(
                                            (m) => m.value === filters.month
                                        )?.label
                                    }{" "}
                                    {filters.year}
                                </p>
                            </div>
                        </a>
                        <a href={route("admin.task.tasklist", {
                            member_id: initialFilters?.member_id, status: "pending"
                        })}>
                            <div className="cards border borderbx rounded-lg p-4 shadow-sm">
                                <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Total Department Pending Tasks Instances
                                </h3>
                                <h2 className="text-3xl font-bold mt-2 text-gray-900 dark:text-white">
                                    {stats.pendingTasks}
                                </h2>
                                <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {
                                        monthOptions.find(
                                            (m) => m.value === filters.month
                                        )?.label
                                    }{" "}
                                    {filters.year}
                                </p>
                            </div>
                        </a>
                        <div className="cards border borderbx rounded-lg p-4 shadow-sm">
                            <h3 className="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Total Department Completed Tasks Instances
                            </h3>
                            <h2 className="text-3xl font-bold mt-2 text-gray-900 dark:text-white">
                                {stats.completedTasks}
                            </h2>
                            <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {
                                    monthOptions.find(
                                        (m) => m.value === filters.month
                                    )?.label
                                }{" "}
                                {filters.year}
                            </p>
                        </div>
                    </div>

                    <div className="cards border borderbx rounded-lg p-4 shadow-sm">
                        <div className="flex flex-col gap-[5px] sm:flex-row items-center mb-6 pb-[15px] border-b-[1px] border-b-[#5146E64D]">
                            <div className="w-full sm:w-auto text-lg font-semibold text-second-color">
                                Filters:
                            </div>
                            <select
                                value={filters.year}
                                onChange={(e) =>
                                    updateFilters("year", e.target.value)
                                }
                                className="w-full sm:w-auto sm:min-w-[120px] text-sm selectbg border rounded-md px-3 py-2"
                                disabled={loading}
                            >
                                {yearOptions.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                            <select
                                value={filters.month}
                                onChange={(e) =>
                                    updateFilters("month", e.target.value)
                                }
                                className="w-full sm:w-auto sm:min-w-[120px] text-sm selectbg border rounded-md px-3 py-2"
                                disabled={loading}
                            >
                                {monthOptions.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                            {isAdmin && (
                                <select
                                    value={filters.member_id || ""}
                                    onChange={(e) => updateFilters("member_id", e.target.value)}
                                    className="w-full sm:w-auto min-w-[180px] text-sm selectbg border rounded-md px-3 py-2"
                                    disabled={loading}
                                >
                                    {memberOptions.map((option) => (
                                        <option key={option.value} value={option.value}>
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            )}
                            {loading && (
                                <svg
                                    className="animate-spin h-5 w-5 text-gray-500"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        className="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        strokeWidth="4"
                                    ></circle>
                                    <path
                                        className="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                    ></path>
                                </svg>
                            )}
                        </div>

                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                            <div className="cards border borderbx rounded-lg p-4 shadow-sm">
                                <h3 className="text-lg font-semibold mb-4 text-gray-900 dark:text-white">
                                    Tasks Instances Overview
                                </h3>
                                <div className="h-80">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <PieChart>
                                            <Pie
                                                data={tasksData}
                                                cx="50%"
                                                cy="50%"
                                                innerRadius={70}
                                                outerRadius={90}
                                                paddingAngle={5}
                                                dataKey="value"
                                                label={({ name, percent }) =>
                                                    `${name}: ${(
                                                        percent * 100
                                                    ).toFixed(0)}%`
                                                }
                                            >
                                                {tasksData.map((entry, index) => (
                                                    <Cell
                                                        key={`cell-${index}`}
                                                        fill={
                                                            COLORS[
                                                                index %
                                                                    COLORS.length
                                                            ]
                                                        }
                                                    />
                                                ))}
                                            </Pie>
                                            <Tooltip
                                                contentStyle={{
                                                    backgroundColor: "#1e293b",
                                                    borderColor: "#334155",
                                                    borderRadius: "0.5rem",
                                                }}
                                            />
                                            <Legend />
                                        </PieChart>
                                    </ResponsiveContainer>
                                </div>
                            </div>

                            {isAdmin &&
                                stats.tasksByDepartment &&
                                stats.tasksByDepartment.length > 0 && (
                                    <div className="cards border borderbx rounded-lg p-4 shadow-sm">
                                        <h3 className="text-lg font-semibold mb-4 text-gray-900 dark:text-white">
                                            Tasks Instances by Department
                                        </h3>
                                        <div className="h-80">
                                            <ResponsiveContainer
                                                width="100%"
                                                height="100%"
                                            >
                                                <BarChart
                                                    data={stats.tasksByDepartment}
                                                    margin={{
                                                        top: 20,
                                                        right: 30,
                                                        left: 20,
                                                        bottom: 5,
                                                    }}
                                                    layout="vertical"
                                                >
                                                    <XAxis
                                                        type="number"
                                                        tick={{
                                                            fill: "#6b7280",
                                                            stroke: "transparent",
                                                        }}
                                                        axisLine={{
                                                            stroke: "#6b7280",
                                                            strokeWidth: 0.5,
                                                        }}
                                                    />
                                                    <YAxis
                                                        dataKey="name"
                                                        type="category"
                                                        width={120}
                                                        tick={{
                                                            fill: "#6b7280",
                                                            stroke: "transparent",
                                                            fontSize: 12,
                                                        }}
                                                        axisLine={{
                                                            stroke: "#6b7280",
                                                            strokeWidth: 0.5,
                                                        }}
                                                    />
                                                    <Tooltip
                                                        contentStyle={{
                                                            backgroundColor:
                                                                "#1e293b",
                                                            borderColor: "#334155",
                                                            borderRadius: "0.5rem",
                                                            color: "#f3f4f6",
                                                        }}
                                                        itemStyle={{
                                                            color: "#f3f4f6",
                                                        }}
                                                        labelStyle={{
                                                            color: "#f3f4f6",
                                                            fontWeight: "bold",
                                                        }}
                                                    />
                                                    <Legend
                                                        wrapperStyle={{
                                                            color: "#6b7280",
                                                            paddingTop: "20px",
                                                        }}
                                                    />
                                                    <Bar
                                                        dataKey="value"
                                                        name="Tasks"
                                                        radius={[0, 4, 4, 0]}
                                                        label={{
                                                            position: "right",
                                                            fill: "#6b7280",
                                                            fontSize: 12,
                                                            formatter: (value) =>
                                                                value > 0
                                                                    ? value
                                                                    : "",
                                                        }}
                                                    >
                                                        {stats.tasksByDepartment.map(
                                                            (entry, index) => (
                                                                <Cell
                                                                    key={`cell-${index}`}
                                                                    fill={
                                                                        BAR_COLORS[
                                                                            index %
                                                                                BAR_COLORS.length
                                                                        ]
                                                                    }
                                                                    strokeWidth={1}
                                                                />
                                                            )
                                                        )}
                                                    </Bar>
                                                </BarChart>
                                            </ResponsiveContainer>
                                        </div>
                                        <div className="mt-4 text-sm text-gray-500 dark:text-gray-400">
                                            Showing task distribution across{" "}
                                            {stats.tasksByDepartment.length}{" "}
                                            departments for{" "}
                                            {
                                                monthOptions.find(
                                                    (m) => m.value === filters.month
                                                )?.label
                                            }{" "}
                                            {filters.year}
                                        </div>
                                    </div>
                                )}
                        </div>
                    </div>

                    <div className="cards border borderbx rounded-lg p-4 shadow-sm mt-[30px]">
                        <div className="mb-[20px] py-4 border-b border-gray-200 dark:border-gray-700">
                            <h3 className="text-lg font-semibold mb-2 text-gray-900 dark:text-white">
                                Recent Tasks
                            </h3>
                            <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                Showing tasks from{" "}
                                {
                                    monthOptions.find(
                                        (m) => m.value === filters.month
                                    )?.label
                                }{" "}
                                {filters.year}
                            </p>
                        </div>
                        <div className="overflow-x-auto tablebxbg p-[15px] rounded-[15px]">
                            <table className="min-w-full text-black rounded-2xl dark:text-white">
                                <thead className="">
                                    <tr className="whitespace-nowrap text-left">
                                        <th className="p-3">Task</th>
                                        {isAdmin && (
                                            <th className="p-3">Assigned To</th>
                                        )}
                                        <th className="p-3">Due Date</th>
                                        <th className="p-3 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="">
                                    {recentTasks.map((task) => (
                                        <tr
                                            key={task.id}
                                            className="hover:bg-gray-100 dark:hover:bg-[#0a0e25]"
                                        >
                                            <td className="p-3">
                                                {task.title}
                                            </td>
                                            {isAdmin && (
                                                <td className="p-3">
                                                    {task.assigned_to_name ||
                                                        "Unassigned"}
                                                </td>
                                            )}
                                            <td className="p-3">
                                                {new Date(
                                                    task.due_date
                                                ).toLocaleDateString()}
                                            </td>
                                            <td align="center" className="px-6 py-4 whitespace-nowrap text-center">
                                                <span
                                                    className={`px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${
                                                        task.status ==
                                                        "completed"
                                                            ? "bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200"
                                                            : task.status ==
                                                              "in_progress"
                                                            ? "bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                                                            : "bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200"
                                                    }`}
                                                >
                                                    {task.status
                                                        .split("_")
                                                        .map(
                                                            (word) =>
                                                                word
                                                                    .charAt(0)
                                                                    .toUpperCase() +
                                                                word.slice(1)
                                                        )
                                                        .join(" ")}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <AdminCalendar />
                    {activityLogs && (
                        <ActivityLogSectionAdmin activityLogs={activityLogs} />
                    )}
                    <AdminPasswordLogSection passwordLogs={passwordLogs} auth={auth} />
                    */}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
