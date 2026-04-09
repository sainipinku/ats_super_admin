<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json([
            'success' => true,
            'member' => $request->user(),
        ]);
    }

    public function update(Request $request)
    {
        $member = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('members', 'email')->ignore($member->id),
            ],
            'dob' => ['sometimes', 'nullable', 'date'],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female', 'other'])],
            'image' => ['sometimes', 'nullable', 'file', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
        ]);

        if ($request->hasFile('image')) {
            $path = Storage::disk('public')->putFile('members', $request->file('image'));
            $validated['image'] = $path;
        }

        $member->fill($validated)->save();

        return response()->json([
            'success' => true,
            'member' => $member->fresh(),
        ]);
    }
}
