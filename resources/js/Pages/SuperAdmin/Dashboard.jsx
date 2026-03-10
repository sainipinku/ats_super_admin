import { Head, router } from "@inertiajs/react";
import { useState, useRef } from "react";
import ActivityLogSection from "@/Components/ActivityLogSection";
import PasswordLogSection from "@/Components/PasswordLogSection";
import ImageActionLogSection from "@/Components/ImageActionLogSection";
import Calendar from "@/Components/Calendar";

import {
    FaTasks,
    FaCalendarAlt,
    FaCheckCircle,
    FaExclamationTriangle,
    FaChartLine,
    FaChartPie,
    FaChartBar,
    FaFileExport,
    FaPrint,
} from "react-icons/fa";
import {
    LineChart,
    Line,
    BarChart,
    Bar,
    PieChart,
    Pie,
    Cell,
    ResponsiveContainer,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    Legend,
} from "recharts";
import AuthenticatedLayout from "./Layouts/AuthenticatedLayout";

const CHART_COLORS = {
    primary: "#3b82f6",
    primaryDark: "#1d4ed8",
    success: "#10b981",
    successDark: "#059669",
    warning: "#f59e0b",
    warningDark: "#d97706",
    danger: "#ef4444",
    dangerDark: "#dc2626",
    info: "#06b6d4",
    infoDark: "#0891b2",
    gray: "#6b7280",
    grayLight: "#f3f4f6",
};

const chartTheme = {
    colors: CHART_COLORS,
    fontFamily:
        'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
    fontSize: 12,
    axis: {
        tickColor: CHART_COLORS.gray,
        tickLine: false,
    },
    grid: {
        stroke: CHART_COLORS.grayLight,
        strokeDasharray: "3 3",
    },
    tooltip: {
        backgroundColor: "white",
        border: "none",
        borderRadius: "6px",
        boxShadow:
            "0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)",
    },
    legend: {
        wrapperStyle: {
            paddingTop: "10px",
            fontSize: "12px",
        },
    },
};

const TASK_TYPE_COLORS = {
    one_time: CHART_COLORS.primary,
    recurring: CHART_COLORS.success,
};

const TASK_STATUS_COLORS = {
    pending: CHART_COLORS.warning,
    completed: CHART_COLORS.success,
    overdue: CHART_COLORS.danger,
    in_progress: CHART_COLORS.info,
};

