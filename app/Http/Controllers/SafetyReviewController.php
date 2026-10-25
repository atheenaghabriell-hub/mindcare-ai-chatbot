<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\CrisisReviewEvent;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SafetyReviewController extends Controller
{
    public function index(): View
    {
        Gate::authorize('review-crisis-events');
        AdminAuditLog::query()->create([
            'admin_user_id' => auth()->id(),
            'action' => 'view_safety_review_queue',
            'occurred_at' => now(),
        ]);

        return view('admin.safety-reviews', [
            'events' => CrisisReviewEvent::query()
                ->where('expires_at', '>', now())
                ->latest('detected_at')
                ->paginate(25, ['id', 'detected_at', 'expires_at']),
        ]);
    }
}
