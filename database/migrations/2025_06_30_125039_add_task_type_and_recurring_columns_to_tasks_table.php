<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->text('task_type')->default('one_time')->after('description');
            $table->text('recurring_type')->nullable()->after('task_type');
            $table->json('recurring_days')->nullable()->after('recurring_type');
            $table->text('start_from')->nullable()->after('recurring_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
         $table->dropColumn(['task_type', 'recurring_type', 'recurring_days', 'start_from']);
        });
    }
};
