import {
    INNER_PROJECT_LOGO_FALLBACK_SRC,
    INNER_PROJECT_LOGO_SRC,
    resolveInnerLogoSrc,
} from "@/Components/ApplicationLogo";

/**
 * Shared brand header for inner portal sidebars displaying the horizontal project logo banner.
 */
export default function SidebarBrandLogo({
    subtitle = null,
    companyName = null,
    onClose = null,
    closeButtonClassName = "",
    logoPath = null,
    alt = "Track My Survey Logo",
}) {
    const logoSrc = logoPath
        ? resolveInnerLogoSrc(logoPath)
        : INNER_PROJECT_LOGO_SRC;

    return (
        <div className="relative shrink-0 border-b border-gray-200/80 px-5 py-4 dark:border-[#5146e64a]">
            <div className="flex items-center justify-between gap-2.5">
                <div className="flex min-w-0 flex-1 items-center justify-center rounded-xl border border-slate-200/80 bg-white px-2.5 py-1 shadow-sm dark:border-white/10">
                    <img
                        src={logoSrc}
                        alt={alt}
                        className="h-16 w-auto max-w-[205px] object-contain"
                        onError={(e) => {
                            e.currentTarget.onerror = null;
                            e.currentTarget.src = INNER_PROJECT_LOGO_FALLBACK_SRC;
                        }}
                    />
                </div>

                {onClose && (
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Close sidebar"
                        className={
                            closeButtonClassName ||
                            "flex h-[38px] w-[42px] shrink-0 items-center justify-center rounded-[8px] border border-[#0000001A] bg-white text-[#000] transition focus:outline-none dark:border-[#61CC681A] dark:bg-[#61CC681A] dark:text-[#fff] xl:hidden"
                        }
                    >
                        <svg
                            className="h-5 w-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                )}
            </div>

            {(subtitle || companyName) && (
                <div className="mt-2.5 min-w-0 px-0.5 text-center">
                    {subtitle && (
                        <p className="truncate text-[11px] font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-300">
                            {subtitle}
                        </p>
                    )}
                    {companyName && (
                        <p
                            className="mt-0.5 truncate text-[10px] font-medium text-slate-500 dark:text-slate-400"
                            title={companyName}
                        >
                            {companyName}
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}
