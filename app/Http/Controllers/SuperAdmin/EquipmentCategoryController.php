<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEquipmentCategoryRequest;
use App\Models\EquipmentCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EquipmentCategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = EquipmentCategory::query()
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($query) use ($request) {
                    $query->where('category_id', 'like', "%{$request->search}%")
                        ->orWhere('category_name', 'like', "%{$request->search}%")
                        ->orWhere('description', 'like', "%{$request->search}%");
                });
            })
            ->when($request->status, function ($q) use ($request) {
                $q->where('status', (int) $request->status);
            })
            ->when($request->sort, function ($q) use ($request) {
                switch ($request->sort) {
                    case 'newest':
                        $q->latest();
                        break;
                    case 'oldest':
                        $q->oldest();
                        break;
                    case 'name':
                        $q->orderBy('category_name');
                        break;
                    default:
                        $q->latest();
                }
            }, fn ($q) => $q->latest())
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

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
        $validated = $request->validated();

        EquipmentCategory::create($validated);

        return redirect()->back()->with('success', 'Equipment category created successfully!');
    }

    public function update(StoreEquipmentCategoryRequest $request, $id)
    {
        $category = EquipmentCategory::findOrFail($id);
        $category->update($request->validated());

        return redirect()->back()->with('success', 'Equipment category updated successfully!');
    }

    public function destroy($id)
    {
        $category = EquipmentCategory::findOrFail($id);

        if ($category->equipments()->exists()) {
            return redirect()->back()->with('error', 'Cannot delete category because it has equipments assigned to it.');
        }

        $category->delete();

        return redirect()->back()->with('success', 'Equipment category deleted successfully!');
    }
}