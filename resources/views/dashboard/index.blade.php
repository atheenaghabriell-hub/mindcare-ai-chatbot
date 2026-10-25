@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<section class="dashboard-hero p-4 p-md-5 mb-4">
    <div class="row align-items-center g-4">
        <div class="col-lg-8">
            <span class="badge rounded-pill text-bg-success-subtle text-success-emphasis mb-3">Your private wellbeing space</span>
            <h1 class="display-6 fw-semibold mb-2">Welcome back, {{ auth()->user()->name }}</h1>
            <p class="lead text-secondary mb-0">Take a moment to reflect and track your emotional well-being. Small steps can make space for self-understanding.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <form method="POST" action="{{ route('conversations.store') }}">
                @csrf
                <input type="hidden" name="title" value="Reflection {{ now()->format('M j') }}">
                <button class="btn btn-success btn-lg" type="submit">Start new conversation</button>
            </form>
        </div>
    </div>
    <p class="small text-secondary mt-4 mb-0">Mood and sentiment features are reflective tools, not a diagnosis or clinical assessment.</p>
</section>

<section class="row g-3 mb-4" aria-label="Your activity summary">
    <div class="col-sm-6 col-xl-4">
        <article class="card dashboard-stat-card">
            <div class="d-flex align-items-start justify-content-between mb-3">
                <span class="text-secondary fw-medium">Conversations</span>
                <span class="dashboard-stat-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H6l-3 2v-5.5A7.5 7.5 0 1 1 20 11.5Z"/><path d="M8 11h8M8 14h5"/></svg>
                </span>
            </div>
            <div class="dashboard-stat-value mb-2">{{ $conversationCount }}</div>
            <a class="link-success link-underline-opacity-0 link-underline-opacity-75-hover" href="{{ route('conversations.index') }}">View your history</a>
        </article>
    </div>
    <div class="col-sm-6 col-xl-4">
        <article class="card dashboard-stat-card">
            <div class="d-flex align-items-start justify-content-between mb-3">
                <span class="text-secondary fw-medium">Mood check-ins</span>
                <span class="dashboard-stat-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.8 8.6c0 5.2-8.8 10-8.8 10s-8.8-4.8-8.8-10a4.6 4.6 0 0 1 8.8-1.9 4.6 4.6 0 0 1 8.8 1.9Z"/></svg>
                </span>
            </div>
            <div class="dashboard-stat-value mb-2">{{ $moodCount }}</div>
            <a class="link-success link-underline-opacity-0 link-underline-opacity-75-hover" href="#mood-checkin">Record a check-in</a>
        </article>
    </div>
    <div class="col-sm-6 col-xl-4">
        <article class="card dashboard-stat-card">
            <div class="d-flex align-items-start justify-content-between mb-3">
                <span class="text-secondary fw-medium">Recent reflections</span>
                <span class="dashboard-stat-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v18M3 12h18"/><path d="m5.6 5.6 12.8 12.8M18.4 5.6 5.6 18.4"/></svg>
                </span>
            </div>
            <div class="dashboard-stat-value mb-2">{{ $conversations->sum('messages_count') }}</div>
            <span class="text-secondary">Messages across your latest conversations</span>
        </article>
    </div>
</section>

<section class="row g-4 mb-4">
    <div class="col-lg-7">
        <article class="card h-100 p-4">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h2 class="h4 mb-1">Recent conversations</h2>
                    <p class="text-secondary mb-0">Your private reflections, all in one place.</p>
                </div>
                <a class="btn btn-outline-success btn-sm flex-shrink-0" href="{{ route('conversations.index') }}">View all</a>
            </div>

            @forelse($conversations as $conversation)
                <article class="conversation-card d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                    <div class="min-w-0">
                        <h3 class="h6 fw-semibold text-body text-truncate mb-1">{{ $conversation->title }}</h3>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="badge rounded-pill text-bg-light">{{ $conversation->messages_count }} {{ \Illuminate\Support\Str::plural('message', $conversation->messages_count) }}</span>
                            @if($conversation->last_message_at)
                                <time class="small text-secondary" datetime="{{ $conversation->last_message_at->toIso8601String() }}">{{ $conversation->last_message_at->diffForHumans() }}</time>
                            @else
                                <span class="small text-secondary">Just started</span>
                            @endif
                        </div>
                    </div>
                    <a class="btn btn-outline-success btn-sm flex-shrink-0" href="{{ route('conversations.show', $conversation) }}">Open conversation</a>
                </article>
            @empty
                <div class="dashboard-empty-state text-center py-4">
                    <p class="text-secondary mb-3">No conversations yet.</p>
                    <form method="POST" action="{{ route('conversations.store') }}">
                        @csrf
                        <input type="hidden" name="title" value="Reflection {{ now()->format('M j') }}">
                        <button class="btn btn-outline-success" type="submit">Start your first conversation</button>
                    </form>
                </div>
            @endforelse

            @if($conversations->isNotEmpty())
                <form method="POST" action="{{ route('conversations.store') }}" class="mt-3">
                @csrf
                <input type="hidden" name="title" value="Reflection {{ now()->format('M j') }}">
                    <button class="btn btn-outline-success" type="submit">Start another conversation</button>
                </form>
            @endif
        </article>
    </div>

    <div class="col-lg-5">
        <article class="card h-100 p-4">
            <h2 class="h4 mb-1">Mood trend</h2>
            <p class="text-secondary small mb-3" id="mood-chart-description">Your last 7 self-reported check-ins. This is not a clinical assessment.</p>
            @if($moods->isNotEmpty())
                <div class="mood-chart-wrap">
                    <canvas id="mood-trend-chart" role="img" aria-label="Chart of your last 7 mood check-in intensity scores" aria-describedby="mood-chart-description"></canvas>
                </div>
                <script>window.mindCareMoodData = @js($moodChartData);</script>
            @else
                <div class="d-flex flex-column flex-grow-1 justify-content-center align-items-center text-center py-4">
                    <span class="dashboard-stat-icon mb-3" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 17 9 11l4 4 8-9"/><path d="M15 6h6v6"/></svg>
                    </span>
                    <p class="text-secondary mb-3">No mood entries recorded.</p>
                    <a class="btn btn-outline-success btn-sm" href="#mood-checkin">Record your first mood</a>
                </div>
            @endif
        </article>
    </div>
