import { Head } from '@inertiajs/react';
import { ThemeProvider } from '@/Contexts/ThemeContext';
import HomepageLayout from '@/Layouts/HomepageLayout';
import HeroSection from '@/Components/Homepage/HeroSection';
import StatsSection from '@/Components/Homepage/StatsSection';
import FeaturedJobsSection from '@/Components/Homepage/FeaturedJobsSection';
import FeaturesSection from '@/Components/Homepage/FeaturesSection';
import TestimonialsSection from '@/Components/Homepage/TestimonialsSection';
import CTASection from '@/Components/Homepage/CTASection';
import Footer from '@/Components/Homepage/Footer';

export default function Homepage({ auth, stats, jobs }) {
    return (
        <ThemeProvider>
            <Head title="Find Your Dream Career | ATS" />
            <HomepageLayout>
                <HeroSection />
                <StatsSection stats={stats} />
                <FeaturedJobsSection jobs={jobs} />
                <FeaturesSection />
                <TestimonialsSection />
                <CTASection />
                <Footer />
            </HomepageLayout>
        </ThemeProvider>
    );
}
