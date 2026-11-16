<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class EquipmentAddModalUnitTest extends TestCase
{
    public function test_equipment_list_declares_can_add_equipment_as_always_available(): void
    {
        $listPath = resource_path('js/Pages/SuperAdmin/Equipment/List.jsx');
        $this->assertFileExists($listPath);

        $code = file_get_contents($listPath);

        // canAddEquipment must evaluate to true unconditionally for page viewers
        $this->assertMatchesRegularExpression(
            '/const\s+canAddEquipment\s*=\s*true;/',
            $code,
            'List.jsx must set canAddEquipment to true so the Add button is visible to all authenticated roles including Project Owner'
        );

        // effectiveCanManageEquipment must NOT block Project Owner via !isProjectOwner
        $this->assertDoesNotMatchRegularExpression(
            '/effectiveCanManageEquipment\s*=\s*canManageEquipment\s*!==\s*undefined\s*\?\s*\([^)]*!\s*isProjectOwner\)/',
            $code,
            'effectiveCanManageEquipment must not strip manage access from Project Owner'
        );
    }

    public function test_equipment_list_renders_add_equipment_button_with_handle_create(): void
    {
        $listPath = resource_path('js/Pages/SuperAdmin/Equipment/List.jsx');
        $this->assertFileExists($listPath);

        $code = file_get_contents($listPath);

        // Must have Add Equipment button with handleCreate
        $this->assertMatchesRegularExpression(
            '/onClick=\{handleCreate\}[^>]*>[\s\S]*?Add Equipment/i',
            $code,
            'List.jsx must render Add Equipment button bound to handleCreate'
        );

        // handleCreate must set isOpen to true and reset currentEquipment to null
        $this->assertStringContainsString(
            'setCurrentEquipment(null);',
            $code,
            'handleCreate must reset currentEquipment to null for adding new equipment'
        );
        $this->assertStringContainsString(
            'setIsOpen(true);',
            $code,
            'handleCreate must set isOpen to true'
        );
    }

    public function test_equipment_modal_renders_unconditionally_on_is_open(): void
    {
        $listPath = resource_path('js/Pages/SuperAdmin/Equipment/List.jsx');
        $this->assertFileExists($listPath);

        $code = file_get_contents($listPath);

        // The Add/Edit Modal must render directly using <Modal show={isOpen} without guard wrapping
        $this->assertDoesNotMatchRegularExpression(
            '/\{\(effectiveCanManageEquipment\s*\|\|\s*\(canCreateEquipment\s*&&/i',
            $code,
            'The Add/Edit Modal must not be conditionally wrapped in restrictive guards that prevent it from rendering on isOpen'
        );

        $this->assertStringContainsString(
            '<Modal show={isOpen} onClose={handleClose}',
            $code,
            'The Add/Edit modal must be rendered with <Modal show={isOpen} onClose={handleClose}'
        );
    }
}

