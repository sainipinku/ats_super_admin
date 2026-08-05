<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            $table->string('vehicle_id')->unique();

            // Basic Information
            $table->string('vehicle_type')->nullable();
            $table->string('vehicle_number')->unique();
            $table->string('vehicle_name')->nullable();
            $table->string('brand')->nullable();
            $table->string('fuel_type')->nullable();
            $table->string('color')->nullable();
            $table->string('manufacturing_year')->nullable();
            $table->string('engine_number')->nullable();
            $table->string('chassis_number')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_amount', 12, 2)->nullable();
            $table->string('current_km_reading')->nullable();
            $table->string('vehicle_image')->nullable();

            $table->tinyInteger('status')
                ->default(0)
                ->comment('0 = Active, 1 = Inactive, 2 = Sold');

            // Insurance Details
            $table->string('insurance_provider')->nullable();
            $table->string('policy_number')->nullable();
            $table->string('insurance_type')->nullable();
            $table->date('insurance_start_date')->nullable();
            $table->date('insurance_end_date')->nullable();
            $table->string('insurance_status')->nullable();

            // PUC Details
            $table->string('puc_certificate_number')->nullable();
            $table->date('puc_issue_date')->nullable();
            $table->date('puc_expiry_date')->nullable();
            $table->string('puc_status')->nullable();

            // Challan Details
            $table->string('challan_number')->nullable();
            $table->date('challan_date')->nullable();
            $table->string('violation_type')->nullable();
            $table->decimal('fine_amount', 10, 2)->nullable();

            $table->tinyInteger('payment_status')
                ->nullable()
                ->comment('0 = Unpaid, 1 = Paid');

            $table->timestamps();

            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};