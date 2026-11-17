<?php

namespace Tests\Feature\Construction;

use Tests\TestCase;

class TaskSubmissionModalAlignmentFeatureTest extends TestCase
{
    public function test_task_submission_modal_has_proper_scrolling_padding_and_no_duplicate_titles(): void
    {
        $showPath = resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx');
        $this->assertFileExists($showPath);

        $code = file_get_contents($showPath);

        // 1. Modal scroll container has pb-12 or greater to prevent content being clipped by the sticky footer
        $this->assertMatchesRegularExpression(
            '/overflow-y-auto\s+p-6\s+pb-(?:12|14|16)/',
            $code,
            'Modal body must have adequate bottom padding (pb-12 or greater) to prevent footer clipping'
        );

        // 2. Drafting & CAD drawing section has distinct ID and top margin for scrolling
        $this->assertStringContainsString(
            'id="drafting-cad-workspace-card"',
            $code,
            'Drafting CAD workspace card must have id for smooth scrolling'
        );

        // 3. Proof photos use flexible wrap layout
        $this->assertStringContainsString(
            'proof-photos-container',
            $code,
            'Proof photos must use proof-photos-container flex wrap'
        );

        // 4. GPS coordinates are wrapped in dedicated styled container
        $this->assertStringContainsString(
            'task-submission-coords',
            $code,
            'Coordinates must be wrapped in task-submission-coords'
        );

        // 5. Intelligent metrics display for non-survey tasks
        $this->assertStringContainsString(
            'hasSurveyData',
            $code,
            'Metrics grid must adapt to survey vs non-survey tasks without showing empty dashes'
        );
    }

    public function test_field_notes_display_has_raw_json_toggle_in_header_without_empty_footer_row(): void
    {
        $fieldNotesPath = resource_path('js/Pages/Construction/Components/FieldNotesDisplay.jsx');
        $this->assertFileExists($fieldNotesPath);

        $code = file_get_contents($fieldNotesPath);

        // Header has raw JSON toggle button
        $this->assertStringContainsString(
            'raw-json-header-toggle',
            $code,
            'FieldNotesDisplay must position the raw JSON toggle in the header'
        );

        // Redundant empty footer row must not exist
        $this->assertStringNotContainsString(
            'View Raw JSON Data',
            $code,
            'FieldNotesDisplay must not render raw text "View Raw JSON Data" in a lonely bottom row'
        );
    }
}
