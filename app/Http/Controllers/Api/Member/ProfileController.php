<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    private function getProfileCompletionData($member): array
    {
        $weights = [
            'name' => 15,
            'email' => 10,
            'phone' => 10,
            'username' => 5,
            'image' => 10,
            'dob' => 5,
            'gender' => 5,
            'skills' => 8,
            'overview' => 8,
            'education' => 8,
            'projects' => 6,
            'experience' => 10,
        ];

        $candidate = is_array($member->candidate_profile) ? $member->candidate_profile : [];

        $checks = [
            'name' => !empty($member->name),
            'email' => !empty($member->email),
            'phone' => !empty($member->phone),
            'username' => !empty($member->username),
            'image' => !empty($member->image),
            'dob' => !empty($member->dob),
            'gender' => !empty($member->gender),
            'skills' => !empty($candidate['skills']),
            'overview' => !empty($candidate['overview']),
            'education' => !empty($candidate['education']),
            'projects' => !empty($candidate['projects']),
            'experience' => array_key_exists('is_fresher', $candidate)
                ? ($candidate['is_fresher'] ? true : !empty($candidate['experience']))
                : false,
        ];

        $totalWeight = array_sum($weights);
        $score = 0;
        $missing = [];

        foreach ($weights as $key => $weight) {
            if (!empty($checks[$key])) {
                $score += $weight;
            } else {
                $missing[] = $key;
            }
        }

        $percentage = $totalWeight > 0 ? (int) floor(($score / $totalWeight) * 100) : 0;

        return [
            'percentage' => $percentage,
            'missing_fields' => $missing,
        ];
    }

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

    public function completion(Request $request)
    {
        $member = $request->user();
        $data = $this->getProfileCompletionData($member);

        return response()->json([
            'success' => true,
            'completion_percentage' => $data['percentage'],
            'missing_fields' => $data['missing_fields'],
            'min_required' => 35,
        ]);
    }
}
