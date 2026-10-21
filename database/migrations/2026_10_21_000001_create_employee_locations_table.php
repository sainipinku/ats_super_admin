<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 11, 7);
            $table->decimal('accuracy', 10, 2)->nullable();
            $table->timestamp('captured_at')->index();
            $table->timestamps();
            $table->index(['member_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_locations');
    }
};
