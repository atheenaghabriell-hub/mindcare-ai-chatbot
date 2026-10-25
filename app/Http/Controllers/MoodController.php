<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMoodLogRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class MoodController extends Controller
{
    public function store(StoreMoodLogRequest $request): RedirectResponse
    {
        Auth::user()->moodLogs()->create($request->validated());

        return redirect()->route('dashboard')->with('status', 'Mood entry saved.');
    }
}
