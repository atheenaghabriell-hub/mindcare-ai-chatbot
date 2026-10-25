@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<section class="dashboard-hero p-4 p-md-5 mb-4">
    <div class="row align-items-center g-4">
        <div class="col-lg-8">
            <span class="badge rounded-pill text-bg-success-subtle text-success-emphasis mb-3">Your private wellbeing space</span>
            <h1 class="display-6 fw-semibold mb-2">Welcome back, {{ auth()->user()->name }}</h1>
            <p class="lead text-secondary mb-0">Take a moment for yourself. Small check-ins can help you reflect on how you’re feeling.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <a href="#mood-checkin" class="btn btn-success btn-lg">Check in with yourself</a>
        </div>
    </div>
    <p class="small text-secondary mt-4 mb-0">Mood and sentiment features are reflective tools, not a diagnosis or clinical assessment.</p>
</section>

<section class="row g-3 mb-4" aria-label="Your activity summary">
    <div class="col-sm-6 col-xl-4">
        <article class="card dashboard-stat-card">
            <div class="d-flex align-items-start justify-content-between mb-3">
                <span class="text-secondary fw-medium">Conversations</span>
                <span class="dashboard-stat-icon" aria-hidden="true">↗</span>
            </div>
            <div class="dashboard-stat-value mb-2">{{ $conversationCount }}</div>
            <a class="link-success link-underline-opacity-0 link-underline-opacity-75-hover" href="{{ route('conversations.index') }}">View your history</a>
        </article>
    </div>
    <div class="col-sm-6 col-xl-4">
        <article class="card dashboard-stat-card">
            <div class="d-flex align-items-start justify-content-between mb-3">
                <span class="text-secondary fw-medium">Mood check-ins</span>
                <span class="dashboard-stat-icon" aria-hidden="true">♡</span>
            </div>
            <div class="dashboard-stat-value mb-2">{{ $moodCount }}</div>
            <a class="link-success link-underline-opacity-0 link-underline-opacity-75-hover" href="#mood-checkin">Record a check-in</a>
        </article>
    </div>
    <div class="col-sm-6 col-xl-4">
        <article class="card dashboard-stat-card">
            <div class="d-flex align-items-start justify-content-between mb-3">
                <span class="text-secondary fw-medium">Recent reflections</span>
                <span class="dashboard-stat-icon" aria-hidden="true">✦</span>
            </div>
            <div class="dashboard-stat-value mb-2">{{ $conversations->sum('messages_count') }}</div>
            <span class="text-secondary">Messages in your recent conversations</span>
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
                <a class="conversation-card d-flex justify-content-between align-items-center gap-3 text-decoration-none" href="{{ route('conversations.show', $conversation) }}">
                    <span class="min-w-0">
                        <span class="d-block fw-semibold text-body text-truncate">{{ $conversation->title }}</span>
                        <span class="small text-secondary">{{ $conversation->last_message_at?->diffForHumans() ?? 'Just started' }}</span>
                    </span>
                    <span class="d-flex align-items-center gap-2 flex-shrink-0">
                        <span class="badge rounded-pill text-bg-light">{{ $conversation->messages_count }} {{ \Illuminate\Support\Str::plural('message', $conversation->messages_count) }}</span>
                        <span class="text-success" aria-hidden="true">→</span>
                    </span>
                </a>
            @empty
                <div class="text-center py-4">
                    <p class="text-secondary mb-3">Your conversation history will appear here when you’re ready.</p>
                </div>
            @endforelse

            <form method="POST" action="{{ route('conversations.store') }}" class="mt-3">
                @csrf
                <input type="hidden" name="title" value="Reflection {{ now()->format('M j') }}">
                <button class="btn btn-success" type="submit">Start a conversation</button>
            </form>
        </article>
    </div>

    <div class="col-lg-5">
        <article class="card h-100 p-4">
            <h2 class="h4 mb-1">Mood trend</h2>
            <p class="text-secondary small mb-3">Your recent self-reported check-ins. This is not a clinical assessment.</p>
            @if($moods->isNotEmpty())
                <div class="mood-chart-wrap">
                    <canvas id="mood-trend-chart" role="img" aria-label="Chart of your recent mood check-in intensity"></canvas>
                </div>
                <script>window.mindCareMoodData = @js($moodChartData);</script>
            @else
                <div class="d-flex flex-column flex-grow-1 justify-content-center align-items-center text-center py-4">
                    <span class="dashboard-stat-icon mb-3" aria-hidden="true">♡</span>
                    <p class="text-secondary mb-0">Your personal trend will appear after your first check-in.</p>
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
                        'low' => 'text-bg-warning',
                        'neutral' => 'text-bg-secondary',
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
                <p class="text-secondary mb-0">Your saved check-ins will appear here.</p>
            @endforelse
        </article>
    </div>
</section>

<div class="alert alert-warning border-warning-subtle mb-0" role="note">
    <h2 class="h5 alert-heading">Need urgent help?</h2>
    <p class="mb-0">If you or someone else may be in immediate danger, contact your local emergency number or go to the nearest emergency department. MindCare cannot monitor emergencies.</p>
</div>
@endsection
