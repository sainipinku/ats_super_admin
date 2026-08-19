<?php

namespace App\Http\Controllers\Api\Construction;

use App\Http\Controllers\Controller;
use App\Models\Construction\SurveyPlan;
use App\Models\Construction\SurveyPlanMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SurveyTeamController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request, $projectId)
    {
        $rows = DB::table('construction_survey_teams')
            ->where('project_id', (int) $projectId)
            ->whereNull('deleted_at')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderBy('team_number')
            ->get();

        $rows->transform(function ($t) use ($projectId) {
            $t->member_count = DB::table('construction_survey_plan_members')
                ->where('survey_team_id', $t->id)
                ->count();
            $t->work_type_A_count = DB::table('construction_survey_plan_members')
                ->where('survey_team_id', $t->id)->where('work_type', 'A')->count();
            $t->work_type_B_count = DB::table('construction_survey_plan_members')
                ->where('survey_team_id', $t->id)->where('work_type', 'B')->count();
            return $t;
        });

        return response()->json([
            'success' => true,
            'project_id' => (int) $projectId,
            'teams'   => $rows,
        ]);
    }

    public function store(Request $request, $projectId)
    {
        $validator = Validator::make($request->all(), [
            'team_number'        => ['required', 'integer', 'between:1,10'],
            'team_name'          => ['required', 'string', 'max:100'],
            'description'        => ['nullable', 'string', 'max:500'],
            'supervisor_member_id' => ['nullable', 'integer', 'exists:members,id'],
            'status'             => ['nullable', 'in:active,inactive'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $exists = DB::table('construction_survey_teams')
            ->where('project_id', (int) $projectId)
            ->where('team_number', (int) $request->team_number)
            ->whereNull('deleted_at')
            ->exists();
        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Team number already exists on this project.',
            ], 422);
        }

        $actor = $request->user();
        $teamId = DB::table('construction_survey_teams')->insertGetId([
            'project_id'          => (int) $projectId,
            'team_number'         => (int) $request->team_number,
            'team_name'           => $request->team_name,
            'description'         => $request->description ?? null,
            'supervisor_member_id'=> $request->supervisor_member_id ?? null,
            'status'              => $request->status ?? 'active',
            'created_by_type'     => $actor ? get_class($actor) : null,
            'created_by_id'       => $actor?->id,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Survey team created (Team ' . (int) $request->team_number . ').',
            'team_id' => $teamId,
        ], 201);
    }

    public function show(Request $request, $projectId, $teamId)
    {
        $team = DB::table('construction_survey_teams')
            ->where('project_id', (int) $projectId)
            ->where('id', (int) $teamId)
            ->whereNull('deleted_at')
            ->first();

        if (! $team) {
            return response()->json([
                'success' => false,
                'message' => 'Survey team not found.',
            ], 404);
        }

        $members = DB::table('construction_survey_plan_members AS spm')
            ->leftJoin('members AS m', 'm.id', '=', 'spm.member_id')
            ->where('spm.survey_team_id', (int) $teamId)
            ->select([
                'spm.id', 'spm.survey_plan_id', 'spm.role_in_survey', 'spm.work_type',
                'm.id AS member_id', 'm.name', 'm.phone', 'm.email', 'm.profile_photo_url',
            ])
            ->get();

        return response()->json([
            'success' => true,
            'team'    => $team,
            'members' => $members,
        ]);
    }

    public function addMember(Request $request, $projectId, $teamId)
    {
        $validated = $request->validate([
            'survey_plan_id' => ['required', 'integer', 'exists:construction_survey_plans,id'],
            'member_id'      => ['required', 'integer', 'exists:members,id'],
            'role_in_survey' => ['nullable', 'string', 'max:100'],
            'work_type'      => ['required', 'in:A,B'],
        ]);

        $team = DB::table('construction_survey_teams')
            ->where('id', (int) $teamId)
            ->where('project_id', (int) $projectId)
            ->whereNull('deleted_at')
            ->first();
        if (! $team) {
            return response()->json([
                'success' => false,
                'message' => 'Survey team not found.',
            ], 404);
        }

        $workTypeLabel = $validated['work_type'] === 'A' ? 'Maint. Machine Operator' : 'Danda Pakden / Physical Marking';
        $row = SurveyPlanMember::firstOrCreate(
            [
                'survey_plan_id' => (int) $validated['survey_plan_id'],
                'member_id'      => (int) $validated['member_id'],
            ],
            [
                'survey_team_id' => (int) $teamId,
                'role_in_survey' => $validated['role_in_survey'] ?? $workTypeLabel,
                'work_type'      => $validated['work_type'],
                'status'         => 'assigned',
            ]
        );
        $row->update([
            'survey_team_id' => (int) $teamId,
            'work_type'      => $validated['work_type'],
            'role_in_survey' => $validated['role_in_survey'] ?? $row->role_in_survey,
        ]);

        return response()->json([
            'success'         => true,
            'message'         => 'Member added to Survey Team ' . $team->team_number . ' as Work Type ' . $validated['work_type'] . ' (' . $workTypeLabel . ')',
            'plan_member_id'  => $row->id,
            'work_type_label' => $workTypeLabel,
        ]);
    }

    public function removeMember(Request $request, $projectId, $teamId, $planMemberId)
    {
        $row = DB::table('construction_survey_plan_members')
            ->where('id', (int) $planMemberId)
            ->where('survey_team_id', (int) $teamId)
            ->first();
        if (! $row) {
            return response()->json([
                'success' => false,
                'message' => 'Plan member not found in team.',
            ], 404);
        }

        DB::table('construction_survey_plan_members')
            ->where('id', (int) $planMemberId)
            ->update([
                'survey_team_id' => null,
                'work_type'      => null,
                'updated_at'     => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Member removed from survey team (retains plan assignment).',
        ]);
    }

    public function updateWorkType(Request $request, $projectId, $teamId, $planMemberId)
    {
        $validated = $request->validate([
            'work_type' => ['required', 'in:A,B'],
        ]);

        DB::table('construction_survey_plan_members')
            ->where('id', (int) $planMemberId)
            ->where('survey_team_id', (int) $teamId)
            ->update([
                'work_type'  => $validated['work_type'],
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Work type updated to ' . ($validated['work_type'] === 'A' ? 'Maint. Machine' : 'Danda Pakden / Physical Marking') . '.',
        ]);
    }

    public function destroy(Request $request, $projectId, $teamId)
    {
        $team = DB::table('construction_survey_teams')
            ->where('id', (int) $teamId)
            ->where('project_id', (int) $projectId)
            ->whereNull('deleted_at')
            ->first();
        if (! $team) {
            return response()->json([
                'success' => false,
                'message' => 'Survey team not found.',
            ], 404);
        }

        DB::table('construction_survey_plan_members')
            ->where('survey_team_id', (int) $teamId)
            ->update(['survey_team_id' => null, 'work_type' => null]);

        DB::table('construction_survey_teams')
            ->where('id', (int) $teamId)
            ->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Survey team soft-deleted.',
        ]);
    }
}
