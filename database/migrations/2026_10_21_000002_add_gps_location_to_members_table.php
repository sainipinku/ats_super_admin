<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('members', 'latitude')) {
            Schema::table('members', function (Blueprint $table) {
                $table->decimal('latitude', 10, 7)->nullable();
            });
        }

        if (!Schema::hasColumn('members', 'longitude')) {
            Schema::table('members', function (Blueprint $table) {
                $table->decimal('longitude', 11, 7)->nullable();
            });
        }

        if (!Schema::hasColumn('members', 'location_captured_at')) {
            Schema::table('members', function (Blueprint $table) {
                $table->timestamp('location_captured_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        $columns = array_values(array_filter(
            ['latitude', 'longitude', 'location_captured_at'],
            fn (string $column) => Schema::hasColumn('members', $column)
        ));

        if ($columns) {
            Schema::table('members', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
