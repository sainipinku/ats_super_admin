<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
   
    public function up(): void
    {
        // Convert status from ENUM('active','inactive','sold') to TINYINT
        DB::statement("ALTER TABLE vehicles MODIFY COLUMN status TINYINT NOT NULL DEFAULT '0' COMMENT '0 = Active, 1 = Inactive, 2 = Sold'");

        // Convert insurance_status from VARCHAR to TINYINT
        // Existing string values 'Active' → 1, anything else → 0
        DB::statement("UPDATE vehicles SET insurance_status = CASE WHEN insurance_status = 'Active' THEN 1 ELSE 0 END WHERE insurance_status IS NOT NULL");
        DB::statement("ALTER TABLE vehicles MODIFY COLUMN insurance_status TINYINT DEFAULT NULL COMMENT '0 = Expired, 1 = Active'");

        // Convert puc_status from VARCHAR to TINYINT
        // Existing string values 'Valid' → 1, anything else → 0
        DB::statement("UPDATE vehicles SET puc_status = CASE WHEN puc_status = 'Valid' THEN 1 ELSE 0 END WHERE puc_status IS NOT NULL");
        DB::statement("ALTER TABLE vehicles MODIFY COLUMN puc_status TINYINT DEFAULT NULL COMMENT '0 = Expired, 1 = Valid'");

        // Convert payment_status from ENUM('paid','unpaid') to TINYINT
        // Existing string values 'paid' → 1, 'unpaid' → 0
        DB::statement("UPDATE vehicles SET payment_status = CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END WHERE payment_status IS NOT NULL");
        DB::statement("ALTER TABLE vehicles MODIFY COLUMN payment_status TINYINT DEFAULT NULL COMMENT '0 = Unpaid, 1 = Paid'");
    }

    public function down(): void
    {
        // Revert payment_status from TINYINT back to ENUM
        DB::statement("UPDATE vehicles SET payment_status = CASE WHEN payment_status = 1 THEN 'paid' ELSE 'unpaid' END WHERE payment_status IS NOT NULL");
        DB::statement("ALTER TABLE vehicles MODIFY COLUMN payment_status ENUM('paid', 'unpaid') DEFAULT NULL");

        // Revert puc_status from TINYINT back to VARCHAR
        DB::statement("UPDATE vehicles SET puc_status = CASE WHEN puc_status = 1 THEN 'Valid' ELSE 'Expired' END WHERE puc_status IS NOT NULL");
        DB::statement("ALTER TABLE vehicles MODIFY COLUMN puc_status VARCHAR(255) DEFAULT NULL");

        // Revert insurance_status from TINYINT back to VARCHAR
        DB::statement("UPDATE vehicles SET insurance_status = CASE WHEN insurance_status = 1 THEN 'Active' ELSE 'Expired' END WHERE insurance_status IS NOT NULL");
        DB::statement("ALTER TABLE vehicles MODIFY COLUMN insurance_status VARCHAR(255) DEFAULT NULL");

        // Revert status from TINYINT back to ENUM
        DB::statement("UPDATE vehicles SET status = CASE WHEN status = 0 THEN 'active' WHEN status = 1 THEN 'inactive' WHEN status = 2 THEN 'sold' ELSE 'active' END");
        DB::statement("ALTER TABLE vehicles MODIFY COLUMN status ENUM('active', 'inactive', 'sold') NOT NULL DEFAULT 'active'");
    }
};