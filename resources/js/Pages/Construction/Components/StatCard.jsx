const colorPresets = {
    indigo: {
        bg: "bg-indigo-50 dark:bg-indigo-950/40",
        iconBg: "bg-indigo-600",
        iconText: "text-white",
        ring: "ring-indigo-100 dark:ring-indigo-900",
        accent: "text-indigo-600 dark:text-indigo-400",
    },
    emerald: {
        bg: "bg-emerald-50 dark:bg-emerald-950/40",
        iconBg: "bg-emerald-600",
        iconText: "text-white",
        ring: "ring-emerald-100 dark:ring-emerald-900",
        accent: "text-emerald-600 dark:text-emerald-400",
    },
    amber: {
        bg: "bg-amber-50 dark:bg-amber-950/40",
        iconBg: "bg-amber-500",
        iconText: "text-white",
        ring: "ring-amber-100 dark:ring-amber-900",
        accent: "text-amber-600 dark:text-amber-400",
    },
    rose: {
        bg: "bg-rose-50 dark:bg-rose-950/40",
        iconBg: "bg-rose-600",
        iconText: "text-white",
        ring: "ring-rose-100 dark:ring-rose-900",
        accent: "text-rose-600 dark:text-rose-400",
    },
    sky: {
        bg: "bg-sky-50 dark:bg-sky-950/40",
        iconBg: "bg-sky-600",
        iconText: "text-white",
        ring: "ring-sky-100 dark:ring-sky-900",
        accent: "text-sky-600 dark:text-sky-400",
    },
    violet: {
        bg: "bg-violet-50 dark:bg-violet-950/40",
        iconBg: "bg-violet-600",
        iconText: "text-white",
        ring: "ring-violet-100 dark:ring-violet-900",
        accent: "text-violet-600 dark:text-violet-400",
    },
    slate: {
        bg: "bg-slate-50 dark:bg-slate-900/60",
        iconBg: "bg-slate-700",
        iconText: "text-white",
        ring: "ring-slate-200 dark:ring-slate-800",
        accent: "text-slate-600 dark:text-slate-400",
    },
    fuchsia: {
        bg: "bg-fuchsia-50 dark:bg-fuchsia-950/40",
        iconBg: "bg-fuchsia-600",
        iconText: "text-white",
        ring: "ring-fuchsia-100 dark:ring-fuchsia-900",
        accent: "text-fuchsia-600 dark:text-fuchsia-400",
    },
};

export default function StatCard({
    label,
    value,
    hint,
    icon,
    color = "indigo",
    iconPosition = "right",
    trend,
    trendLabel,
    onClick,
}) {
    const preset = colorPresets[color] || colorPresets.indigo;
    const IconComp = icon;
    const Wrapper = onClick ? "button" : "div";

    return (
        <Wrapper
            onClick={onClick}
            className={`group relative w-full text-left rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 dark:border-slate-800 dark:bg-slate-950 ${
                onClick ? "cursor-pointer" : ""
            }`}
        >
            <div className="flex items-start justify-between gap-4">
                <div className="min-w-0 flex-1">
                    <p className="text-[13px] font-medium text-slate-500 dark:text-slate-400 tracking-wide">
                        {label}
                    </p>
                    <p className="mt-2.5 text-2xl font-bold text-slate-900 dark:text-white tracking-tight truncate">
                        {value}
                    </p>
                    {hint ? (
                        <p className="mt-2 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            {hint}
                        </p>
                    ) : null}
                    {trend !== undefined && trend !== null ? (
                        <div className="mt-2 flex items-center gap-1.5 text-[11px] font-medium">
                            <span
                                className={`inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 ${
                                    Number(trend) >= 0
                                        ? "bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                                        : "bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300"
                                }`}
                            >
                                {Number(trend) >= 0 ? "▲" : "▼"}
                                {Math.abs(Number(trend))}%
                            </span>
                            {trendLabel ? (
                                <span className="text-slate-500 dark:text-slate-400">
                                    {trendLabel}
                                </span>
                            ) : null}
                        </div>
                    ) : null}
                </div>

                {IconComp && iconPosition === "right" ? (
                    <div
                        className={`shrink-0 flex items-center justify-center w-11 h-11 rounded-xl ring-1 ${preset.bg} ${preset.ring}`}
                    >
                        <div
                            className={`w-9 h-9 rounded-lg flex items-center justify-center ${preset.iconBg} ${preset.iconText}`}
                        >
                            <IconComp size={18} strokeWidth={2} />
                        </div>
                    </div>
                ) : null}
            </div>

            {IconComp && iconPosition === "top" ? (
                <div
                    className={`absolute -top-3 left-5 w-12 h-12 rounded-xl shadow-lg ring-4 ring-white dark:ring-slate-950 ${preset.iconBg} ${preset.iconText} flex items-center justify-center`}
                >
                    <IconComp size={22} strokeWidth={2} />
                </div>
            ) : null}
        </Wrapper>
    );
}
