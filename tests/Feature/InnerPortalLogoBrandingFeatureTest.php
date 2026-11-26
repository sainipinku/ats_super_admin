<?php

namespace Tests\Feature;

use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class InnerPortalLogoBrandingFeatureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_all_inner_portal_sidebars_use_shared_sidebar_brand_logo_and_drop_legacy_logos(): void
    {
        $sidebars = [
            resource_path('js/Pages/SuperAdmin/Layouts/Sidebar.jsx'),
            resource_path('js/Pages/Admin/Layouts/Sidebar.jsx'),
            resource_path('js/Pages/Member/Layouts/Sidebar.jsx'),
            resource_path('js/Pages/CallingTeam/Layouts/Sidebar.jsx'),
        ];

        foreach ($sidebars as $sidebarPath) {
            $this->assertFileExists($sidebarPath);
            $content = file_get_contents($sidebarPath);

            $this->assertStringContainsString(
                "import SidebarBrandLogo from '@/Components/SidebarBrandLogo'",
                str_replace('"', "'", $content),
                basename(dirname(dirname($sidebarPath))) . ' Sidebar must import SidebarBrandLogo'
            );
            $this->assertStringContainsString(
                '<SidebarBrandLogo',
                $content,
                basename(dirname(dirname($sidebarPath))) . ' Sidebar must render <SidebarBrandLogo />'
            );
            $this->assertStringNotContainsString('/images/cadmax_con_logo.jpeg', $content);
            $this->assertStringNotContainsString('/images/logo.png', $content);
            $this->assertStringNotContainsString('/images/logo-dark.png', $content);
            $this->assertStringNotContainsString('betaxtech', $content);
        }
    }

    public function test_all_inner_authenticated_layouts_render_application_logo_without_commenting_it_out(): void
    {
        $layouts = [
            resource_path('js/Layouts/AuthenticatedLayout.jsx'),
            resource_path('js/Pages/SuperAdmin/Layouts/AuthenticatedLayout.jsx'),
            resource_path('js/Pages/Admin/Layouts/AuthenticatedLayout.jsx'),
            resource_path('js/Pages/Member/Layouts/AuthenticatedLayout.jsx'),
            resource_path('js/Pages/CallingTeam/Layouts/AuthenticatedLayout.jsx'),
        ];

        foreach ($layouts as $layoutPath) {
            $this->assertFileExists($layoutPath);
            $content = file_get_contents($layoutPath);

            $this->assertStringContainsString('ApplicationLogo', $content);
            $this->assertMatchesRegularExpression(
                '/<ApplicationLogo\b/',
                $content,
                $layoutPath . ' must render <ApplicationLogo />'
            );
            $this->assertDoesNotMatchRegularExpression(
                '/\{\/\*(?:(?!\*\/)[\s\S])*?<ApplicationLogo(?:(?!\*\/)[\s\S])*?\*\//',
                $content,
                $layoutPath . ' must not leave <ApplicationLogo /> commented out'
            );
        }
    }

    public function test_compiled_vite_assets_include_storage_logo_1_reference(): void
    {
        $manifestPath = public_path('build/manifest.json');
        $this->assertFileExists($manifestPath, 'public/build/manifest.json must exist after build');

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $this->assertIsArray($manifest);

        $assetFiles = glob(public_path('build/assets/*.js')) ?: [];
        $this->assertNotEmpty($assetFiles, 'Built JS assets must exist in public/build/assets');

        $found = false;
        foreach ($assetFiles as $file) {
            $js = file_get_contents($file);
            if (is_string($js) && str_contains($js, '/storage/Logo_1.jpeg')) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Compiled Vite bundle must contain /storage/Logo_1.jpeg');
    }

    public function test_authenticated_superadmin_inner_portal_renders_successfully_with_vite_bundle(): void
    {
        $superAdmin = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Logo Verify SuperAdmin',
            'email' => 'logo-verify-' . Str::random(6) . '@example.com',
            'phone' => '9' . random_int(100000000, 999999999),
            'whatsapp_phone' => '9' . random_int(100000000, 999999999),
            'password' => 'password',
            'roles' => ['super_admin'],
        ]);

        $response = $this->actingAs($superAdmin, 'superadmin')
            ->get('/super/profile');

        $response->assertStatus(200);
        $response->assertSee('data-page', false);
        $this->assertFileExists(public_path('storage/Logo_1.jpeg'));
    }
}
