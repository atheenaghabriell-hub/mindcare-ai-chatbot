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
        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model');

        return view('profile.privacy', [
            'geminiConfigured' => is_string($apiKey) && $apiKey !== ''
                && is_string($model) && $model !== '',
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
