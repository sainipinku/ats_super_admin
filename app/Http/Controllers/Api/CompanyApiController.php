<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Construction\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CompanyApiController extends Controller
{
    public function index(Request $request)
    {
        $query = Company::withCount(['clients', 'projects']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('legal_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('gst_number', 'like', "%{$search}%");
            });
        }

        $perPage = $request->per_page ?? 15;
        $companies = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $companies->items(),
            'pagination' => [
                'total' => $companies->total(),
                'per_page' => $companies->perPage(),
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
            ],
        ]);
    }

    public function show(Company $company)
    {
        $company->load(['clients', 'projects', 'roles']);

        return response()->json([
            'success' => true,
            'data' => $company,
        ]);
    }

    public function store(Request $request)
    {
        $actor = $request->user('superadmin-api');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:construction_companies,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:construction_companies,phone'],
            'gst_number' => ['nullable', 'string', 'max:50', 'unique:construction_companies,gst_number'],
            'address' => ['nullable', 'string'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:5120'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('companies/logos', 'public');
            $validated['logo_path'] = $path;
        }

        $company = Company::create([
            ...$validated,
            'status' => $validated['status'] ?? 'active',
            'created_by_type' => $actor ? $actor::class : null,
            'created_by_id' => $actor?->getKey(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Company created successfully.',
            'data' => $company,
        ], 201);
    }

    public function update(Request $request, Company $company)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'legal_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:construction_companies,email,' . $company->id],
            'phone' => ['sometimes', 'string', 'max:20', 'unique:construction_companies,phone,' . $company->id],
            'gst_number' => ['sometimes', 'nullable', 'string', 'max:50', 'unique:construction_companies,gst_number,' . $company->id],
            'address' => ['sometimes', 'nullable', 'string'],
            'logo' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:5120'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        if ($request->hasFile('logo')) {
            if ($company->logo_path && ! filter_var($company->logo_path, FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete($company->logo_path);
            }

            $path = $request->file('logo')->store('companies/logos', 'public');
            $validated['logo_path'] = $path;
        } elseif ($request->has('logo') && $request->input('logo') === null && $company->logo_path) {
            if (! filter_var($company->logo_path, FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $validated['logo_path'] = null;
        }

        $company->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Company updated successfully.',
            'data' => $company->fresh(),
        ]);
    }

    public function destroy(Company $company)
    {
        $projectCount = $company->projects()->count();
        $clientCount = $company->clients()->count();

        if ($projectCount > 0 || $clientCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete company. It has {$projectCount} projects and {$clientCount} clients linked.",
            ], 422);
        }

        if ($company->logo_path && ! filter_var($company->logo_path, FILTER_VALIDATE_URL)) {
            Storage::disk('public')->delete($company->logo_path);
        }

        $company->delete();

        return response()->json([
            'success' => true,
            'message' => 'Company deleted successfully.',
        ]);
    }

    public function all()
    {
        $companies = Company::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'legal_name', 'email', 'phone']);

        return response()->json([
            'success' => true,
            'data' => $companies,
        ]);
    }
}
