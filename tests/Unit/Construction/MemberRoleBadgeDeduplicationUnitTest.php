<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class MemberRoleBadgeDeduplicationUnitTest extends TestCase
{
    public function test_show_component_does_not_render_duplicate_driver_or_draft_emojis_in_assign_dropdown(): void
    {
        $showPath = resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx');
        $this->assertFileExists($showPath);

        $code = file_get_contents($showPath);

        // Must NOT append hardcoded emojis and duplicate text in transfer dropdown option
        $this->assertStringNotContainsString(
            '🚗 (Driver)',
            $code,
            'Show.jsx must not append hardcoded 🚗 (Driver) suffix to member option in task assignment modal'
        );
        $this->assertStringNotContainsString(
            '⭐ (Draft Person)',
            $code,
            'Show.jsx must not append hardcoded ⭐ (Draft Person) suffix to member option in task assignment modal'
        );
    }

    public function test_member_badges_utility_excludes_designations_and_deduplicates_driver_aliases(): void
    {
        $badgesPath = resource_path('js/lib/memberBadges.js');
        $this->assertFileExists($badgesPath);

        $code = file_get_contents($badgesPath);

        // Must not push designation or designation_text into role badges
        $this->assertStringNotContainsString(
            'member.designation_text',
            $code,
            'memberBadges.js must not add designation_text to role badges'
        );
        $this->assertStringNotContainsString(
            'member.designation',
            $code,
            'memberBadges.js must not add designation to role badges'
        );
    }

    public function test_member_model_is_driver_does_not_hardcode_arbitrary_numeric_id_six(): void
    {
        $memberPath = app_path('Models/Member.php');
        $this->assertFileExists($memberPath);

        $code = file_get_contents($memberPath);

        // isDriver must not hardcode (int) $r === 6
        $this->assertDoesNotMatchRegularExpression(
            '/if\s*\(\s*\(int\)\s*\\\$r\s*===\s*6\s*\)\s*\{\s*return\s+true;\s*\}/',
            $code,
            'Member::isDriver must not hardcode numeric id 6 as Driver'
        );

        // getRoleBadgesAttribute must not have bogus hardcoded 6 => 'Driver'
        $this->assertDoesNotMatchRegularExpression(
            '/6\s*=>\s*[\'"]Driver[\'"]/',
            $code,
            'Member::getRoleBadgesAttribute must not hardcode 6 => Driver'
        );

        // getRoleBadgesAttribute must not include designation_names
        $this->assertDoesNotMatchRegularExpression(
            '/\\\$desigStr\s*=\s*\\\$this->designation_names/',
            $code,
            'Member::getRoleBadgesAttribute must not include designation names in role badges'
        );
    }
}
