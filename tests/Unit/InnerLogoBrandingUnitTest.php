<?php

namespace Tests\Unit;

use Tests\TestCase;

class InnerLogoBrandingUnitTest extends TestCase
{
    public function test_logo_1_asset_exists_in_public_storage_and_source_storage_and_is_valid_jpeg(): void
    {
        $publicLogo = public_path('storage/Logo_1.jpeg');
        $storageLogo = storage_path('app/public/Logo_1.jpeg');

        $this->assertFileExists($publicLogo, 'public/storage/Logo_1.jpeg must exist and be accessible via /storage/Logo_1.jpeg');
        $this->assertFileExists($storageLogo, 'storage/app/public/Logo_1.jpeg must exist');

        $imageInfo = @getimagesize($publicLogo);
        $this->assertIsArray($imageInfo, 'Logo_1.jpeg must be a valid image file');
        $this->assertGreaterThan(0, $imageInfo[0] ?? 0);
        $this->assertGreaterThan(0, $imageInfo[1] ?? 0);
        $this->assertSame('image/jpeg', $imageInfo['mime'] ?? null);
    }

    public function test_application_logo_defaults_to_storage_logo_1_and_avoids_tiny_circular_clipping(): void
    {
        $path = resource_path('js/Components/ApplicationLogo.jsx');
        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringContainsString('/storage/Logo_1.jpeg', $content);
        $this->assertStringContainsString('export const INNER_PROJECT_LOGO_SRC', $content);
        $this->assertStringContainsString('export function resolveInnerLogoSrc', $content);
        $this->assertStringContainsString('object-contain', $content);
        $this->assertStringContainsString('bg-white', $content);
        $this->assertStringNotContainsString('max-w-[20px]', $content);
        $this->assertStringNotContainsString('rounded-full', $content);
    }

    public function test_shared_sidebar_brand_logo_component_renders_horizontal_logo_card_without_legacy_brand(): void
    {
        $path = resource_path('js/Components/SidebarBrandLogo.jsx');
        $this->assertFileExists($path, 'Shared SidebarBrandLogo.jsx component must exist');

        $content = file_get_contents($path);

        $this->assertStringContainsString('INNER_PROJECT_LOGO_SRC', $content);
        $this->assertStringContainsString('object-contain', $content);
        $this->assertStringContainsString('rounded-xl', $content);
        $this->assertStringContainsString('bg-white', $content);
        $this->assertStringContainsString('subtitle', $content);
        $this->assertStringContainsString('companyName', $content);
        $this->assertStringContainsString('onClose', $content);
        $this->assertStringNotContainsString('/images/cadmax_con_logo.jpeg', $content);
        $this->assertStringNotContainsString('betaxtech', $content);
    }

    public function test_public_homepage_and_homepage_layout_keep_original_branding(): void
    {
        $homepageLayout = file_get_contents(resource_path('js/Layouts/HomepageLayout.jsx'));
        $homepage = file_get_contents(resource_path('js/Pages/Homepage.jsx'));

        $this->assertStringContainsString('/images/cadmax_con_logo.jpeg', $homepageLayout);
        $this->assertStringNotContainsString('/storage/Logo_1.jpeg', $homepageLayout);

        $this->assertStringContainsString('/images/cadmax_con_logo.jpeg', $homepage);
        $this->assertStringNotContainsString('/storage/Logo_1.jpeg', $homepage);
    }
}
