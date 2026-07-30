<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   
    public function up(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->dropForeign(['category_id']);

            $table->foreign('category_id')
                ->references('id')
                ->on('equipment_categories')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->dropForeign(['category_id']);

            $table->foreign('category_id')
                ->references('id')
                ->on('equipment_categories')
                ->cascadeOnDelete();
        });
    }
};