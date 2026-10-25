@extends('layouts.app')

@section('title', 'Safety review metadata')

@section('content')
<h1>Safety review metadata</h1>
<div class="notice warning">This queue is not monitored continuously and is not an emergency response service. It contains only opt-in case references and timestamps; no user identity or message content is shown. Records expire after 30 days.</div>
<section class="card">
    @forelse($events as $event)
        <div class="mc-row"><span>Case {{ hash_hmac('sha256', (string) $event->id, config('app.key')) }}</span><span class="muted">{{ $event->detected_at->toDayDateTimeString() }}</span><span class="muted small">Expires {{ $event->expires_at->toDateString() }}</span></div>
    @empty
        <p class="muted">No active opt-in safety review records.</p>
    @endforelse
    {{ $events->links() }}
</section>
@endsection
