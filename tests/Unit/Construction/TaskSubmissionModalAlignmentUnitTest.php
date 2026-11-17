<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class TaskSubmissionModalAlignmentUnitTest extends TestCase
{
    public function test_show_component_has_bottom_scroll_clearance_and_clean_layout(): void
    {
        $showPath = resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx');
        $this->assertFileExists($showPath);

        $code = file_get_contents($showPath);

        // 1. Must have bottom padding clearance on modal body to prevent footer clipping
        $this->assertMatchesRegularExpression(
            '/overflow-y-auto\s+p-6\s+pb-(?:10|12|14|16)/',
            $code,
            'Modal body must have adequate bottom padding (pb-10 or greater) to prevent footer clipping'
        );

        // 2. Proof photos must use flexible or responsive layout rather than rigid col grid
        $this->assertStringContainsString(
            'proof-photos-container',
            $code,
            'Proof photos container must have dedicated class or flex-wrap layout'
        );

        // 3. Location coordinates must be wrapped in a styled location card/pill
        $this->assertStringContainsString(
            'task-submission-coords',
            $code,
            'GPS coordinates must be wrapped in a dedicated styled location container'
        );
    }

    public function test_field_notes_display_aligns_raw_json_toggle_in_header(): void
    {
        $fieldNotesPath = resource_path('js/Pages/Construction/Components/FieldNotesDisplay.jsx');
        $this->assertFileExists($fieldNotesPath);

        $code = file_get_contents($fieldNotesPath);

        // Raw JSON toggle button must be aligned cleanly inside the header
        $this->assertStringContainsString(
            'raw-json-header-toggle',
            $code,
            'FieldNotesDisplay must position the raw JSON toggle cleanly within the header'
        );
    }
}
