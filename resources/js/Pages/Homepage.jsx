import { useEffect, useMemo, useState } from "react";
import { Head } from "@inertiajs/react";
import { ThemeProvider } from "@/Contexts/ThemeContext";
import HomepageLayout from "@/Layouts/HomepageLayout";

export default function Homepage() {
    const countdownTarget = useMemo(
        () => Date.now() + 10 * 24 * 60 * 60 * 1000,
        []
    );

    const [timeLeft, setTimeLeft] = useState(() =>
        getTimeLeft(countdownTarget)
    );

    useEffect(() => {
        const timer = setInterval(() => {
            setTimeLeft(getTimeLeft(countdownTarget));
        }, 1000);

        return () => clearInterval(timer);
    }, [countdownTarget]);

    const services = [
        {
            icon: "⬡",
            title: "CAD Design",
            subtitle: "& Drafting",
        },
        {
            icon: "◇",
            title: "3D Modeling",
            subtitle: "& Visualization",
        },
        {
            icon: "⚙",
            title: "Product Design",
            subtitle: "& Development",
        },
        {
            icon: "▥",
            title: "BIM Services",
            subtitle: "& Solutions",
        },
        {
            icon: "⬢",
            title: "Engineering",
            subtitle: "Consultancy",
        },
        {
            icon: "☑",
            title: "Project Planning",
            subtitle: "& Management",
        },
    ];

    const stats = [
        {
            value: "50+",
            title: "Projects Completed",
            icon: "●",
        },
        {
            value: "100%",
            title: "Client Satisfaction",
            icon: "☺",
        },
        {
            value: "24/7",
            title: "Support",
            icon: "◉",
        },
    ];

    return (
        <ThemeProvider>
            <Head title="Cadmax Consultancy | Engineering & CAD Solutions" />

            <HomepageLayout>
                <main className="min-h-screen bg-slate-950">

                    {/* ================= HERO ================= */}
                    <section
                        className="relative min-h-[720px] overflow-hidden bg-slate-950"
                        style={{
                            backgroundImage:
                                "linear-gradient(90deg, rgba(2,15,35,0.98) 0%, rgba(3,24,51,0.92) 45%, rgba(3,24,51,0.75) 100%), url('/images/cadmax-bg.jpg')",
                            backgroundSize: "cover",
                            backgroundPosition: "center",
                        }}
                    >
                        {/* Background glow */}
                        <div className="absolute inset-0 bg-[radial-gradient(circle_at_50%_50%,rgba(37,99,235,0.25),transparent_55%)]" />

                        {/* Engineering grid */}
                        <div className="absolute inset-0 opacity-10 bg-[linear-gradient(rgba(255,255,255,.2)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.2)_1px,transparent_1px)] bg-[size:60px_60px]" />

                        <div className="relative z-10 mx-auto max-w-7xl px-6">




                            {/* ================= HERO CONTENT ================= */}
                            <div className="mx-auto max-w-4xl pt-10 text-center md:pt-14">

                                <div className="inline-flex items-center rounded-full border border-blue-400/30 bg-blue-600/80 px-5 py-2 text-xs font-bold uppercase tracking-[0.3em] text-white shadow-lg shadow-blue-600/20">
                                    Coming Soon
                                </div>

                                <h1 className="mt-7 text-4xl font-extrabold leading-tight text-white sm:text-5xl md:text-6xl">
                                    Redefining Engineering.
                                    <br />

                                    <span className="text-blue-500">
                                        Designing
                                    </span>{" "}
                                    The Future.
                                </h1>

                                <p className="mx-auto mt-6 max-w-3xl text-base leading-7 text-slate-300 sm:text-lg">
                                    Cadmax Consultancy delivers innovative CAD
                                    design, engineering, and consultancy
                                    solutions that drive{" "}
                                    <span className="font-semibold text-blue-400">
                                        ideas into reality.
                                    </span>
                                </p>
                            </div>

                            {/* ================= COUNTDOWN ================= */}
                            <div className="relative z-20 mx-auto mt-10 max-w-4xl">

                                <div className="rounded-[28px] bg-white p-7 text-center shadow-2xl shadow-black/40 sm:p-9">

                                    <h2 className="text-2xl font-bold text-slate-900 sm:text-3xl">
                                        Our new homepage is on the way
                                    </h2>

                                    <p className="mt-2 text-sm text-slate-600 sm:text-base">
                                        Launch countdown for the next{" "}
                                        <span className="font-bold text-blue-600">
                                            10 days.
                                        </span>
                                    </p>

                                    <div className="mt-7 grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">

                                        {[
                                            {
                                                label: "Days",
                                                value: timeLeft.days,
                                            },
                                            {
                                                label: "Hours",
                                                value: timeLeft.hours,
                                            },
                                            {
                                                label: "Minutes",
                                                value: timeLeft.minutes,
                                            },
                                            {
                                                label: "Seconds",
                                                value: timeLeft.seconds,
                                            },
                                        ].map((item) => (
                                            <div
                                                key={item.label}
                                                className="rounded-2xl bg-blue-50 px-3 py-5 sm:px-4 sm:py-6"
                                            >
                                                <div className="text-3xl font-bold text-blue-700 sm:text-4xl">
                                                    {formatTime(item.value)}
                                                </div>

                                                <div className="mt-2 text-[10px] font-bold uppercase tracking-[0.25em] text-slate-600 sm:text-xs">
                                                    {item.label}
                                                </div>
                                            </div>
                                        ))}

                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* ================= SERVICES ================= */}
                    <section className="relative z-20 -mt-10 px-4">

                        <div className="mx-auto grid max-w-7xl grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">

                            {services.map((service) => (
                                <div
                                    key={service.title}
                                    className="group rounded-xl border border-slate-200 bg-white px-4 py-6 text-center shadow-lg transition duration-300 hover:-translate-y-2 hover:shadow-xl dark:border-slate-700 dark:bg-slate-900"
                                >
                                    <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-2xl font-bold text-blue-600 transition group-hover:bg-blue-600 group-hover:text-white dark:bg-blue-950">
                                        {service.icon}
                                    </div>

                                    <h3 className="mt-4 text-sm font-bold text-slate-900 dark:text-white">
                                        {service.title}
                                    </h3>

                                    <p className="text-xs text-slate-500 dark:text-slate-400">
                                        {service.subtitle}
                                    </p>
                                </div>
                            ))}

                        </div>
                    </section>

                    {/* ================= STATS ================= */}
                    <section className="bg-white px-6 py-12 dark:bg-slate-950">

                        <div className="mx-auto grid max-w-5xl grid-cols-1 divide-y md:grid-cols-3 md:divide-x md:divide-y-0">

                            {stats.map((stat) => (
                                <div
                                    key={stat.title}
                                    className="flex items-center justify-center gap-4 px-6 py-6"
                                >
                                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-600 text-xl text-white">
                                        {stat.icon}
                                    </div>

                                    <div>
                                        <div className="text-3xl font-bold text-slate-900 dark:text-white">
                                            {stat.value}
                                        </div>

                                        <p className="text-sm text-slate-500 dark:text-slate-400">
                                            {stat.title}
                                        </p>
                                    </div>
                                </div>
                            ))}

                        </div>
                    </section>

                    {/* ================= CONTACT FOOTER ================= */}
                    <footer className="bg-[#02152d] px-6 py-8 text-white">

                        <div className="mx-auto grid max-w-7xl gap-8 md:grid-cols-4 md:items-center">

                            {/* Address */}
                            <div className="flex items-start gap-4">
                                <div className="text-2xl text-blue-400">
                                    ⌖
                                </div>

                                <div>
                                    <p className="text-sm font-semibold">
                                        Jaipur, Rajasthan, India
                                    </p>

                                    <p className="mt-1 text-xs text-slate-400">
                                        302039
                                    </p>
                                </div>
                            </div>

                            {/* Phone */}
                            <div className="flex items-start gap-4">
                                <div className="text-2xl text-blue-400">
                                    ☎
                                </div>

                                <div>
                                    <p className="text-sm font-semibold">
                                        +91 98870 62063
                                    </p>

                                    <p className="mt-1 text-xs text-slate-400">
                                        Mon - Sat: 9:00 AM - 6:00 PM
                                    </p>
                                </div>
                            </div>

                            {/* Email */}
                            <div className="flex items-start gap-4">
                                <div className="text-2xl text-blue-400">
                                    ✉
                                </div>

                                <div>
                                    <p className="text-sm font-semibold">
                                        info@cadmaxconsultancy.com
                                    </p>

                                    <p className="mt-1 text-xs text-slate-400">
                                        We reply within 24 hours
                                    </p>
                                </div>
                            </div>

                            {/* Social */}
                            <div className="flex items-center gap-3 md:justify-end">
                                <span className="mr-2 text-sm font-semibold">
                                    Follow Us
                                </span>

                                <a
                                    href="#"
                                    className="flex h-9 w-9 items-center justify-center rounded-lg bg-white/10 text-sm transition hover:bg-blue-600"
                                >
                                    in
                                </a>

                                <a
                                    href="#"
                                    className="flex h-9 w-9 items-center justify-center rounded-lg bg-white/10 text-sm transition hover:bg-blue-600"
                                >
                                    f
                                </a>

                                <a
                                    href="#"
                                    className="flex h-9 w-9 items-center justify-center rounded-lg bg-white/10 text-sm transition hover:bg-blue-600"
                                >
                                    ◎
                                </a>

                                <a
                                    href="#"
                                    className="flex h-9 w-9 items-center justify-center rounded-lg bg-white/10 text-sm transition hover:bg-blue-600"
                                >
                                    ▶
                                </a>
                            </div>
                        </div>

                        <div className="mx-auto mt-7 max-w-7xl border-t border-white/10 pt-5 text-center text-xs text-slate-400">
                            © {new Date().getFullYear()} Cadmax Consultancy.
                            All rights reserved.
                        </div>

                    </footer>

                </main>
            </HomepageLayout>
        </ThemeProvider>
    );
}

function getTimeLeft(targetTime) {
    const difference = Math.max(targetTime - Date.now(), 0);

    return {
        days: Math.floor(
            difference / (1000 * 60 * 60 * 24)
        ),
        hours: Math.floor(
            (difference / (1000 * 60 * 60)) % 24
        ),
        minutes: Math.floor(
            (difference / (1000 * 60)) % 60
        ),
        seconds: Math.floor(
            (difference / 1000) % 60
        ),
    };
}

function formatTime(value) {
    return String(value).padStart(2, "0");
}