export default function Dashboard({
    statsData,
    auth,
    stats,
    activityLogs,
    members,
    passwordLogs,
    imageActionLogs,
}) {
    const [activeTab, setActiveTab] = useState("monthly");
    const [loading, setLoading] = useState(false);
    const [filters, setFilters] = useState({
        year: stats.tasks.filter?.year || new Date().getFullYear(),
        month: stats.tasks.filter?.month || new Date().getMonth() + 1,
        member_id: stats.tasks.filter?.member_id || null,
    });
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
        ...(members || []).map((member) => ({
            value: member.id,
            label: member.name,
        })),
    ];
    const handleFilterChange = async (year, month, member_id) => {
        setLoading(true);
        try {
            await router.get(
                route("super.dashboard"),
                { year, month, member_id },
                {
                    preserveState: true,
                    replace: true,
                    only: ["stats"],
                }
            );
            setFilters({ year, month, member_id });
        } catch (error) {
            console.error("Filter change error:", error);
        } finally {
            setLoading(false);
        }
    };
    const updateFilters = (type, value) => {
        const newFilters = {
            ...filters,
            [type]:
                type === "year" || type === "month" || type === "member_id"
                    ? value === ""
                        ? null
                        : parseInt(value)
                    : value,
        };
        setFilters(newFilters);
        const debounceTimer = setTimeout(() => {
            handleFilterChange(
                newFilters.year,
                newFilters.month,
                newFilters.member_id
            );
        }, 300);
        return () => clearTimeout(debounceTimer);
    };
    const taskTypeData = Object.entries(stats.tasks.types || {}).map(
        ([name, value]) => ({
            name: name == "one_time" ? "One Time" : "Recurring",
            value,
            color: TASK_TYPE_COLORS[name],
        })
    );
    const taskStatusData = Object.entries(stats.tasks.statuses || {}).map(
        ([name, value]) => ({
            name:
                name.charAt(0).toUpperCase() + name.slice(1).replace("_", " "),
            value,
            color: TASK_STATUS_COLORS[name],
        })
    );
    const exportRef = useRef();
    const handleExport = (type = "print") => {
        const selectedYear =
            yearOptions.find((opt) => opt.value === filters.year)?.label ||
            filters.year;
        const selectedMonth =
            monthOptions.find((opt) => opt.value === filters.month)?.label ||
            filters.month;
        const selectedMember =
            memberOptions.find((opt) => opt.value === filters.member_id)
                ?.label || "All Members";
        const printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Dashboard Report</title>
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 20px; }
                .filters { margin-bottom: 20px; padding: 10px; background: #f5f5f5; border-radius: 5px; }
                .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 20px; }
                .stat-card { border: 1px solid #ddd; padding: 15px; border-radius: 5px; }
                .chart-section { margin-bottom: 30px; }
                .chart-title { font-weight: bold; margin-bottom: 10px; }
                .chart-container { position: relative; height: 300px; margin-bottom: 20px; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
                .charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
                @media print {
                    body { margin: 0; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Task Management Dashboard Report</h1>
                <p>Generated on: ${new Date().toLocaleDateString()}</p>
            </div>

            <div class="filters">
                <h3>Applied Filters:</h3>
                <p><strong>Year:</strong> ${selectedYear}</p>
                <p><strong>Month:</strong> ${selectedMonth}</p>
                <p><strong>Member:</strong> ${selectedMember}</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h4>Total Tasks</h4>
                    <p>${stats.tasks.total || "No Data Available"}</p>
                </div>
                <div class="stat-card">
                    <h4>Closed Tasks</h4>
                    <p>${stats.tasks.completed || "No Data Available"}</p>
                </div>
                <div class="stat-card">
                    <h4>Active Staff</h4>
                    <p>${stats.staff.count || "No Data Available"}</p>
                </div>
                <div class="stat-card">
                    <h4>Departments</h4>
                    <p>${stats.departments.count || "No Data Available"}</p>
                </div>
                <div class="stat-card">
                    <h4>Overdue Tasks</h4>
                    <p>${stats.tasks.overdue || "No Data Available"}</p>
                </div>
            </div>

            <div class="charts-grid">
                <div class="chart-section">
                    <div class="chart-title">Task Type Distribution</div>
                    <div class="chart-container">
                        <canvas id="typeChart"></canvas>
                    </div>
                    <p>One Time: ${
                        taskTypeData.find((d) => d.name === "One Time")
                            ?.value || 0
                    } tasks</p>
                    <p>Recurring: ${
                        taskTypeData.find((d) => d.name === "Recurring")
                            ?.value || 0
                    } tasks</p>
                </div>

                <div class="chart-section">
                    <div class="chart-title">Task Status Distribution</div>
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                    ${taskStatusData
                        .map(
                            (status) =>
                                `<p>${status.name}: ${status.value} tasks</p>`
                        )
                        .join("")}
                </div>

                <div class="chart-section">
                    <div class="chart-title">Task Completion Trend</div>
                    <div class="chart-container">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>

                <div class="chart-section">
                    <div class="chart-title">Creation vs Completion</div>
                    <div class="chart-container">
                        <canvas id="comparisonChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="chart-section">
                <div class="chart-title">Monthly Trends Data</div>
                ${
                    stats.tasks.trend
                        ? stats.tasks.trend
                              .map(
                                  (month) =>
                                      `<p>${month.month}: Created - ${month.total}, Completed - ${month.completed}</p>`
                              )
                              .join("")
                        : "<p>No trend data available</p>"
                }
            </div>

            <div class="footer">
                <p>Report generated from Task Management System</p>
            </div>

            <div class="no-print">
                <button onclick="window.print()">Print Now</button>
                <button onclick="window.close()">Close</button>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Task Type Distribution Chart
                    const typeCtx = document.getElementById('typeChart').getContext('2d');
                    new Chart(typeCtx, {
                        type: 'pie',
                        data: {
                            labels: ${JSON.stringify(
                                taskTypeData.map((d) => d.name)
                            )},
                            datasets: [{
                                data: ${JSON.stringify(
                                    taskTypeData.map((d) => d.value)
                                )},
                                backgroundColor: ${JSON.stringify(
                                    taskTypeData.map((d) => d.color)
                                )},
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                },
                                title: {
                                    display: true,
                                    text: 'Task Type Distribution'
                                }
                            }
                        }
                    });
                    const statusCtx = document.getElementById('statusChart').getContext('2d');
                    new Chart(statusCtx, {
                        type: 'pie',
                        data: {
                            labels: ${JSON.stringify(
                                taskStatusData.map((d) => d.name)
                            )},
                            datasets: [{
                                data: ${JSON.stringify(
                                    taskStatusData.map((d) => d.value)
                                )},
                                backgroundColor: ${JSON.stringify(
                                    taskStatusData.map((d) => d.color)
                                )},
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                },
                                title: {
                                    display: true,
                                    text: 'Task Status Distribution'
                                }
                            }
                        }
                    });
                    const trendCtx = document.getElementById('trendChart').getContext('2d');
                    new Chart(trendCtx, {
                        type: 'line',
                        data: {
                            labels: ${JSON.stringify(
                                stats.tasks.trend
                                    ? stats.tasks.trend.map((d) => d.month)
                                    : []
                            )},
                            datasets: [{
                                label: 'Completed Tasks',
                                data: ${JSON.stringify(
                                    stats.tasks.trend
                                        ? stats.tasks.trend.map(
                                              (d) => d.completed
                                          )
                                        : []
                                )},
                                backgroundColor: '${CHART_COLORS.success}',
                                borderColor: '${CHART_COLORS.success}',
                                borderWidth: 2,
                                fill: false
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                },
                                title: {
                                    display: true,
                                    text: 'Completion Trend'
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                    const comparisonCtx = document.getElementById('comparisonChart').getContext('2d');
                    new Chart(comparisonCtx, {
                        type: 'line',
                        data: {
                            labels: ${JSON.stringify(
                                stats.tasks.trend
                                    ? stats.tasks.trend.map((d) => d.month)
                                    : []
                            )},
                            datasets: [
                                {
                                    label: 'Created Tasks',
                                    data: ${JSON.stringify(
                                        stats.tasks.trend
                                            ? stats.tasks.trend.map(
                                                  (d) => d.total
                                              )
                                            : []
                                    )},
                                    backgroundColor: '${CHART_COLORS.primary}',
                                    borderColor: '${CHART_COLORS.primary}',
                                    borderWidth: 2,
                                    fill: false
                                },
                                {
                                    label: 'Completed Tasks',
                                    data: ${JSON.stringify(
                                        stats.tasks.trend
                                            ? stats.tasks.trend.map(
                                                  (d) => d.completed
                                              )
                                            : []
                                    )},
                                    backgroundColor: '${CHART_COLORS.success}',
                                    borderColor: '${CHART_COLORS.success}',
                                    borderWidth: 2,
                                    fill: false
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                },
                                title: {
                                    display: true,
                                    text: 'Creation vs Completion'
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                    setTimeout(() => {
                        window.print();
                    }, 1000);
                });
            </script>
        </body>
        </html>
    `;
        if (type === "print") {
            const printWindow = window.open("", "_blank");
            printWindow.document.write(printContent);
            printWindow.document.close();
        } else {
            const iframe = document.createElement("iframe");
            iframe.style.display = "none";
            document.body.appendChild(iframe);

            const iframeDoc =
                iframe.contentDocument || iframe.contentWindow.document;
            iframeDoc.write(printContent);
            iframeDoc.close();
            iframe.onload = function () {
                const checkChartsLoaded = () => {
                    const iframeWindow = iframe.contentWindow;
                    if (iframeWindow.chartsLoaded) {
                        const element = iframeDoc.body;
                        const opt = {
                            margin: 10,
                            filename: `dashboard-report-${
                                new Date().toISOString().split("T")[0]
                            }.pdf`,
                            image: { type: "jpeg", quality: 0.98 },
                            html2canvas: {
                                scale: 2,
                                useCORS: true,
                                logging: true,
                                onclone: function (clonedDoc) {
                                    const charts =
                                        clonedDoc.querySelectorAll("canvas");
                                    charts.forEach((chart) => {
                                        chart.style.width = "100%";
                                        chart.style.height = "300px";
                                    });
                                },
                            },
                            jsPDF: {
                                unit: "mm",
                                format: "a4",
                                orientation: "portrait",
                            },
                        };
                        html2pdf()
                            .set(opt)
                            .from(element)
                            .save()
                            .then(() => {
                                document.body.removeChild(iframe);
                            });
                    } else {
                        setTimeout(checkChartsLoaded, 100);
                    }
                };
                checkChartsLoaded();
            };
        }
    };
    const GlobalFilters = () => (
        <div className="flex flex-col gap-[5px] sm:flex-row items-center mb-6 pb-[15px] border-b-[1px] border-b-[#5146E64D]">
            <div className="w-full sm:w-auto text-lg font-semibold text-second-color">
                Filters:
            </div>
            <select
                value={filters.year}
                onChange={(e) => updateFilters("year", e.target.value)}
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
                onChange={(e) => updateFilters("month", e.target.value)}
                className="w-full sm:w-auto min-w-[120px] text-sm selectbg border rounded-md px-3 py-2"
                disabled={loading}
            >
                {monthOptions.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
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
            <div className="flex gap-2">
                <button
                    onClick={() => handleExport("print")}
                    className="flex items-center gap-1 px-3 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600"
                    title="Print Report"
                >
                    <FaPrint size={14} />
                    <span>Print</span>
                </button>
            </div>
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
    );
    const ChartHeader = ({ title, icon }) => (
        <div className="flex items-center justify-between mb-4">
            <h3 className="text-lg font-semibold text-gray-800 dark:text-white">
                {title}
            </h3>
            {icon}
        </div>
    );

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />
            <div className="mt-[75px]">
                <div className="min-h-screen py-[40px] px-[15px]">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        {/* <a
                            href={route("super.task.list", {
                                member_id: stats?.tasks?.filter?.member_id,
                            })}
                            className=""
                        > */}
                            <div className="cards border borderbx rounded-lg p-4 shadow-sm">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-[14px] font-[400] text-prime-color uppercase">
                                            Total Tasks
                                        </p>
                                        <h3 className="text-[30px] font-[500] text-second-color mt-1">
                                            {stats.tasks.total || (
                                                <span className="text-[15px] font-[500] text-second-color">
                                                    No Data Available
                                                </span>
                                            )}
                                        </h3>
                                    </div>
                                </div>
                            </div>
                        {/* </a> */}

                        {/* <a
                            href={
                                route("super.task.list") +
                                "?page=1&per_page=10&search=&status=0"
                            }
                            className=""
                        > */}
                            <div className="cards border borderbx rounded-lg p-4 shadow-sm">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-[14px] font-[400] text-prime-color uppercase">
                                            Closed Tasks
                                        </p>
                                        <h3 className="text-[30px] font-[500] text-second-color mt-1">
                                            {stats.tasks.completed || (
                                                <span className="text-[15px] font-semibold text-second-color">
                                                    No Data Available
                                                </span>
                                            )}
                                        </h3>
                                    </div>
                                    <div className="taskdone px-[13px] py-[10px] rounded-lg border">
                                        <FaCheckCircle
                                            className="text-green-500 dark:text-green-400"
                                            size={20}
                                        />
                                    </div>
                                </div>
                            </div>
                        {/* </a> */}

                        {/* Active Staff Card */}
                        <a  className="">
                            <div className="cards border borderbx rounded-lg p-4 shadow-sm">
                                <div className="flex items-center justify-between">
                                    <div className="w-0 flex-1">
                                        <dt className="text-[14px] font-[400] text-prime-color uppercase">
                                            Total Active Staff
                                        </dt>
                                        <dd className="flex items-baseline">
                                            <div className="text-2xl font-semibold text-gray-900 dark:text-white">
                                                {stats.staff.count || (
                                                    <span className="text-[15px] font-semibold text-second-color">
                                                        No Data Available
                                                    </span>
                                                )}
                                            </div>
                                        </dd>
                                    </div>

                                    <div className="taskrunning px-[13px] py-[10px] rounded-lg border">
                                        <svg
                                            size={20}
                                            className="h-6 w-6 text-[#5146E6]"
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
                        </a>
                    </div>

                    {/* Second Row Cards */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        {/* Departments Card */}
                        <div className="cards border borderbx rounded-lg p-4 shadow-sm">
                            <a href={route("super.departments")} className="">
                                <div className="flex items-center justify-between">
                                    <div className="w-0 flex-1">
                                        <dt className="text-[14px] font-[400] text-prime-color uppercase">
                                            Departments In Use
                                        </dt>
                                        <dd className="flex items-baseline">
                                            <div className="text-2xl font-semibold text-gray-900 dark:text-white">
                                                {stats.departments.count || (
                                                    <span className="text-[15px] font-semibold text-second-color">
                                                        No Data Available
                                                    </span>
                                                )}
                                            </div>
                                        </dd>
                                    </div>

                                    <div className="taskrunning px-[13px] py-[10px] rounded-lg border">
                                        <svg
                                            className="h-6 w-6 text-[#5146E6]"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                strokeWidth={2}
                                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                                            />
                                        </svg>
                                    </div>
                                </div>
                            </a>
                        </div>

                        {/* Overdue Tasks Card */}
                        <div className="cards border borderbx rounded-lg p-4 shadow-sm">
                            {/* <a
                                href={route("super.task.tasklist", {
                                    member_id: stats?.tasks?.filter?.member_id,
                                    status: "overdue",
                                })}
                                className=""
                            > */}
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-[14px] font-[400] text-prime-color uppercase">
                                            Overdue Task Instance
                                        </p>
                                        <h3 className="text-[30px] font-[500] text-second-color mt-1">
                                            {stats.tasks.overdue || (
                                                <span className="text-[15px] font-semibold text-second-color">
                                                    No Data Available
                                                </span>
                                            )}
                                        </h3>
                                    </div>
                                    <div className="p-3 rounded-lg bg-red-50 dark:bg-red-900/30">
                                        <FaExclamationTriangle
                                            className="text-red-500 dark:text-red-400"
                                            size={20}
                                        />
                                    </div>
                                </div>
                            {/* </a> */}
                        </div>
                    </div>

                    <div className="cards border borderbx rounded-lg p-4 shadow-sm">
                        <GlobalFilters />
                        {/* Main Charts Section */}
                        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                            {/* Task Type Distribution */}
                            <div className="cards border borderbx rounded-lg p-4 shadow-sm">
                                <ChartHeader
                                    title="Task Type Distribution"
                                    icon={
                                        <div className="taskrunning px-[13px] py-[10px] rounded-lg border">
                                            <FaChartPie className="text-[#5146E6]" />
                                        </div>
                                    }
                                />
                                <div className="h-64">
                                    <ResponsiveContainer
                                        width="100%"
                                        height="100%"
                                    >
                                        <PieChart>
                                            <Pie
                                                data={taskTypeData}
                                                cx="50%"
                                                cy="50%"
                                                innerRadius={60}
                                                outerRadius={80}
                                                paddingAngle={5}
                                                dataKey="value"
                                                label={({ name, percent }) =>
                                                    `${name}\n${(
                                                        percent * 100
                                                    ).toFixed(0)}%`
                                                }
                                                labelLine={false}
                                            >
                                                {taskTypeData.map(
                                                    (entry, index) => (
                                                        <Cell
                                                            key={`cell-${index}`}
                                                            fill={entry.color}
                                                        />
                                                    )
                                                )}
                                            </Pie>
                                            <Tooltip
                                                formatter={(value) => [
                                                    `${value} tasks`,
                                                    "Count",
                                                ]}
                                                contentStyle={
                                                    chartTheme.tooltip
                                                }
                                            />
                                            <Legend
                                                layout="horizontal"
                                                verticalAlign="bottom"
                                                align="center"
                                                wrapperStyle={
                                                    chartTheme.legend
                                                        .wrapperStyle
                                                }
                                            />
                                        </PieChart>
                                    </ResponsiveContainer>
                                </div>
                            </div>

                            {/* Task Status Distribution */}
                            <div className="cards border borderbx rounded-lg p-4 shadow-sm">
                                <ChartHeader
                                    title="Task Status"
                                    icon={
                                        <div className="taskpending px-[13px] py-[10px] rounded-lg border">
                                            <FaChartPie className="text-[#FFBA26]" />
                                        </div>
                                    }
                                />
                                <div className="h-64">
                                    <ResponsiveContainer
                                        width="100%"
                                        height="100%"
                                    >
                                        <PieChart>
                                            <Pie
                                                data={taskStatusData}
                                                cx="50%"
                                                cy="50%"
                                                innerRadius={60}
                                                outerRadius={80}
                                                paddingAngle={5}
                                                dataKey="value"
                                                label={({ name, percent }) =>
                                                    `${name}\n${(
                                                        percent * 100
                                                    ).toFixed(0)}%`
                                                }
                                                labelLine={false}
                                            >
                                                {taskStatusData.map(
                                                    (entry, index) => (
                                                        <Cell
                                                            key={`cell-${index}`}
                                                            fill={entry.color}
                                                        />
                                                    )
                                                )}
                                            </Pie>
                                            <Tooltip
                                                formatter={(value) => [
                                                    `${value} tasks`,
                                                    "Count",
                                                ]}
                                                contentStyle={
                                                    chartTheme.tooltip
                                                }
                                            />
                                            <Legend
                                                layout="horizontal"
                                                verticalAlign="bottom"
                                                align="center"
                                                wrapperStyle={
                                                    chartTheme.legend
                                                        .wrapperStyle
                                                }
                                            />
                                        </PieChart>
                                    </ResponsiveContainer>
                                </div>
                            </div>

                            {/* Task Completion Trend */}
                            <div className="cards border borderbx rounded-lg p-4 shadow-sm">
                                <ChartHeader
                                    title="Completion Trend"
                                    icon={
                                        <div className="taskdone px-[13px] py-[10px] rounded-lg border">
                                            <FaChartBar className="text-green-500 dark:text-green-400" />
                                        </div>
                                    }
                                />
                                <div className="h-64">
                                    <ResponsiveContainer
                                        width="100%"
                                        height="100%"
                                    >
                                        <BarChart
                                            data={stats.tasks.trend}
                                            margin={{
                                                top: 5,
                                                right: 5,
                                                left: 5,
                                                bottom: 5,
                                            }}
                                        >
                                            <CartesianGrid
                                                strokeDasharray={
                                                    chartTheme.grid
                                                        .strokeDasharray
                                                }
                                                stroke={chartTheme.grid.stroke}
                                                vertical={false}
                                            />
                                            <XAxis
                                                dataKey="month"
                                                tick={{
                                                    fill: chartTheme.colors
                                                        .gray,
                                                }}
                                                tickLine={false}
                                            />
                                            <YAxis
                                                tick={{
                                                    fill: chartTheme.colors
                                                        .gray,
                                                }}
                                                tickLine={false}
                                            />
                                            <Tooltip
                                                contentStyle={
                                                    chartTheme.tooltip
                                                }
                                            />
                                            <Legend
                                                wrapperStyle={
                                                    chartTheme.legend
                                                        .wrapperStyle
                                                }
                                            />
                                            <Bar
                                                dataKey="total"
                                                name="Created"
                                                fill={CHART_COLORS.primary}
                                                radius={[4, 4, 0, 0]}
                                            />
                                            <Bar
                                                dataKey="completed"
                                                name="Completed"
                                                fill={CHART_COLORS.success}
                                                radius={[4, 4, 0, 0]}
                                            />
                                        </BarChart>
                                    </ResponsiveContainer>
                                </div>
                            </div>
                        </div>
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div className="cards border borderbx rounded-lg p-4 shadow-sm">
                                <ChartHeader
                                    title="Completion Rate"
                                    icon={
                                        <div className="taskdone px-[13px] py-[10px] rounded-lg border">
                                            <FaChartLine className="text-green-500 dark:text-green-400" />
                                        </div>
                                    }
                                />
                                <div className="h-64">
                                    <ResponsiveContainer
                                        width="100%"
                                        height="100%"
                                    >
                                        <LineChart
                                            data={stats.tasks.trend || []}
                                            margin={{
                                                top: 15,
                                                right: 15,
                                                left: 15,
                                                bottom: 15,
                                            }}
                                        >
                                            <CartesianGrid
                                                strokeDasharray="3 3"
                                                stroke="#e5e7eb"
                                                vertical={false}
                                            />
                                            <XAxis
                                                dataKey="month"
                                                tick={{
                                                    fill: "#6b7280",
                                                    fontSize: 11,
                                                }}
                                                tickLine={false}
                                                axisLine={{ stroke: "#e5e7eb" }}
                                            />
                                            <YAxis
                                                tick={{
                                                    fill: "#6b7280",
                                                    fontSize: 11,
                                                }}
                                                tickLine={false}
                                                axisLine={{ stroke: "#e5e7eb" }}
                                                domain={[
                                                    "dataMin - 5",
                                                    "dataMax + 5",
                                                ]}
                                                tickCount={6}
                                            />
                                            <Tooltip
                                                contentStyle={{
                                                    backgroundColor: "white",
                                                    border: "none",
                                                    borderRadius: "6px",
                                                    boxShadow:
                                                        "0 4px 6px -1px rgba(0, 0, 0, 0.1)",
                                                    fontSize: "12px",
                                                }}
                                                formatter={(value) => [
                                                    `${value} tasks`,
                                                    "Completed",
                                                ]}
                                                labelFormatter={(label) =>
                                                    `Month: ${label}`
                                                }
                                            />
                                            <Legend
                                                wrapperStyle={{
                                                    paddingTop: "10px",
                                                    fontSize: "12px",
                                                }}
                                            />
                                            <Line
                                                type="monotone"
                                                dataKey="completed"
                                                name="Completed Tasks"
                                                stroke="#10b981"
                                                strokeWidth={3}
                                                dot={{
                                                    r: 5,
                                                    fill: "#10b981",
                                                    stroke: "#059669",
                                                    strokeWidth: 2,
                                                }}
                                                activeDot={{
                                                    r: 7,
                                                    fill: "#059669",
                                                    stroke: "#10b981",
                                                    strokeWidth: 2,
                                                }}
                                                connectNulls={true}
                                            />
                                        </LineChart>
                                    </ResponsiveContainer>
                                </div>
                            </div>

                            {/* Task Creation vs Completion */}
                            <div className="cards border borderbx rounded-lg p-4 shadow-sm">
                                <ChartHeader
                                    title="Creation vs Completion"
                                    icon={
                                        <div className="taskrunning px-[13px] py-[10px] rounded-lg border">
                                            <FaChartLine className="text-[#5146E6]" />
                                        </div>
                                    }
                                />
                                <div className="h-64">
                                    <ResponsiveContainer
                                        width="100%"
                                        height="100%"
                                    >
                                        <LineChart
                                            data={stats.tasks.trend}
                                            margin={{
                                                top: 5,
                                                right: 5,
                                                left: 5,
                                                bottom: 5,
                                            }}
                                        >
                                            <CartesianGrid
                                                strokeDasharray={
                                                    chartTheme.grid
                                                        .strokeDasharray
                                                }
                                                stroke={chartTheme.grid.stroke}
                                                vertical={false}
                                            />
                                            <XAxis
                                                dataKey="month"
                                                tick={{
                                                    fill: chartTheme.colors
                                                        .gray,
                                                }}
                                                tickLine={false}
                                            />
                                            <YAxis
                                                tick={{
                                                    fill: chartTheme.colors
                                                        .gray,
                                                }}
                                                tickLine={false}
                                            />
                                            <Tooltip
                                                contentStyle={
                                                    chartTheme.tooltip
                                                }
                                            />
                                            <Legend
                                                wrapperStyle={
                                                    chartTheme.legend
                                                        .wrapperStyle
                                                }
                                            />
                                            <Line
                                                type="monotone"
                                                dataKey="total"
                                                name="Created"
                                                stroke={CHART_COLORS.primary}
                                                strokeWidth={2}
                                                dot={{ r: 4 }}
                                                activeDot={{ r: 6 }}
                                            />
                                            <Line
                                                type="monotone"
                                                dataKey="completed"
                                                name="Completed"
                                                stroke={CHART_COLORS.success}
                                                strokeWidth={2}
                                                dot={{ r: 4 }}
                                                activeDot={{ r: 6 }}
                                            />
                                        </LineChart>
                                    </ResponsiveContainer>
                                </div>
                            </div>
                        </div>
                    </div>
                    <Calendar />
                    <ActivityLogSection activityLogs={activityLogs} />
                    <PasswordLogSection
                        passwordLogs={passwordLogs}
                        auth={auth}
                    />
                    <ImageActionLogSection
                        imageActionLogs={imageActionLogs}
                        auth={auth}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
