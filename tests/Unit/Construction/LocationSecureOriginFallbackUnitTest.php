<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class LocationSecureOriginFallbackUnitTest extends TestCase
{
    public function test_project_controller_defines_current_ip_location_endpoint(): void
    {
        $controllerPath = app_path('Http/Controllers/SuperAdmin/Construction/ProjectController.php');
        $this->assertFileExists($controllerPath);

        $code = file_get_contents($controllerPath);

        // Controller must define currentIpLocation method
        $this->assertMatchesRegularExpression(
            '/public function currentIpLocation\s*\(\s*Request\s+\$request\s*\)/i',
            $code,
            'ProjectController must define currentIpLocation method for network location fallback'
        );
    }

    public function test_route_for_current_ip_location_is_registered(): void
    {
        $routesPath = base_path('routes/web.php');
        $this->assertFileExists($routesPath);

        $code = file_get_contents($routesPath);

        $this->assertStringContainsString(
            'projects.current-ip-location',
            $code,
            'routes/web.php must register projects.current-ip-location route'
        );
    }

    public function test_google_map_location_section_guards_against_insecure_origin_and_uses_ip_fallback(): void
    {
        $componentPath = resource_path('js/Components/GoogleMapLocationSection.jsx');
        $this->assertFileExists($componentPath);

        $code = file_get_contents($componentPath);

        // Must check isSecureContext or handle insecure origins
        $this->assertMatchesRegularExpression(
            '/isSecureContext/i',
            $code,
            'GoogleMapLocationSection.jsx must check isSecureContext to handle non-HTTPS origins'
        );

        // Must define network / IP location fallback
        $this->assertMatchesRegularExpression(
            '/fetchNetworkLocation|fetchIpLocation|current-ip-location/i',
            $code,
            'GoogleMapLocationSection.jsx must include network/IP fallback for location detection'
        );

        // Must not directly show raw error.message without guarding secure origin
        $this->assertStringNotContainsString(
            "toast.error(error.message || 'Unable to retrieve location.');",
            $code,
            'GoogleMapLocationSection.jsx must not output raw browser secure origin error to toast'
        );
    }
}
