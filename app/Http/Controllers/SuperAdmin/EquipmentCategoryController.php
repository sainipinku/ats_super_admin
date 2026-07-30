<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEquipmentCategoryRequest;
use App\Models\EquipmentCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

class EquipmentCategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = EquipmentCategory::when($request->filled('search'), function ($q) use ($request) {
                $q->where(function ($query) use ($request) {
                    $query->where('category_id', 'like', '%' . $request->search . '%')
                        ->orWhere('category_name', 'like', '%' . $request->search . '%')
                        ->orWhere('description', 'like', '%' . $request->search . '%');
                });
            })
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('status', (int) $request->status);
            })
            ->when($request->filled('sort'), function ($q) use ($request) {
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
                        break;
                }
            }, function ($q) {
                $q->latest();
            })
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('SuperAdmin/EquipmentCategories/List', [
            'categories' => $categories,
            'filters' => (object) $request->only([
                'search',
                'status',
                'sort',
                'per_page',
            ]),
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
            EquipmentCategory::create($request->validated());

            return redirect()
                ->route('super.equipment.categories.list')
                ->with('success', 'Equipment category created successfully.');
        } catch (Throwable $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Unable to create equipment category.');
        }
    }

    public function update(StoreEquipmentCategoryRequest $request, $id)
    {
        try {
            $category = EquipmentCategory::findOrFail($id);

            $category->update($request->validated());

            return redirect()
                ->route('super.equipment.categories.list')
                ->with('success', 'Equipment category updated successfully.');
        } catch (Throwable $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Unable to update equipment category.');
        }
    }

    public function destroy($id)
    {
        try {
            $category = EquipmentCategory::findOrFail($id);

            if ($category->equipments()->exists()) {
                return redirect()
                    ->back()
                    ->with('error', 'Cannot delete category because it has equipments assigned to it.');
            }

            $category->delete();

            return redirect()
                ->back()
                ->with('success', 'Equipment category deleted successfully.');
        } catch (Throwable $e) {
            return redirect()
                ->back()
                ->with('error', 'Unable to delete equipment category.');
        }
    }
}