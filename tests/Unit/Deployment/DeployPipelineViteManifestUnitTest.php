<?php

namespace Tests\Unit\Deployment;

use Tests\TestCase;

class DeployPipelineViteManifestUnitTest extends TestCase
{
    public function test_vite_config_explicitly_outputs_manifest_json_in_public_build(): void
    {
        $path = base_path('vite.config.js');
        $this->assertFileExists($path);

        $code = file_get_contents($path);

        $this->assertStringContainsString("manifest: 'manifest.json'", $code, 'vite.config.js must explicitly write manifest.json');
        $this->assertStringContainsString("outDir: 'public/build'", $code, 'vite.config.js must explicitly target public/build');
    }

    public function test_deploy_workflow_validates_manifest_and_prevents_missing_vite_manifest_on_live(): void
    {
        $path = base_path('.github/workflows/deploy.yml');
        $this->assertFileExists($path);

        $yaml = file_get_contents($path);

        // 1. Verify manifest exists after npm run build in CI job before tarring
        $this->assertStringContainsString('test -f public/build/manifest.json', $yaml, 'CI job must verify public/build/manifest.json exists before archiving');

        // 2. Use run-scoped archive name to prevent /tmp permission collisions
        $this->assertStringContainsString('frontend-build-${{ github.run_id }}.tar.gz', $yaml, 'Archive filename must be unique per workflow run');

        // 3. Remove stale public/hot file on production server
        $this->assertStringContainsString('rm -f public/hot', $yaml, 'Deploy script must remove stale public/hot');

        // 4. Verify extracted manifest before swapping public/build
        $this->assertStringContainsString('public/build_new/manifest.json', $yaml, 'Deploy script must verify extracted manifest before swapping');

        // 5. Fallback VPS npm build & hard post-deploy manifest gate
        $this->assertStringContainsString('npm run build', $yaml, 'Deploy script must support fallback build');
        $this->assertStringContainsString('[ ! -f public/build/manifest.json ]', $yaml, 'Deploy script must fail hard if public/build/manifest.json is still missing');
    }
}
