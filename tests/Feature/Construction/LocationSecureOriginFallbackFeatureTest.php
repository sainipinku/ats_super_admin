<?php

namespace Tests\Feature\Construction;

use Tests\TestCase;

class LocationSecureOriginFallbackFeatureTest extends TestCase
{
    public function test_controller_current_ip_location_handles_request_and_returns_location_format(): void
    {
        $controllerPath = app_path('Http/Controllers/SuperAdmin/Construction/ProjectController.php');
        $this->assertFileExists($controllerPath);

        $code = file_get_contents($controllerPath);

        // Verify currentIpLocation resolves client IP and queries providers
        $this->assertStringContainsString('$request->header(\'CF-Connecting-IP\')', $code);
        $this->assertStringContainsString('ip-api.com/json', $code);
        $this->assertStringContainsString('freeipapi.com/api/json', $code);
        $this->assertStringContainsString('formatted_address', $code);
    }

    public function test_frontend_location_picker_checks_secure_context_and_suppresses_secure_origin_error(): void
    {
        $componentPath = resource_path('js/Components/GoogleMapLocationSection.jsx');
        $this->assertFileExists($componentPath);

        $code = file_get_contents($componentPath);

        // Verify isInsecureOrigin check
        $this->assertMatchesRegularExpression(
            '/isInsecureOrigin\s*=\s*typeof window !== [\'"]undefined[\'"] && window\.isSecureContext === false/i',
            $code,
            'GoogleMapLocationSection.jsx must check if origin is insecure before invoking navigator.geolocation'
        );

        // Verify fallback network query
        $this->assertStringContainsString(
            '/super/construction/projects/current-ip-location',
            $code,
            'GoogleMapLocationSection.jsx must query internal current-ip-location endpoint'
        );

        // Verify friendly user toast
        $this->assertStringContainsString(
            'Device GPS requires an HTTPS secure connection.',
            $code,
            'GoogleMapLocationSection.jsx must display friendly guidance instead of raw browser security error'
        );
    }
}
