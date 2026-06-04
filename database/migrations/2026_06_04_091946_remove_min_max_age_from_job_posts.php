<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_posts', function (Blueprint $table) {
            if (Schema::hasColumn('job_posts', 'min_age')) {
                $table->dropColumn('min_age');
            }
            if (Schema::hasColumn('job_posts', 'max_age')) {
                $table->dropColumn('max_age');
            }
        });
    }

    public function down(): void
    {
        Schema::table('job_posts', function (Blueprint $table) {
            $table->unsignedTinyInteger('min_age')->nullable()->after('experience');
            $table->unsignedTinyInteger('max_age')->nullable()->after('min_age');
        });
    }
};