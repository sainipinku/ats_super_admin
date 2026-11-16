<?php

namespace Tests\Feature\Construction;

use Tests\TestCase;

class EquipmentAddModalFeatureTest extends TestCase
{
    public function test_equipment_master_controller_passes_creation_and_manage_permissions_for_actors(): void
    {
        $controllerPath = app_path('Http/Controllers/SuperAdmin/EquipmentMasterController.php');
        $this->assertFileExists($controllerPath);

        $code = file_get_contents($controllerPath);

        // index method must pass canCreateEquipment => true and canManageEquipment => true (or unconditional truthy)
        $this->assertMatchesRegularExpression(
            "/'canCreateEquipment'\s*=>\s*true/",
            $code,
            'EquipmentMasterController::index must pass canCreateEquipment as true'
        );
        $this->assertMatchesRegularExpression(
            "/'canManageEquipment'\s*=>\s*true/",
            $code,
            'EquipmentMasterController::index must pass canManageEquipment as true'
        );
    }

    public function test_equipment_list_component_has_add_equipment_form_fields_and_store_route(): void
    {
        $listPath = resource_path('js/Pages/SuperAdmin/Equipment/List.jsx');
        $this->assertFileExists($listPath);

        $code = file_get_contents($listPath);

        // Must submit to super.equipment.store
        $this->assertStringContainsString(
            'route("super.equipment.store")',
            $code,
            'List.jsx must submit to route("super.equipment.store") when creating new equipment'
        );

        // Must have input fields for adding equipment
        $this->assertStringContainsString('name="equipment_name"', $code);
        $this->assertStringContainsString('name="category_id"', $code);
        $this->assertStringContainsString('name="quantity"', $code);
        $this->assertStringContainsString('name="ownership_type"', $code);
        $this->assertStringContainsString('name="status"', $code);
    }

    public function test_equipment_list_does_not_suppress_add_button_for_project_owner(): void
    {
        $listPath = resource_path('js/Pages/SuperAdmin/Equipment/List.jsx');
        $this->assertFileExists($listPath);

        $code = file_get_contents($listPath);

        // canAddEquipment must NOT evaluate to false when isProjectOwner is true
        $this->assertStringContainsString('const canAddEquipment = true;', $code);
        $this->assertStringContainsString('<Modal show={isOpen} onClose={handleClose}', $code);
    }
}