</section>

<section class="row g-4 mb-4" id="mood-checkin">
    <div class="col-lg-7">
        <article class="card p-4 h-100">
            <h2 class="h4 mb-1">How are you feeling today?</h2>
            <p class="text-secondary mb-3">Choose the mood that feels closest. There are no right or wrong answers.</p>
            <form method="POST" action="{{ route('moods.store') }}">
                @csrf
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label" for="mood">Mood</label>
                        <select class="form-select" id="mood" name="mood" required>
                            <option value="">Choose one</option>
                            <option value="very_low">Very low</option>
                            <option value="low">Low</option>
                            <option value="neutral">Neutral</option>
                            <option value="good">Good</option>
                            <option value="very_good">Very good</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="intensity">Intensity (optional)</label>
                        <select class="form-select" id="intensity" name="intensity">
                            <option value="">Skip</option>
                            @for($i = 1; $i <= 5; $i++)
                                <option value="{{ $i }}">{{ $i }} of 5</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="hidden" name="recorded_at" value="{{ now()->toDateTimeString() }}">
                        <button class="btn btn-success w-100" type="submit">Save check-in</button>
                    </div>
                </div>
            </form>
        </article>
    </div>
    <div class="col-lg-5">
        <article class="card p-4 h-100">
            <h2 class="h4 mb-3">Recent mood check-ins</h2>
            @forelse($moods->reverse() as $mood)
                @php
                    $moodStyle = match($mood->mood) {
                        'very_low' => 'text-bg-danger',
                        'low' => 'mood-badge-orange',
                        'neutral' => 'text-bg-warning',
                        'good' => 'text-bg-success',
                        'very_good' => 'text-bg-primary',
                        default => 'text-bg-light',
                    };
                    $moodLabel = str($mood->mood)->replace('_', ' ')->title();
                @endphp
                <div class="d-flex justify-content-between align-items-center gap-2 py-2 border-bottom">
                    <span>
                        <span class="badge rounded-pill {{ $moodStyle }}">{{ $moodLabel }}</span>
                        @if($mood->intensity)
                            <span class="small text-secondary ms-1">Intensity {{ $mood->intensity }}/5</span>
                        @endif
                    </span>
                    <time class="small text-secondary flex-shrink-0" datetime="{{ $mood->recorded_at->toIso8601String() }}">{{ $mood->recorded_at->format('M j') }}</time>
                </div>
            @empty
                <div class="dashboard-empty-state">
                    <p class="text-secondary mb-3">No mood entries recorded.</p>
                    <a class="btn btn-outline-success btn-sm" href="#mood-checkin">Record your first mood</a>
                </div>
            @endforelse
        </article>
    </div>
</section>

<section class="card ai-insight-card p-4 mb-4" aria-labelledby="ai-insight-title">
    <div class="d-flex align-items-start gap-3">
        <span class="dashboard-stat-icon flex-shrink-0" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 14.5 9.5 21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5L12 3Z"/><path d="m19 2 .8 2.2L22 5l-2.2.8L19 8l-.8-2.2L16 5l2.2-.8L19 2Z"/></svg>
        </span>
        <div>
            <h2 class="h5 mb-2" id="ai-insight-title">AI Reflection Insight</h2>
            <p class="text-body-secondary mb-2">{{ $reflectionInsight }}</p>
            <p class="small text-secondary mb-0">This is a simple reflection based on your recent check-ins, not an AI-generated assessment.</p>
        </div>
    </div>
</section>

<div class="alert alert-warning border-warning-subtle mb-0" role="note">
    <h2 class="h5 alert-heading">Need urgent help?</h2>
    <p class="mb-0">If you or someone else may be in immediate danger, contact your local emergency number or go to the nearest emergency department. MindCare cannot monitor emergencies.</p>
</div>
@endsection
