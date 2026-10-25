<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PrivacySettingsController extends Controller
{
    public function edit(): View
    {
        return view('profile.privacy', [
            'geminiConfigured' => config('services.gemini.api_key') !== null
                && config('services.gemini.api_key') !== '',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'gemini_consent' => ['nullable', 'boolean'],
            'context_memory_enabled' => ['nullable', 'boolean'],
            'crisis_review_consent' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $user->forceFill([
            'gemini_data_sharing_consented_at' => ($data['gemini_consent'] ?? false)
                ? ($user->gemini_data_sharing_consented_at ?? now())
                : null,
            'context_memory_enabled' => (bool) ($data['context_memory_enabled'] ?? false),
            'crisis_review_consented_at' => ($data['crisis_review_consent'] ?? false)
                ? ($user->crisis_review_consented_at ?? now())
                : null,
        ])->save();

        return back()->with('status', 'Privacy preferences updated.');
    }

    public function destroyAccount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($data['password'], $request->user()->password)) {
            return back()->withErrors(['password' => 'The password was not accepted.']);
        }

        $user = $request->user();
        Auth::logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('register')
            ->with('status', 'Your account and associated MindCare records were deleted.');
    }
}
