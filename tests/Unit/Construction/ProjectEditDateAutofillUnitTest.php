<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class ProjectEditDateAutofillUnitTest extends TestCase
{
    public function test_projects_index_formats_dates_for_input_fields_in_edit_modal(): void
    {
        $indexPath = resource_path('js/Pages/SuperAdmin/Construction/Projects/Index.jsx');
        $this->assertFileExists($indexPath);

        $code = file_get_contents($indexPath);

        // 1. Must define a date formatting helper for date input fields (YYYY-MM-DD)
        $this->assertMatchesRegularExpression(
            "/function\s+formatDateForInput|const\s+formatDateForInput/i",
            $code,
            'Index.jsx must define a helper function formatDateForInput to normalize dates to YYYY-MM-DD'
        );

        // 2. openEditModal must use formatDateForInput or format start_date and expected_end_date
        $this->assertMatchesRegularExpression(
            "/openEditModal\s*=\s*\([^)]*\)\s*=>\s*\{[\s\S]*?start_date:\s*formatDateForInput\(project\.start_date\)/i",
            $code,
            'openEditModal must format project.start_date with formatDateForInput'
        );
        $this->assertMatchesRegularExpression(
            "/openEditModal\s*=\s*\([^)]*\)\s*=>\s*\{[\s\S]*?expected_end_date:\s*formatDateForInput\(project\.expected_end_date\)/i",
            $code,
            'openEditModal must format project.expected_end_date with formatDateForInput'
        );

        // 3. InputField component should sanitize type="date" inputs so ISO strings don't break HTML5 date input
        $this->assertMatchesRegularExpression(
            "/function\s+InputField[\s\S]*?type\s*===\s*['\"]date['\"]/i",
            $code,
            'InputField must handle type="date" to ensure value is formatted as YYYY-MM-DD'
        );
    }
}
