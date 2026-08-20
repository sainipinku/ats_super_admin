<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\SuperAdmin;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            if (!Schema::hasColumn('members', 'company_name')) {
                $table->string('company_name')->nullable()->after('email');
            }
            if (!Schema::hasColumn('members', 'state')) {
                $table->string('state')->nullable()->after('company_name');
            }
            if (!Schema::hasColumn('members', 'city')) {
                $table->string('city')->nullable()->after('state');
            }
            if (!Schema::hasColumn('members', 'registration_source')) {
                $table->enum('registration_source', ['web', 'mobile_api', 'admin_created'])
                    ->default('admin_created')
                    ->after('city');
            }
            if (!Schema::hasColumn('members', 'approved_by')) {
                $table->foreignIdFor(SuperAdmin::class, 'approved_by')
                    ->nullable()
                    ->constrained('super_admins')
                    ->nullOnDelete()
                    ->after('registration_source');
            }
            if (!Schema::hasColumn('members', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (!Schema::hasColumn('members', 'approval_remark')) {
                $table->text('approval_remark')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('members', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('approval_remark');
            }
        });

        DB::statement("
            ALTER TABLE members
            MODIFY COLUMN status TINYINT NOT NULL DEFAULT 0
            COMMENT '0 = Pending Approval, 1 = Active, 2 = Rejected'
        ");

        DB::table('members')
            ->where('status', '1')
            ->orWhere('status', 1)
            ->update(['status' => 1]);

        DB::table('members')
            ->whereNotIn('status', [0, 1, 2])
            ->update(['status' => 0]);
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'company_name',
                'state',
                'city',
                'registration_source',
                'approved_by',
                'approved_at',
                'approval_remark',
                'rejected_at',
            ]);
        });

        DB::statement("
            ALTER TABLE members
            MODIFY COLUMN status VARCHAR(255) NOT NULL DEFAULT '1'
        ");
    }
};
