export const INNER_PROJECT_LOGO_SRC = "/storage/Logo_1.jpeg";
export const INNER_PROJECT_LOGO_FALLBACK_SRC = "/images/logo.png";

/**
 * Resolve the inner portal logo URL, falling back to /storage/Logo_1.jpeg.
 */
export function resolveInnerLogoSrc(customPath) {
    if (typeof customPath === "string" && customPath.trim() !== "") {
        const trimmed = customPath.trim();
        if (
            trimmed.startsWith("http://") ||
            trimmed.startsWith("https://") ||
            trimmed.startsWith("/")
        ) {
            return trimmed;
        }
        return `/storage/${trimmed}`;
    }

    return INNER_PROJECT_LOGO_SRC;
}

/**
 * Render the inner portal brand logo in a crisp light container suitable for both themes.
 */
export default function ApplicationLogo({
    props: siteProps = null,
    className = "",
    alt = "Track My Survey Logo",
    ...rest
}) {
    const lightLogo = resolveInnerLogoSrc(siteProps?.light_logo_path);
    const darkLogo = resolveInnerLogoSrc(siteProps?.dark_logo_path);

    // Strip legacy SVG sizing/display utilities so wide horizontal logos are never clipped or lose flex alignment.
    const sanitizedClassName = String(className || "")
        .replace(/\b(?:sm:|md:|lg:|xl:)?(?:block|w-(?:\d+|full)|h-(?:\d+|full)|fill-current)\b/g, "")
        .replace(/\s+/g, " ")
        .trim();

    const handleLogoError = (e) => {
        e.currentTarget.onerror = null;
        e.currentTarget.src = INNER_PROJECT_LOGO_FALLBACK_SRC;
    };

    return (
        <span
            className={`inline-flex w-auto min-w-fit items-center justify-center rounded-lg bg-white px-2 py-0.5 shadow-sm ring-1 ring-slate-200/80 dark:ring-white/10 ${sanitizedClassName}`.trim()}
            {...rest}
        >
            <img
                className="block dark:hidden h-8 sm:h-9 w-auto max-w-[145px] object-contain"
                src={lightLogo}
                alt={alt}
                onError={handleLogoError}
            />
            <img
                className="hidden dark:block h-8 sm:h-9 w-auto max-w-[145px] object-contain"
                src={darkLogo}
                alt={alt}
                onError={handleLogoError}
            />
        </span>
    );
}
