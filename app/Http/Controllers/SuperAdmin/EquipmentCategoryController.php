<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEquipmentCategoryRequest;
use App\Models\EquipmentCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class EquipmentCategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = EquipmentCategory::query()
            ->when($request->search, fn($q) => $q->where(function ($query) use ($request) {
                $query->where('category_id', 'like', "%{$request->search}%")
                    ->orWhere('category_name', 'like', "%{$request->search}%")
                    ->orWhere('description', 'like', "%{$request->search}%");
            }))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->sort, function ($q) use ($request) {
                switch ($request->sort) {
                    case 'newest': $q->orderBy('id', 'desc'); break;
                    case 'oldest': $q->orderBy('id', 'asc'); break;
                    case 'name': $q->orderBy('category_name', 'asc'); break;
                    default: $q->orderBy('id', 'desc');
                }
            }, fn($q) => $q->orderBy('id', 'desc'))
            ->paginate($request->per_page ?? 10);

        return Inertia::render('SuperAdmin/EquipmentCategories/List', [
            'categories' => $categories,
            'filters' => (object) $request->only(['search', 'status', 'sort', 'per_page']),
        ]);
    }

    public function show($id)
    {
        $category = EquipmentCategory::withCount('equipments')->findOrFail($id);
        return response()->json([
            'success' => true,
            'category' => $category,
        ]);
    }

    public function store(StoreEquipmentCategoryRequest $request)
    {
        try {
            $validated = $request->validated();

            DB::transaction(function () use ($validated) {
                EquipmentCategory::create($validated);
            });

            return redirect()->back()->with('success', 'Equipment category created successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors())->with('error', 'Please fix the highlighted errors.');
        } catch (\Exception $e) {
            Log::error('Equipment category creation failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Failed to create equipment category: ' . $e->getMessage());
        }
    }

    public function update(StoreEquipmentCategoryRequest $request, $id)
    {
        try {
            $category = EquipmentCategory::findOrFail($id);
            $validated = $request->validated();

            DB::transaction(function () use ($validated, $category) {
                $category->update($validated);
            });

            return redirect()->back()->with('success', 'Equipment category updated successfully!');
        } catch (\Exception $e) {
            Log::error('Equipment category update failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to update equipment category: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $category = EquipmentCategory::findOrFail($id);

            DB::transaction(function () use ($category) {
                $category->delete();
            });

            return redirect()->back()->with('success', 'Equipment category deleted successfully!');
        } catch (\Exception $e) {
            Log::error('Equipment category deletion failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to delete equipment category.');
        }
    }
}