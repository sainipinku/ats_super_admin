<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('approved_by')
                ->nullable()
                ->after('created_by')
                ->constrained('super_admins')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('approval_notes')->nullable()->after('approved_at');

            $table->string('company_name')->nullable()->after('email');
            $table->string('state')->nullable()->after('company_name');
            $table->string('city')->nullable()->after('state');

            $table->boolean('terms_agreed')->default(false)->after('password');
            $table->timestamp('terms_agreed_at')->nullable()->after('terms_agreed');

            $table->index('status', 'members_status_idx');
            $table->index(['status', 'approved_at'], 'members_pending_approval_idx');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex('members_pending_approval_idx');
            $table->dropIndex('members_status_idx');

            $table->dropColumn([
                'approved_by',
                'approved_at',
                'approval_notes',
                'company_name',
                'state',
                'city',
                'terms_agreed',
                'terms_agreed_at',
            ]);
        });
    }
};
