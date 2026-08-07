import { Link, usePage } from '@inertiajs/react';
import { useTheme } from '@/Contexts/ThemeContext';
import {
    Sun,
    Moon,
    Menu,
    X,
    Building2,
    LayoutDashboard,
    Home,
} from 'lucide-react';
import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';

export default function HomepageLayout({ children }) {
    const { toggleTheme, isDark } = useTheme();
    const [isScrolled, setIsScrolled] = useState(false);
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const { auth } = usePage().props;
    const isAuthenticated = !!auth?.user;

    useEffect(() => {
        const handleScroll = () => {
            setIsScrolled(window.scrollY > 20);
        };
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    const navLinks = [
        { name: 'Home', href: route('homepage'), icon: Home },
        // { name: 'Browse Jobs', href: route('jobs.index'), icon: Briefcase },
        // { name: 'Companies', href: route('companies.index'), icon: Building2 },
        // { name: 'About', href: route('about'), icon: Sparkles },
        // { name: 'Contact', href: route('contact.show'), icon: Mail },
    ];

    if (isAuthenticated) {
        const dashboardRouteByGuard = {
            superadmin: 'super.dashboard',
            admin: 'admin.dashboard',
            member: 'member.dashboard',
        };
        const dashboardRouteName = dashboardRouteByGuard[auth?.guard] ?? 'home';
        navLinks.push({ name: 'Dashboard', href: route(dashboardRouteName), icon: LayoutDashboard });
    }

    return (
        <div className={`min-h-screen transition-colors duration-300 ${isDark ? 'bg-[#0f172a]' : 'bg-gray-50'}`}>
            {/* Navbar */}
            <nav
                className={`fixed top-0 left-0 right-0 z-50 transition-colors duration-300 ${isScrolled
                    ? isDark
                        ? 'bg-gray-900/95 border-b border-gray-800'
                        : 'bg-white border-b border-gray-200 shadow-sm'
                    : isDark ? 'bg-[#0f172a]' : 'bg-gray-50'
                    }`}
            >
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex h-28 items-center justify-between">

                        {/* Logo */}
                        <div className="flex items-center gap-3">
                            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-600 text-2xl font-bold text-white shadow-lg shadow-blue-600/30">
                                ◈
                            </div>

                            <div>
                                <div className="text-2xl font-bold tracking-wide text-black">
                                    CADMAX
                                </div>

                                <div className="text-[11px] font-semibold tracking-[0.35em] text-blue-300">
                                    CONSULTANCY
                                </div>
                            </div>
                        </div>

                        {/* Navigation */}
                        {/* <nav className="hidden items-center gap-10 md:flex">
                            <a
                                href="/"
                                className="relative py-2 text-sm font-medium text-white"
                            >
                                Home
                                <span className="absolute bottom-0 left-0 h-0.5 w-full bg-blue-500" />
                            </a>

                            <a
                                href="/services"
                                className="text-sm font-medium text-slate-300 transition hover:text-white"
                            >
                                Services
                            </a>

                            <a
                                href="/about"
                                className="text-sm font-medium text-slate-300 transition hover:text-white"
                            >
                                About Us
                            </a>

                            <a
                                href="/contact"
                                className="text-sm font-medium text-slate-300 transition hover:text-white"
                            >
                                Contact
                            </a>
                        </nav> */}

                        {/* Right buttons */}
                        <div className="flex items-center gap-4">
                            <button
                                type="button"
                                className="hidden h-10 w-10 items-center justify-center rounded-full bg-white/10 text-xl text-white backdrop-blur-sm transition hover:bg-white/20 sm:flex"
                            >
                                ☾
                            </button>

                            <a
                                href="/login"
                                className="hidden text-sm font-medium text-black sm:block"
                            >
                                Sign In
                            </a>

                            <a
                                href="/register"
                                className="rounded-lg bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-600/25 transition hover:bg-blue-700"
                            >
                                Sign Up
                            </a>
                        </div>
                    </div>
                </div>

                {/* Mobile Menu */}
                <AnimatePresence>
                    {mobileMenuOpen && (
                        <motion.div
                            initial={{ opacity: 0, height: 0 }}
                            animate={{ opacity: 1, height: 'auto' }}
                            exit={{ opacity: 0, height: 0 }}
                            transition={{ duration: 0.3 }}
                            className={`lg:hidden border-t ${isDark
                                ? 'bg-slate-900/95 border-slate-800 backdrop-blur-xl'
                                : 'bg-white/95 border-slate-200 backdrop-blur-xl'
                                }`}
                        >
                            <div className="px-4 py-4 space-y-2">
                                {navLinks.map((link) => (
                                    <Link
                                        key={link.name}
                                        href={link.href}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className={`flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-300 ${isDark
                                            ? 'text-slate-300 hover:text-white hover:bg-white/10'
                                            : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
                                            }`}
                                    >
                                        <link.icon className="w-5 h-5" />
                                        {link.name}
                                    </Link>
                                ))}
                                <div className="pt-4 border-t border-slate-200 dark:border-slate-800 space-y-2">
                                    {isAuthenticated ? (
                                        <Link
                                            href={route(({
                                                superadmin: 'super.dashboard',
                                                admin: 'admin.dashboard',
                                                member: 'member.dashboard',
                                            })[auth?.guard] ?? 'home')}
                                            className={`block w-full text-center px-4 py-3 rounded-xl text-sm font-semibold ${isDark
                                                ? 'bg-gradient-to-r from-brand-500 to-accent-purple text-white'
                                                : 'bg-gradient-to-r from-brand-600 to-brand-700 text-white'
                                                }`}
                                        >
                                            Dashboard
                                        </Link>
                                    ) : (
                                        <>
                                            <Link
                                                href={route('login')}
                                                className={`block w-full text-center px-4 py-3 rounded-xl text-sm font-medium ${isDark
                                                    ? 'text-slate-300 hover:text-white'
                                                    : 'text-slate-600 hover:text-slate-900'
                                                    }`}
                                            >
                                                Sign In
                                            </Link>
                                            <Link
                                                href={route('register')}
                                                className={`block w-full text-center px-4 py-3 rounded-xl text-sm font-semibold ${isDark
                                                    ? 'bg-gradient-to-r from-brand-500 to-accent-purple text-white'
                                                    : 'bg-gradient-to-r from-brand-600 to-brand-700 text-white'
                                                    }`}
                                            >
                                                Get Started
                                            </Link>
                                        </>
                                    )}
                                </div>
                            </div>
                        </motion.div>
                    )}
                </AnimatePresence>
            </nav>

            {/* Main Content */}
            <main className="pt-16 lg:pt-20">
                {children}
            </main>
        </div>
    );
}
