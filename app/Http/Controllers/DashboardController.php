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
        $mostCommonMood = $moods
            ->countBy('mood')
            ->sortDesc()
            ->keys()
            ->first();
        $moodLabels = [
            'very_low' => 'very low',
            'low' => 'low',
            'neutral' => 'neutral',
            'good' => 'good',
            'very_good' => 'very good',
        ];

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
            'reflectionInsight' => $mostCommonMood
                ? 'The mood you selected most often in your recent check-ins is '.$moodLabels[$mostCommonMood].'. Continue using daily reflections to monitor your emotional well-being.'
                : 'Your reflections are personal to you. Record a mood check-in when you feel ready to begin noticing patterns over time.',
        ]);
    }
}
