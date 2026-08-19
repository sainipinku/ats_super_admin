<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientApiController extends Controller
{
    public function index(Request $request)
    {
        $query = Client::with(['company'])->withCount('projects');

        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('client_type')) {
            $query->where('client_type', $request->client_type);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('client_code', 'like', "%{$search}%")
                    ->orWhere('gst_number', 'like', "%{$search}%");
            });
        }

        $perPage = $request->per_page ?? 15;
        $clients = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $clients->items(),
            'pagination' => [
                'total' => $clients->total(),
                'per_page' => $clients->perPage(),
                'current_page' => $clients->currentPage(),
                'last_page' => $clients->lastPage(),
            ],
        ]);
    }

    public function show(Client $client)
    {
        $client->load(['company', 'projects']);

        return response()->json([
            'success' => true,
            'data' => $client,
        ]);
    }

    public function store(Request $request)
    {
        $actor = $request->user();

        $validated = $request->validate([
            'company_id' => ['required', 'exists:construction_companies,id'],
            'client_type' => ['required', 'in:individual,company,government,ngo'],
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:construction_clients,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:construction_clients,phone'],
            'alternate_phone' => ['nullable', 'string', 'max:20'],
            'gst_number' => ['nullable', 'string', 'max:50', 'unique:construction_clients,gst_number'],
            'billing_address' => ['nullable', 'string'],
            'site_address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        $nextId = (Client::max('id') ?? 0) + 1;

        $client = Client::create([
            ...$validated,
            'client_code' => 'CLT-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT),
            'status' => $validated['status'] ?? 'active',
            'created_by_type' => $actor ? $actor::class : null,
            'created_by_id' => $actor?->getKey(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Client created successfully.',
            'data' => $client->load('company'),
        ], 201);
    }

    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'company_id' => ['sometimes', 'exists:construction_companies,id'],
            'client_type' => ['sometimes', 'in:individual,company,government,ngo'],
            'name' => ['sometimes', 'string', 'max:255'],
            'contact_person' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', 'unique:construction_clients,email,' . $client->id],
            'phone' => ['sometimes', 'string', 'max:20', 'unique:construction_clients,phone,' . $client->id],
            'alternate_phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'gst_number' => ['sometimes', 'nullable', 'string', 'max:50', 'unique:construction_clients,gst_number,' . $client->id],
            'billing_address' => ['sometimes', 'nullable', 'string'],
            'site_address' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        $client->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Client updated successfully.',
            'data' => $client->load('company')->fresh(),
        ]);
    }

    public function destroy(Client $client)
    {
        $projectCount = $client->projects()->count();

        if ($projectCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete client. It has {$projectCount} projects linked.",
            ], 422);
        }

        $client->delete();

        return response()->json([
            'success' => true,
            'message' => 'Client deleted successfully.',
        ]);
    }

    public function all(Request $request)
    {
        $query = Client::where('status', 'active');

        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        $clients = $query->orderBy('name')
            ->get(['id', 'company_id', 'name', 'contact_person', 'email', 'phone', 'client_type']);

        return response()->json([
            'success' => true,
            'data' => $clients,
        ]);
    }
}
