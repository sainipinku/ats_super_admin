<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVehicleRequest;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $vehicles = Vehicle::query()
            ->when($request->search, fn($q) => $q->where(function ($query) use ($request) {
                $query->where('vehicle_number', 'like', "%{$request->search}%")
                    ->orWhere('vehicle_name', 'like', "%{$request->search}%")
                    ->orWhere('brand', 'like', "%{$request->search}%")
                    ->orWhere('vehicle_type', 'like', "%{$request->search}%");
            }))
            ->when($request->vehicle_type, fn($q) => $q->where('vehicle_type', $request->vehicle_type))
            ->when($request->fuel_type, fn($q) => $q->where('fuel_type', $request->fuel_type))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->insurance_status, fn($q) => $q->where('insurance_status', $request->insurance_status))
            ->when($request->sort, function ($q) use ($request) {
                switch ($request->sort) {
                    case 'newest': $q->newestFirst(); break;
                    case 'oldest': $q->oldestFirst(); break;
                    case 'vehicle_number': $q->sortByVehicleNumber(); break;
                    default: $q->oldestFirst();
                }
            }, fn($q) => $q->oldestFirst())
            ->paginate($request->per_page ?? 10);

        return Inertia::render('SuperAdmin/Vehicles/List', [
            'vehicles' => $vehicles,
            'filters' => (object) $request->only(['search', 'vehicle_type', 'fuel_type', 'status', 'insurance_status', 'sort', 'per_page']),
        ]);
    }

    public function show($uuid)
    {
        $vehicle = Vehicle::where('uuid', $uuid)->firstOrFail();
        return response()->json([
            'success' => true,
            'vehicle' => $vehicle,
        ]);
    }

    public function store(StoreVehicleRequest $request)
    {
        try {
            $validated = $request->validated();

            DB::transaction(function () use ($validated, $request) {
                $vehicleData = [
                    'vehicle_type' => $validated['vehicle_type'],
                    'vehicle_number' => $validated['vehicle_number'],
                    'vehicle_name' => $validated['vehicle_name'] ?? null,
                    'brand' => $validated['brand'] ?? null,
                    'fuel_type' => $validated['fuel_type'],
                    'color' => $validated['color'] ?? null,
                    'manufacturing_year' => $validated['manufacturing_year'] ?? null,
                    'engine_number' => $validated['engine_number'] ?? null,
                    'chassis_number' => $validated['chassis_number'] ?? null,
                    'purchase_date' => $validated['purchase_date'] ?? null,
                    'purchase_amount' => $validated['purchase_amount'] ?? null,
                    'current_km_reading' => $validated['current_km_reading'] ?? null,
                    'status' => $validated['status'],
                    'insurance_provider' => $validated['insurance_provider'] ?? null,
                    'policy_number' => $validated['policy_number'] ?? null,
                    'insurance_type' => $validated['insurance_type'] ?? null,
                    'insurance_start_date' => $validated['insurance_start_date'] ?? null,
                    'insurance_end_date' => $validated['insurance_end_date'] ?? null,
                    'puc_certificate_number' => $validated['puc_certificate_number'] ?? null,
                    'puc_issue_date' => $validated['puc_issue_date'] ?? null,
                    'puc_expiry_date' => $validated['puc_expiry_date'] ?? null,
                    'challan_number' => $validated['challan_number'] ?? null,
                    'challan_date' => $validated['challan_date'] ?? null,
                    'violation_type' => $validated['violation_type'] ?? null,
                    'fine_amount' => $validated['fine_amount'] ?? null,
                    'payment_status' => $validated['payment_status'] ?? null,
                ];

                if ($request->hasFile('vehicle_image')) {
                    $vehicleData['vehicle_image'] = $request->file('vehicle_image')->store('vehicles', 'public');
                }

                Vehicle::create($vehicleData);
            });

            return redirect()->back()->with('success', 'Vehicle added successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors())->with('error', 'Please fix the highlighted errors.');
        } catch (\Exception $e) {
            Log::error('Vehicle creation failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Failed to create vehicle: ' . $e->getMessage());
        }
    }

    public function update(StoreVehicleRequest $request, $uuid)
    {
        try {
            $vehicle = Vehicle::where('uuid', $uuid)->firstOrFail();
            $validated = $request->validated();

            DB::transaction(function () use ($validated, $request, $vehicle) {
                $vehicleData = [
                    'vehicle_type' => $validated['vehicle_type'],
                    'vehicle_number' => $validated['vehicle_number'],
                    'vehicle_name' => $validated['vehicle_name'] ?? null,
                    'brand' => $validated['brand'] ?? null,
                    'fuel_type' => $validated['fuel_type'],
                    'color' => $validated['color'] ?? null,
                    'manufacturing_year' => $validated['manufacturing_year'] ?? null,
                    'engine_number' => $validated['engine_number'] ?? null,
                    'chassis_number' => $validated['chassis_number'] ?? null,
                    'purchase_date' => $validated['purchase_date'] ?? null,
                    'purchase_amount' => $validated['purchase_amount'] ?? null,
                    'current_km_reading' => $validated['current_km_reading'] ?? null,
                    'status' => $validated['status'],
                    'insurance_provider' => $validated['insurance_provider'] ?? null,
                    'policy_number' => $validated['policy_number'] ?? null,
                    'insurance_type' => $validated['insurance_type'] ?? null,
                    'insurance_start_date' => $validated['insurance_start_date'] ?? null,
                    'insurance_end_date' => $validated['insurance_end_date'] ?? null,
                    'puc_certificate_number' => $validated['puc_certificate_number'] ?? null,
                    'puc_issue_date' => $validated['puc_issue_date'] ?? null,
                    'puc_expiry_date' => $validated['puc_expiry_date'] ?? null,
                    'challan_number' => $validated['challan_number'] ?? null,
                    'challan_date' => $validated['challan_date'] ?? null,
                    'violation_type' => $validated['violation_type'] ?? null,
                    'fine_amount' => $validated['fine_amount'] ?? null,
                    'payment_status' => $validated['payment_status'] ?? null,
                ];

                if ($request->hasFile('vehicle_image')) {
                    if ($vehicle->vehicle_image && Storage::disk('public')->exists($vehicle->vehicle_image)) {
                        Storage::disk('public')->delete($vehicle->vehicle_image);
                    }
                    $vehicleData['vehicle_image'] = $request->file('vehicle_image')->store('vehicles', 'public');
                }

                $vehicle->update($vehicleData);
            });

            return redirect()->back()->with('success', 'Vehicle updated successfully!');
        } catch (\Exception $e) {
            Log::error('Vehicle update failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to update vehicle: ' . $e->getMessage());
        }
    }

    public function destroy($uuid)
    {
        try {
            $vehicle = Vehicle::where('uuid', $uuid)->firstOrFail();

            DB::transaction(function () use ($vehicle) {
                if ($vehicle->vehicle_image && Storage::disk('public')->exists($vehicle->vehicle_image)) {
                    Storage::disk('public')->delete($vehicle->vehicle_image);
                }
                $vehicle->delete();
            });

            return redirect()->back()->with('success', 'Vehicle deleted successfully!');
        } catch (\Exception $e) {
            Log::error('Vehicle deletion failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to delete vehicle.');
        }
    }
}