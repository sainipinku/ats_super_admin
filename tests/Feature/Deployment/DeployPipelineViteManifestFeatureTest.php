<?php

namespace Tests\Feature\Deployment;

use Tests\TestCase;

class DeployPipelineViteManifestFeatureTest extends TestCase
{
    public function test_compiled_vite_manifest_contains_app_jsx_entrypoint_required_by_blade(): void
    {
        $blade = file_get_contents(resource_path('views/app.blade.php'));
        $this->assertStringContainsString("@vite(['resources/js/app.jsx'])", $blade);

        $manifestPath = public_path('build/manifest.json');
        $this->assertFileExists($manifestPath, 'public/build/manifest.json must exist after npm run build');

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $this->assertIsArray($manifest, 'public/build/manifest.json must be valid JSON');
        $this->assertArrayHasKey('resources/js/app.jsx', $manifest, 'Vite manifest must contain resources/js/app.jsx');
        $this->assertNotEmpty($manifest['resources/js/app.jsx']['file'] ?? null);
    }

    public function test_ci_workflow_verifies_vite_manifest_and_installs_dependencies_reliably(): void
    {
        $ciPath = base_path('.github/workflows/ci.yml');
        $this->assertFileExists($ciPath);

        $yaml = file_get_contents($ciPath);

        $this->assertStringContainsString('npm ci || npm install', $yaml, 'CI workflow should fall back to npm install when package-lock.json is gitignored');
        $this->assertStringContainsString('test -f public/build/manifest.json', $yaml, 'CI workflow must assert public/build/manifest.json was generated');
    }
}
