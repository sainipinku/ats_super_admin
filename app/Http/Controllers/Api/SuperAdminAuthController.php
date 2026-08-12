<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class SuperAdminAuthController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $identifier = $validated['identifier'];

        $superAdmin = SuperAdmin::query()
            ->where(function ($q) use ($identifier) {
                $q->where('email', $identifier)
                    ->orWhere('phone', $identifier)
                    ->orWhere('username', $identifier);
            })
            ->first();

        if (! $superAdmin || $superAdmin->status != 1) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 422);
        }

        if (! Hash::check($validated['password'], $superAdmin->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 422);
        }

        $tokenName = $validated['device_name'] ?? ($request->userAgent() ?: 'api-superadmin');
        $token = $superAdmin->createToken($tokenName)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Logged in successfully.',
            'token' => $token,
            'token_type' => 'Bearer',
            'super_admin' => $superAdmin,
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    public function profile(Request $request)
    {
        $superAdmin = $request->user();

        if (! $superAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => $superAdmin,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $superAdmin = $request->user();

        if (! $superAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:super_admins,email,' . $superAdmin->id],
            'phone' => ['sometimes', 'string', 'max:20', 'unique:super_admins,phone,' . $superAdmin->id],
            'whatsapp_phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'profile_image' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:5120'],
        ]);

        if ($request->hasFile('profile_image')) {
            if ($superAdmin->profile_image && ! filter_var($superAdmin->profile_image, FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete($superAdmin->profile_image);
            }

            $path = $request->file('profile_image')->store('super_admin/profiles', 'public');
            $validated['profile_image'] = $path;
        } elseif ($request->has('profile_image') && $request->input('profile_image') === null && $superAdmin->profile_image) {
            if (! filter_var($superAdmin->profile_image, FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete($superAdmin->profile_image);
            }
            $validated['profile_image'] = null;
        }

        $superAdmin->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => $superAdmin->fresh(),
        ]);
    }

    public function changePassword(Request $request)
    {
        $superAdmin = $request->user();

        if (! $superAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        if (! Hash::check($validated['current_password'], $superAdmin->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $superAdmin->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }
}
