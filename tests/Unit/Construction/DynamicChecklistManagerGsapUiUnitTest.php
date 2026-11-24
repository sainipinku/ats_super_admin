<?php

namespace Tests\Unit\Construction;

use PHPUnit\Framework\TestCase;

class DynamicChecklistManagerGsapUiUnitTest extends TestCase
{
    private string $componentPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->componentPath = dirname(__DIR__, 3)
            . '/resources/js/Pages/Construction/Components/DynamicChecklistManager.jsx';
    }

    public function test_dynamic_checklist_manager_imports_gsap_with_scoped_context_cleanup(): void
    {
        $this->assertFileExists($this->componentPath);
        $source = file_get_contents($this->componentPath);

        $this->assertStringContainsString('import gsap from "gsap";', $source);
        $this->assertStringContainsString('gsap.context(', $source);
        $this->assertStringContainsString('ctx.revert()', $source);
    }

    public function test_dynamic_checklist_manager_implements_gsap_text_reveal_and_progress_effects(): void
    {
        $source = file_get_contents($this->componentPath);

        // Animated word-by-word heading text spans & kinetic title reveal
        $this->assertStringContainsString('dcm-title-word', $source);
        $this->assertStringContainsString('dcm-group-title', $source);
        $this->assertStringContainsString('dcm-progress-fill', $source);
        $this->assertStringContainsString('dcm-pct-value', $source);
    }

    public function test_dynamic_checklist_manager_implements_screen_zoom_out_and_inner_cascade_effects(): void
    {
        $source = file_get_contents($this->componentPath);

        // Outer stage zoom-out reveal + inner group cards & checklist rows cascade
        $this->assertStringContainsString('dcm-stage', $source);
        $this->assertStringContainsString('dcm-header-card', $source);
        $this->assertStringContainsString('dcm-group-card', $source);
        $this->assertStringContainsString('dcm-checklist-row', $source);
        $this->assertStringContainsString('dcm-add-form-panel', $source);
        $this->assertStringContainsString('dcm-modal-panel', $source);
        $this->assertStringContainsString('IntersectionObserver', $source);
        $this->assertMatchesRegularExpression('/scale:\s*1\.0[3-6]/', $source);
    }

    public function test_dynamic_checklist_manager_implements_gsap_hover_popping_and_toggle_pop_effects(): void
    {
        $source = file_get_contents($this->componentPath);

        // Hover pop enter/leave handlers using springy back.out easing
        $this->assertStringContainsString('handlePopEnter', $source);
        $this->assertStringContainsString('handlePopLeave', $source);
        $this->assertStringContainsString('back.out(', $source);
        $this->assertStringContainsString('onMouseEnter', $source);
        $this->assertStringContainsString('onMouseLeave', $source);
        $this->assertStringContainsString('animateCheckboxPop', $source);
    }

    public function test_dynamic_checklist_manager_uses_valid_tailwind_v3_padding_and_no_invalid_utilities(): void
    {
        $source = file_get_contents($this->componentPath);

        // Guard against invalid Tailwind v3 fractional padding classes that compile to 0px
        $this->assertDoesNotMatchRegularExpression('/\bp-4\.5\b/', $source);
        $this->assertDoesNotMatchRegularExpression('/\bpy-0\.2\b/', $source);
        $this->assertDoesNotMatchRegularExpression('/\bpx-4\.5\b/', $source);
    }

    public function test_dynamic_checklist_manager_uses_compact_mini_space_two_column_grid_and_ten_per_page_pagination(): void
    {
        $source = file_get_contents($this->componentPath);

        // 10 entries per batch, 2 cards per row in compact mini-space grid
        $this->assertStringContainsString('const GROUPS_PER_PAGE = 10;', $source);
        $this->assertStringContainsString('grid gap-3 md:grid-cols-2', $source);
        $this->assertStringContainsString('visibleCount', $source);
        $this->assertStringContainsString('hasMoreGroups', $source);
        $this->assertStringContainsString('handleLoadMore', $source);
        $this->assertStringContainsString('dcm-load-more-btn', $source);
        $this->assertStringContainsString('Load More', $source);
    }

    public function test_dynamic_checklist_manager_implements_gsap_loading_animation_for_load_more(): void
    {
        $source = file_get_contents($this->componentPath);

        // GSAP loading spinner/dots + incremental reveal for newly appended cards
        $this->assertStringContainsString('isLoadingMore', $source);
        $this->assertStringContainsString('dcm-load-more-loader', $source);
        $this->assertStringContainsString('dcm-loader-dot', $source);
        $this->assertStringContainsString('dcm-loader-ring', $source);
        $this->assertStringContainsString('data-dcm-index', $source);
        $this->assertStringContainsString('repeat: -1', $source);
    }
}
