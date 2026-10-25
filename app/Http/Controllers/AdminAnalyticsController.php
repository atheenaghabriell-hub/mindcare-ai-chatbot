<?php

namespace App\Http\Controllers;

use App\Models\AiUsageLog;
use App\Models\Conversation;
use App\Models\CrisisReviewEvent;
use App\Models\Message;
use App\Models\MoodLog;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminAnalyticsController extends Controller
{
    public function __invoke(): View
    {
        Gate::authorize('view-admin-analytics');

        return view('admin.analytics', [
            'userCount' => User::query()->count(),
            'conversationCount' => Conversation::query()->count(),
            'messageCount' => Message::query()->count(),
            'moodLogCount' => MoodLog::query()->count(),
            'crisisEventsLast30Days' => CrisisReviewEvent::query()
                ->where('detected_at', '>=', now()->subDays(30))
                ->count(),
            'aiRequestsLast30Days' => AiUsageLog::query()
                ->where('occurred_at', '>=', now()->subDays(30))
                ->count(),
            'aiFailuresLast30Days' => AiUsageLog::query()
                ->where('occurred_at', '>=', now()->subDays(30))
                ->where('status', 'failed')
                ->count(),
        ]);
    }
}
