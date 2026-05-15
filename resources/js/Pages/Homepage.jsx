import { Head } from '@inertiajs/react';
import { ThemeProvider } from '@/Contexts/ThemeContext';
import HomepageLayout from '@/Layouts/HomepageLayout';
import HeroSection from '@/Components/Homepage/HeroSection';
import FeaturesSection from '@/Components/Homepage/FeaturesSection';
import StatsSection from '@/Components/Homepage/StatsSection';
import FeaturedJobsSection from '@/Components/Homepage/FeaturedJobsSection';
import TestimonialsSection from '@/Components/Homepage/TestimonialsSection';
import CTASection from '@/Components/Homepage/CTASection';
import Footer from '@/Components/Homepage/Footer';

export default function Homepage({ stats, jobs }) {
    return (
        <ThemeProvider>
            <Head title="Find Your Dream Career | ATS" />
            <HomepageLayout>
                <HeroSection />
                <FeaturesSection />
                <StatsSection stats={stats} />
                <FeaturedJobsSection jobs={jobs} />
                <TestimonialsSection />
                <CTASection />
                <Footer />
            </HomepageLayout>
        </ThemeProvider>
    );
}
