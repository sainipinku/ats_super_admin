<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            $table->foreignId('member_id')
                ->unique()
                ->constrained('members')
                ->cascadeOnDelete();

            $table->string('employee_id')->unique();

            $table->string('alternate_number')->nullable();

            $table->string('aadhaar_number')->nullable()->unique();

            $table->string('pan_number')->nullable()->unique();

            $table->timestamps();

            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};