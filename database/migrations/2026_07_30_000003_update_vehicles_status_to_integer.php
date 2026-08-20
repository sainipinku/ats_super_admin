<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert vehicle status columns from string/enum to tinyInteger.
     *
     * Uses Eloquent to migrate data to avoid MySQL strict mode issues
     * where string comparisons on tables with DECIMAL columns fail.
     *
     * Status mapping:
     *   status:          0 = Active, 1 = Inactive, 2 = Sold
     *   insurance_status: 0 = Expired, 1 = Active
     *   puc_status:       0 = Expired, 1 = Valid
     *   payment_status:   0 = Unpaid, 1 = Paid
     */
    public function up(): void
    {
        // Clean up any leftover columns from previous failed runs
        // (MySQL < 8.0.29 does not support DROP COLUMN IF EXISTS)
        foreach (['payment_status_new', 'status_new', 'insurance_status_new', 'puc_status_new'] as $col) {
            $exists = DB::table('information_schema.columns')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', 'vehicles')
                ->where('column_name', $col)
                ->exists();
            if ($exists) {
                DB::statement("ALTER TABLE vehicles DROP COLUMN `{$col}`");
            }
        }

        // --- payment_status: ENUM('paid','unpaid') → TINYINT ---
        DB::statement("ALTER TABLE vehicles ADD COLUMN payment_status_new TINYINT DEFAULT NULL COMMENT '0 = Unpaid, 1 = Paid' AFTER payment_status");
        $this->migrateColumn('payment_status', 'payment_status_new', ['paid' => 1, 'unpaid' => 0]);
        DB::statement("ALTER TABLE vehicles DROP COLUMN payment_status");
        DB::statement("ALTER TABLE vehicles CHANGE COLUMN payment_status_new payment_status TINYINT DEFAULT NULL COMMENT '0 = Unpaid, 1 = Paid'");

        // --- status: ENUM('active','inactive','sold') → TINYINT ---
        DB::statement("ALTER TABLE vehicles ADD COLUMN status_new TINYINT NOT NULL DEFAULT '0' COMMENT '0 = Active, 1 = Inactive, 2 = Sold' AFTER status");
        $this->migrateColumn('status', 'status_new', ['active' => 0, 'inactive' => 1, 'sold' => 2]);
        DB::statement("ALTER TABLE vehicles DROP COLUMN status");
        DB::statement("ALTER TABLE vehicles CHANGE COLUMN status_new status TINYINT NOT NULL DEFAULT '0' COMMENT '0 = Active, 1 = Inactive, 2 = Sold'");

        // --- insurance_status: VARCHAR → TINYINT ---
        DB::statement("ALTER TABLE vehicles ADD COLUMN insurance_status_new TINYINT DEFAULT NULL COMMENT '0 = Expired, 1 = Active' AFTER insurance_status");
        $this->migrateColumn('insurance_status', 'insurance_status_new', ['Active' => 1], 0);
        DB::statement("ALTER TABLE vehicles DROP COLUMN insurance_status");
        DB::statement("ALTER TABLE vehicles CHANGE COLUMN insurance_status_new insurance_status TINYINT DEFAULT NULL COMMENT '0 = Expired, 1 = Active'");

        // --- puc_status: VARCHAR → TINYINT ---
        DB::statement("ALTER TABLE vehicles ADD COLUMN puc_status_new TINYINT DEFAULT NULL COMMENT '0 = Expired, 1 = Valid' AFTER puc_status");
        $this->migrateColumn('puc_status', 'puc_status_new', ['Valid' => 1], 0);
        DB::statement("ALTER TABLE vehicles DROP COLUMN puc_status");
        DB::statement("ALTER TABLE vehicles CHANGE COLUMN puc_status_new puc_status TINYINT DEFAULT NULL COMMENT '0 = Expired, 1 = Valid'");
    }

    /**
     * Migrate data from old string column to new integer column using Eloquent.
     * This avoids MySQL strict mode issues with DECIMAL columns.
     */
    private function migrateColumn(string $oldCol, string $newCol, array $mapping, ?int $default = null): void
    {
        $rows = DB::table('vehicles')->select('id', $oldCol)->get();

        foreach ($rows as $row) {
            $value = $row->$oldCol;
            $newValue = null;

            if ($value !== null) {
                $lower = strtolower((string) $value);
                $found = false;
                foreach ($mapping as $str => $int) {
                    if (strtolower($str) === $lower) {
                        $newValue = $int;
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $newValue = $default;
                }
            }

            DB::table('vehicles')->where('id', $row->id)->update([$newCol => $newValue]);
        }
    }

    public function down(): void
    {
        // Clean up any leftover columns
        // (MySQL < 8.0.29 does not support DROP COLUMN IF EXISTS)
        foreach (['payment_status_old', 'status_old', 'insurance_status_old', 'puc_status_old'] as $col) {
            $exists = DB::table('information_schema.columns')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', 'vehicles')
                ->where('column_name', $col)
                ->exists();
            if ($exists) {
                DB::statement("ALTER TABLE vehicles DROP COLUMN `{$col}`");
            }
        }

        // --- payment_status: TINYINT → ENUM('paid','unpaid') ---
        DB::statement("ALTER TABLE vehicles ADD COLUMN payment_status_old ENUM('paid', 'unpaid') DEFAULT NULL AFTER payment_status");
        $this->reverseColumn('payment_status', 'payment_status_old', [1 => 'paid', 0 => 'unpaid']);
        DB::statement("ALTER TABLE vehicles DROP COLUMN payment_status");
        DB::statement("ALTER TABLE vehicles CHANGE COLUMN payment_status_old payment_status ENUM('paid', 'unpaid') DEFAULT NULL");

        // --- status: TINYINT → ENUM('active','inactive','sold') ---
        DB::statement("ALTER TABLE vehicles ADD COLUMN status_old ENUM('active', 'inactive', 'sold') NOT NULL DEFAULT 'active' AFTER status");
        $this->reverseColumn('status', 'status_old', [0 => 'active', 1 => 'inactive', 2 => 'sold']);
        DB::statement("ALTER TABLE vehicles DROP COLUMN status");
        DB::statement("ALTER TABLE vehicles CHANGE COLUMN status_old status ENUM('active', 'inactive', 'sold') NOT NULL DEFAULT 'active'");

        // --- insurance_status: TINYINT → VARCHAR ---
        DB::statement("ALTER TABLE vehicles ADD COLUMN insurance_status_old VARCHAR(255) DEFAULT NULL AFTER insurance_status");
        $this->reverseColumn('insurance_status', 'insurance_status_old', [1 => 'Active', 0 => 'Expired']);
        DB::statement("ALTER TABLE vehicles DROP COLUMN insurance_status");
        DB::statement("ALTER TABLE vehicles CHANGE COLUMN insurance_status_old insurance_status VARCHAR(255) DEFAULT NULL");

        // --- puc_status: TINYINT → VARCHAR ---
        DB::statement("ALTER TABLE vehicles ADD COLUMN puc_status_old VARCHAR(255) DEFAULT NULL AFTER puc_status");
        $this->reverseColumn('puc_status', 'puc_status_old', [1 => 'Valid', 0 => 'Expired']);
        DB::statement("ALTER TABLE vehicles DROP COLUMN puc_status");
        DB::statement("ALTER TABLE vehicles CHANGE COLUMN puc_status_old puc_status VARCHAR(255) DEFAULT NULL");
    }

    /**
     * Reverse data from integer column back to string column using Eloquent.
     */
    private function reverseColumn(string $oldCol, string $newCol, array $mapping): void
    {
        $rows = DB::table('vehicles')->select('id', $oldCol)->get();

        foreach ($rows as $row) {
            $value = $row->$oldCol;
            $newValue = $value !== null && isset($mapping[(int) $value]) ? $mapping[(int) $value] : null;
            DB::table('vehicles')->where('id', $row->id)->update([$newCol => $newValue]);
        }
    }
};