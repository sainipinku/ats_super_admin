<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('job_applications')->where('status', 'pending')->update(['status' => 'applied']);
        DB::table('job_applications')->where('status', 'assigned_to_calling_team')->update(['status' => 'assigned_to_calling_member']);

        DB::table('job_applications')
            ->whereIn('status', ['interested', 'interview_scheduled', 'follow_up', 'no_response'])
            ->update(['status' => 'calling_in_progress']);

        DB::table('job_applications')
            ->where('status', 'approved')
            ->whereNotNull('hiring_decision')
            ->update(['status' => 'calling_approved']);

        DB::table('job_applications')
            ->where('status', 'rejected')
            ->where('hiring_decision', 'rejected')
            ->update(['status' => 'calling_rejected']);

        DB::table('job_applications')
            ->whereNotNull('admin_final_decision')
            ->whereNull('offer_letter_triggered_at')
            ->update(['status' => 'admin_review']);

        DB::table('job_applications')
            ->whereNotNull('offer_letter_triggered_at')
            ->update(['status' => 'offer_letter_generated']);
    }

    public function down(): void
    {
        DB::table('job_applications')->where('status', 'applied')->update(['status' => 'pending']);
        DB::table('job_applications')->where('status', 'viewed')->update(['status' => 'pending']);
        DB::table('job_applications')->where('status', 'assigned_to_calling_member')->update(['status' => 'assigned_to_calling_team']);
        DB::table('job_applications')->where('status', 'calling_in_progress')->update(['status' => 'interested']);
        DB::table('job_applications')->where('status', 'calling_approved')->update(['status' => 'approved']);
        DB::table('job_applications')->where('status', 'calling_rejected')->update(['status' => 'rejected']);
        DB::table('job_applications')->where('status', 'admin_review')->update(['status' => 'approved']);
        DB::table('job_applications')->where('status', 'offer_letter_generated')->update(['status' => 'approved']);
    }
};
