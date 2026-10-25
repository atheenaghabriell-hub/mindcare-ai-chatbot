<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = Auth::user();
        $moods = $user->moodLogs()
            ->latest('recorded_at')
            ->limit(7)
            ->get()
            ->reverse()
            ->values();

        return view('dashboard.index', [
            'conversations' => $user->conversations()
                ->withCount('messages')
                ->latest('last_message_at')
                ->limit(5)
                ->get(),
            'moods' => $moods,
            'moodChartData' => [
                'labels' => $moods->map(fn ($mood) => $mood->recorded_at->format('M j'))->all(),
                'intensities' => $moods->map(fn ($mood) => $mood->intensity ?? 3)->all(),
                'moods' => $moods->pluck('mood')->all(),
            ],
            'conversationCount' => $user->conversations()->count(),
            'moodCount' => $user->moodLogs()->count(),
        ]);
    }
}
