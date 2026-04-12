<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function show()
    {
        $user = Auth::user()->load('organization', 'departments', 'roles');

        return Inertia::render('Profile/Show', [
            'user' => $user,
        ]);
    }

    public function edit()
    {
        $user = Auth::user()->load('organization', 'departments');

        return Inertia::render('Profile/Edit', [
            'user' => $user,
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'first_name'  => ['required', 'string', 'max:100'],
            'last_name'   => ['required', 'string', 'max:100'],
            'phone'       => ['nullable', 'string', 'max:20'],
            'timezone'    => ['nullable', 'string', 'max:50'],
            'locale'      => ['nullable', 'string', 'max:10'],
        ]);

        $user->update($validated);

        return redirect()->back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->back()->with('success', 'Password updated.');
    }

    public function updatePreferences(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'goal'                => ['nullable', Rule::in(['certification', 'skill', 'internship'])],
            'exam_date'           => ['nullable', 'date'],
            'daily_minutes'       => ['nullable', 'integer', 'min:5', 'max:120'],
            'learning_preference' => ['nullable', Rule::in(['read', 'watch', 'listen'])],
            'experience_level'    => ['nullable', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'accessibility_prefs' => ['nullable', 'array'],
            'accessibility_prefs.dyslexia_font'   => ['nullable', 'boolean'],
            'accessibility_prefs.high_contrast'    => ['nullable', 'boolean'],
            'accessibility_prefs.color_blind_mode' => ['nullable', 'string'],
            'accessibility_prefs.extended_time'    => ['nullable', 'boolean'],
            'notification_prefs'  => ['nullable', 'array'],
            'notification_prefs.push'        => ['nullable', 'boolean'],
            'notification_prefs.email'       => ['nullable', 'boolean'],
            'notification_prefs.quiet_start' => ['nullable', 'string'],
            'notification_prefs.quiet_end'   => ['nullable', 'string'],
        ]);

        $user->update($validated);

        return redirect()->back()->with('success', 'Preferences updated.');
    }

    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048'], // 2MB
        ]);

        $path = $request->file('avatar')->store('avatars', 'public');

        Auth::user()->update(['avatar' => $path]);

        return redirect()->back()->with('success', 'Avatar updated.');
    }
}
