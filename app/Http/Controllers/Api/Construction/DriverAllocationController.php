<?php

namespace App\Http\Controllers\Api\Construction;

use App\Http\Controllers\Controller;
use App\Models\Construction\VehicleAssignment;
use App\Models\Construction\SurveyTeamCheckpoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DriverAllocationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $perPage = (int) ($request->per_page ?? 20);
        $query = VehicleAssignment::query()
            ->with([
                'project:id,name,project_code',
                'vehicle:id,vehicle_code,registration_number,vehicle_type,make,model',
                'driver:id,name,phone,profile_photo_url',
            ]);

        $query->when($request->project_id, fn($q) => $q->where('project_id', (int) $request->project_id));
        $query->when($request->driver_member_id, fn($q) => $q->where('driver_member_id', (int) $request->driver_member_id));
        $query->when($request->assignment_type, fn($q) => $q->where('assignment_type', $request->assignment_type));
        $query->when($request->status, fn($q) => $q->where('status', $request->status));

        if ($request->filled('date_from') || $request->filled('date_to')) {
            $from = $request->date_from ? now()->parse($request->date_from)->startOfDay() : now()->startOfCentury();
            $to   = $request->date_to   ? now()->parse($request->date_to)->endOfDay()   : now()->endOfCentury();
            $query->where(function ($q) use ($from, $to) {
                $q->whereBetween('assigned_from', [$from, $to])
                  ->orWhereBetween('assigned_to', [$from, $to]);
            });
        }

        return response()->json([
            'success'       => true,
            'assignments'   => $query->latest('id')->paginate($perPage),
            'summary_count' => [
                'point_to_point'    => (clone $query)->where('assignment_type', 'point_to_point')->count(),
                'material_handling' => (clone $query)->where('assignment_type', 'material_handling')->count(),
                'multi_day'         => (clone $query)->where('assignment_type', 'multi_day')->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id'               => ['required', 'integer', 'exists:construction_projects,id'],
            'vehicle_id'               => ['required', 'integer', 'exists:construction_vehicles,id'],
            'driver_member_id'         => ['nullable', 'integer', 'exists:members,id'],
            'assignment_type'          => ['required', 'in:point_to_point,material_handling,multi_day'],
            'from_location'            => ['nullable', 'required_if:assignment_type,point_to_point', 'string', 'max:255'],
            'from_lat'                 => ['nullable', 'numeric', 'between:-90,90'],
            'from_lng'                 => ['nullable', 'numeric', 'between:-180,180'],
            'to_location'              => ['nullable', 'required_if:assignment_type,point_to_point', 'string', 'max:255'],
            'to_lat'                   => ['nullable', 'numeric', 'between:-90,90'],
            'to_lng'                   => ['nullable', 'numeric', 'between:-180,180'],
            'material_list'            => ['nullable', 'array'],
            'material_list.*.name'     => ['required_with:material_list', 'string'],
            'material_list.*.quantity' => ['required_with:material_list', 'numeric'],
            'material_list.*.unit'     => ['nullable', 'string'],
            'daily_checkpoint_required'=> ['nullable', 'boolean'],
            'assigned_from'            => ['required', 'date'],
            'assigned_to'              => ['required', 'date', 'after_or_equal:assigned_from'],
            'status'                   => ['nullable', 'in:active,completed,cancelled'],
            'notes'                    => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }
        $data = $validator->validate();

        if ($data['assignment_type'] === 'multi_day' && ! ($data['daily_checkpoint_required'] ?? false)) {
            $data['daily_checkpoint_required'] = true;
        }

        $data['material_list'] = ! empty($data['material_list']) ? json_encode($data['material_list']) : null;

        $actor = $request->user();
        $data['assigned_by_type'] = $actor ? get_class($actor) : null;
        $data['assigned_by_id']   = $actor?->id;
        $data['status']           = $data['status'] ?? 'active';

        $assignment = VehicleAssignment::create($data);
        $assignment->loadMissing([
            'project:id,name,project_code',
            'vehicle:id,vehicle_code,registration_number',
            'driver:id,name,phone',
        ]);

        return response()->json([
            'success'      => true,
            'message'      => 'Driver allocation created — type: ' . $data['assignment_type'],
            'assignment'   => $assignment,
            'enforce_gps'  => true,
            'gps_tolerance_meters' => 50,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $assignment = VehicleAssignment::query()
            ->with([
                'project',
                'vehicle',
                'driver:id,name,phone,profile_photo_url',
            ])
            ->find($id);

        if (! $assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle assignment not found.',
            ], 404);
        }

        $checkpoints = [];
        if ($assignment->assignment_type === 'multi_day') {
            $checkpoints = \DB::table('construction_survey_team_checkpoints')
                ->where('vehicle_assignment_id', $assignment->id)
                ->orderBy('checkpoint_date')
                ->get();
        }

        return response()->json([
            'success'        => true,
            'assignment'     => $assignment,
            'material_list'  => $assignment->material_list ? json_decode($assignment->material_list, true) : null,
            'daily_checkpoints' => $checkpoints,
        ]);
    }

    public function storeCheckpoint(Request $request, $assignmentId)
    {
        $validated = $request->validate([
            'checkpoint_date'    => ['required', 'date'],
            'logged_in_at'       => ['nullable', 'date'],
            'logged_out_at'      => ['nullable', 'date', 'after_or_equal:logged_in_at'],
            'login_lat'          => ['required_with:logged_in_at', 'numeric', 'between:-90,90'],
            'login_lng'          => ['required_with:logged_in_at', 'numeric', 'between:-180,180'],
            'logout_lat'         => ['nullable', 'numeric', 'between:-90,90'],
            'logout_lng'         => ['nullable', 'numeric', 'between:-180,180'],
            'gps_distance_meters'=> ['nullable', 'numeric'],
            'gps_verified'       => ['nullable', 'boolean'],
            'odometer_start_km'  => ['nullable', 'numeric'],
            'odometer_end_km'    => ['nullable', 'numeric', 'gte:odometer_start_km'],
            'checkpoint_notes'   => ['nullable', 'string', 'max:1000'],
        ]);

        $assignment = VehicleAssignment::find($assignmentId);
        if (! $assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle assignment not found.',
            ], 404);
        }

        $validated['vehicle_assignment_id'] = $assignmentId;
        $validated['driver_member_id']      = $assignment->driver_member_id;

        $checkpoint = \DB::table('construction_survey_team_checkpoints')
            ->updateOrCreate(
                ['vehicle_assignment_id' => $assignmentId, 'checkpoint_date' => $validated['checkpoint_date']],
                $validated
            );

        return response()->json([
            'success'    => true,
            'message'    => 'Driver daily checkpoint logged.',
            'checkpoint' => $checkpoint,
        ]);
    }

    public function driverCheckpoints(Request $request, $assignmentId)
    {
        $assignment = VehicleAssignment::find($assignmentId);
        if (! $assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle assignment not found.',
            ], 404);
        }

        $rows = \DB::table('construction_survey_team_checkpoints')
            ->where('vehicle_assignment_id', $assignmentId)
            ->orderBy('checkpoint_date')
            ->paginate($request->per_page ?? 31);

        return response()->json([
            'success'     => true,
            'assignment'  => $assignment->only(['id', 'assignment_type', 'assigned_from', 'assigned_to']),
            'checkpoints' => $rows,
        ]);
    }

    public function update(Request $request, $id)
    {
        $assignment = VehicleAssignment::find($id);
        if (! $assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found.',
            ], 404);
        }

        $validated = $request->validate([
            'driver_member_id'         => ['nullable', 'integer', 'exists:members,id'],
            'assignment_type'          => ['nullable', 'in:point_to_point,material_handling,multi_day'],
            'from_location'            => ['nullable', 'string', 'max:255'],
            'from_lat'                 => ['nullable', 'numeric'],
            'from_lng'                 => ['nullable', 'numeric'],
            'to_location'              => ['nullable', 'string', 'max:255'],
            'to_lat'                   => ['nullable', 'numeric'],
            'to_lng'                   => ['nullable', 'numeric'],
            'material_list'            => ['nullable', 'array'],
            'daily_checkpoint_required'=> ['nullable', 'boolean'],
            'assigned_from'            => ['nullable', 'date'],
            'assigned_to'              => ['nullable', 'date'],
            'status'                   => ['nullable', 'in:active,completed,cancelled'],
            'notes'                    => ['nullable', 'string'],
        ]);

        if (isset($validated['material_list'])) {
            $validated['material_list'] = json_encode($validated['material_list']);
        }

        $assignment->update($validated);

        return response()->json([
            'success'    => true,
            'message'    => 'Driver allocation updated.',
            'assignment' => $assignment->fresh(),
        ]);
    }
}
