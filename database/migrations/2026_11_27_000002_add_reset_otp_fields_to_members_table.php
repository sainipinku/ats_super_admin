<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('members')) {
            return;
        }

        Schema::table('members', function (Blueprint $table) {
            if (! Schema::hasColumn('members', 'reset_otp')) {
                $table->string('reset_otp', 10)->nullable()->after('reset_password_token_expires_at');
            }
            if (! Schema::hasColumn('members', 'reset_otp_expires_at')) {
                $table->timestamp('reset_otp_expires_at')->nullable()->after('reset_otp');
            }
            if (! Schema::hasColumn('members', 'reset_otp_verified_at')) {
                $table->timestamp('reset_otp_verified_at')->nullable()->after('reset_otp_expires_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('members')) {
            return;
        }

        Schema::table('members', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('members', 'reset_otp_verified_at') ? 'reset_otp_verified_at' : null,
                Schema::hasColumn('members', 'reset_otp_expires_at') ? 'reset_otp_expires_at' : null,
                Schema::hasColumn('members', 'reset_otp') ? 'reset_otp' : null,
            ]));

            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
