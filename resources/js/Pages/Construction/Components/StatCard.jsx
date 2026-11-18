import { useEffect, useRef, useState } from "react";
import gsap from "gsap";

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
    compact = false,
}) {
    const preset = colorPresets[color] || colorPresets.indigo;
    const IconComp = icon;
    const Wrapper = onClick ? "button" : "div";
    const cardRef = useRef(null);

    const isPureInteger =
        typeof value === "number" && Number.isFinite(value) && Number.isInteger(value);
    const [displayValue, setDisplayValue] = useState(value);

    useEffect(() => {
        if (!cardRef.current) return;

        const ctx = gsap.context(() => {
            if (isPureInteger && value > 0) {
                const counter = { val: 0 };
                gsap.to(counter, {
                    val: value,
                    duration: 0.55,
                    ease: "power2.out",
                    onUpdate: () => setDisplayValue(Math.round(counter.val)),
                });
            } else {
                setDisplayValue(value);
            }
        }, cardRef);

        return () => ctx.revert();
    }, [value, isPureInteger]);

    const handleMouseEnter = () => {
        if (!cardRef.current) return;
        gsap.to(cardRef.current, {
            y: -2,
            duration: 0.18,
            ease: "power1.out",
            overwrite: "auto",
        });
    };

    const handleMouseLeave = () => {
        if (!cardRef.current) return;
        gsap.to(cardRef.current, {
            y: 0,
            duration: 0.18,
            ease: "power1.out",
            overwrite: "auto",
        });
    };

    return (
        <Wrapper
            ref={cardRef}
            onClick={onClick}
            onMouseEnter={handleMouseEnter}
            onMouseLeave={handleMouseLeave}
            className={`stat-card-item group relative w-full text-left border border-slate-200/90 bg-white shadow-2xs transition-colors duration-200 hover:border-indigo-300/80 hover:shadow-md dark:border-slate-800 dark:bg-slate-950 dark:hover:border-indigo-500/40 ${
                compact ? "rounded-xl px-3 py-2.5" : "rounded-2xl p-4"
            } ${onClick ? "cursor-pointer" : ""}`}
        >
            <div className="flex items-start justify-between gap-2.5">
                <div className="min-w-0 flex-1">
                    <p
                        className={`font-medium text-slate-500 dark:text-slate-400 truncate ${
                            compact ? "text-[11px] tracking-normal" : "text-xs tracking-wide"
                        }`}
                    >
                        {label}
                    </p>
                    <p
                        className={`font-bold text-slate-900 dark:text-white tracking-tight truncate ${
                            compact ? "mt-0.5 text-lg leading-tight" : "mt-1.5 text-2xl"
                        }`}
                    >
                        {displayValue}
                    </p>
                    {hint ? (
                        <p className="mt-1.5 text-[11px] text-slate-500 dark:text-slate-400 leading-snug">
                            {hint}
                        </p>
                    ) : null}
                    {trend !== undefined && trend !== null ? (
                        <div className="mt-1.5 flex items-center gap-1.5 text-[11px] font-medium">
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
                        className={`shrink-0 flex items-center justify-center rounded-xl ring-1 ${preset.bg} ${preset.ring} ${
                            compact ? "w-8 h-8" : "w-10 h-10"
                        }`}
                    >
                        <div
                            className={`rounded-lg flex items-center justify-center ${preset.iconBg} ${preset.iconText} ${
                                compact ? "w-6 h-6" : "w-8 h-8"
                            }`}
                        >
                            <IconComp size={compact ? 14 : 16} strokeWidth={2} />
                        </div>
                    </div>
                ) : null}
            </div>

            {IconComp && iconPosition === "top" ? (
                <div
                    className={`absolute -top-3 left-5 w-10 h-10 rounded-xl shadow-lg ring-4 ring-white dark:ring-slate-950 ${preset.iconBg} ${preset.iconText} flex items-center justify-center`}
                >
                    <IconComp size={18} strokeWidth={2} />
                </div>
            ) : null}
        </Wrapper>
    );
}
