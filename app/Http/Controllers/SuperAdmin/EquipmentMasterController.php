<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEquipmentRequest;
use App\Models\Construction\Project;
use App\Models\Employee;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class EquipmentMasterController extends Controller
{
    public function index(Request $request)
    {
        $equipments = Equipment::query()
            ->with(['category:id,category_name,category_id', 'assignedEmployee', 'assignedProject:id,project_code,name'])
            ->when($request->search, fn($q) => $q->where(function ($query) use ($request) {
                $query->where('equipment_id', 'like', "%{$request->search}%")
                    ->orWhere('equipment_name', 'like', "%{$request->search}%")
                    ->orWhere('brand', 'like', "%{$request->search}%")
                    ->orWhere('model', 'like', "%{$request->search}%")
                    ->orWhere('serial_number', 'like', "%{$request->search}%")
                    ->orWhere('asset_tag', 'like', "%{$request->search}%");
            }))
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->sort, function ($q) use ($request) {
                switch ($request->sort) {
                    case 'newest': $q->newestFirst(); break;
                    case 'oldest': $q->oldestFirst(); break;
                    case 'name': $q->orderBy('equipment_name', 'asc'); break;
                    default: $q->newestFirst();
                }
            }, fn($q) => $q->newestFirst())
            ->paginate($request->per_page ?? 10);

        $categories = EquipmentCategory::where('status', 'active')->orderBy('category_name')->get(['id', 'category_id', 'category_name']);
        $employees = Employee::with('member:id,name,email,phone')->get(['id', 'employee_id'])->map(function ($emp) {
            $empName = $emp->member?->name;
            return [
                'id' => $emp->id,
                'employee_id' => $emp->employee_id,
                'name' => $empName ?? $emp->employee_id,
                'email' => $emp->member?->email ?? '',
                'display_name' => $empName ? $empName . ' (' . $emp->employee_id . ')' : $emp->employee_id,
            ];
        })->values();
        $projects = Project::orderBy('project_code')->get(['id', 'project_code', 'name']);

        return Inertia::render('SuperAdmin/Equipment/List', [
            'equipments' => $equipments,
            'categories' => $categories,
            'employees' => $employees,
            'projects' => $projects,
            'filters' => (object) $request->only(['search', 'category_id', 'status', 'sort', 'per_page']),
        ]);
    }

    public function show($id)
    {
        $equipment = Equipment::with(['category', 'assignedEmployee.member', 'assignedProject'])->findOrFail($id);
        return response()->json([
            'success' => true,
            'equipment' => $equipment,
        ]);
    }

    public function store(StoreEquipmentRequest $request)
    {
        try {
            $validated = $request->validated();

            DB::transaction(function () use ($validated, $request) {
                $equipmentData = [
                    'category_id' => $validated['category_id'],
                    'equipment_name' => $validated['equipment_name'],
                    'company' => $validated['company'] ?? null,
                    'brand' => $validated['brand'] ?? null,
                    'model' => $validated['model'] ?? null,
                    'serial_number' => $validated['serial_number'] ?? null,
                    'asset_tag' => $validated['asset_tag'] ?? null,
                    'purchase_date' => $validated['purchase_date'] ?? null,
                    'purchase_cost' => $validated['purchase_cost'] ?? null,
                    'vendor' => $validated['vendor'] ?? null,
                    'warranty_till' => $validated['warranty_till'] ?? null,
                    'status' => $validated['status'],
                    'assigned_employee_id' => $validated['assigned_employee_id'] ?? null,
                    'assigned_project_id' => $validated['assigned_project_id'] ?? null,
                    'assigned_date' => $validated['assigned_date'] ?? null,
                ];

                if ($request->hasFile('photo')) {
                    $equipmentData['photo'] = $request->file('photo')->store('equipments', 'public');
                }

                Equipment::create($equipmentData);
            });

            return redirect()->back()->with('success', 'Equipment added successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors())->with('error', 'Please fix the highlighted errors.');
        } catch (\Exception $e) {
            Log::error('Equipment creation failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Failed to create equipment: ' . $e->getMessage());
        }
    }

    public function update(StoreEquipmentRequest $request, $id)
    {
        try {
            $equipment = Equipment::findOrFail($id);
            $validated = $request->validated();

            DB::transaction(function () use ($validated, $request, $equipment) {
                $equipmentData = [
                    'category_id' => $validated['category_id'],
                    'equipment_name' => $validated['equipment_name'],
                    'company' => $validated['company'] ?? null,
                    'brand' => $validated['brand'] ?? null,
                    'model' => $validated['model'] ?? null,
                    'serial_number' => $validated['serial_number'] ?? null,
                    'asset_tag' => $validated['asset_tag'] ?? null,
                    'purchase_date' => $validated['purchase_date'] ?? null,
                    'purchase_cost' => $validated['purchase_cost'] ?? null,
                    'vendor' => $validated['vendor'] ?? null,
                    'warranty_till' => $validated['warranty_till'] ?? null,
                    'status' => $validated['status'],
                    'assigned_employee_id' => $validated['assigned_employee_id'] ?? null,
                    'assigned_project_id' => $validated['assigned_project_id'] ?? null,
                    'assigned_date' => $validated['assigned_date'] ?? null,
                ];

                if ($request->hasFile('photo')) {
                    if ($equipment->photo && Storage::disk('public')->exists($equipment->photo)) {
                        Storage::disk('public')->delete($equipment->photo);
                    }
                    $equipmentData['photo'] = $request->file('photo')->store('equipments', 'public');
                }

                $equipment->update($equipmentData);
            });

            return redirect()->back()->with('success', 'Equipment updated successfully!');
        } catch (\Exception $e) {
            Log::error('Equipment update failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to update equipment: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $equipment = Equipment::findOrFail($id);

            DB::transaction(function () use ($equipment) {
                if ($equipment->photo && Storage::disk('public')->exists($equipment->photo)) {
                    Storage::disk('public')->delete($equipment->photo);
                }
                $equipment->delete();
            });

            return redirect()->back()->with('success', 'Equipment deleted successfully!');
        } catch (\Exception $e) {
            Log::error('Equipment deletion failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to delete equipment.');
        }
    }
}