@extends('layouts.app')

@section('title', 'Aggregate usage metrics')

@section('content')
<h1>Aggregate usage metrics</h1>
<p class="muted">Counts only. This page does not display user identities, messages, mood details, or sentiment labels. AI usage logs contain metadata only, never prompts or responses.</p>
<div class="grid">
    <section class="card"><div class="muted">Accounts</div><div class="stat">{{ $userCount }}</div></section>
    <section class="card"><div class="muted">Conversations</div><div class="stat">{{ $conversationCount }}</div></section>
    <section class="card"><div class="muted">Messages</div><div class="stat">{{ $messageCount }}</div></section>
    <section class="card"><div class="muted">Mood check-ins</div><div class="stat">{{ $moodLogCount }}</div></section>
    <section class="card"><div class="muted">Opt-in safety flags, last 30 days</div><div class="stat">{{ $crisisEventsLast30Days }}</div></section>
    <section class="card"><div class="muted">AI requests, last 30 days</div><div class="stat">{{ $aiRequestsLast30Days }}</div></section>
    <section class="card"><div class="muted">AI request failures</div><div class="stat">{{ $aiFailuresLast30Days }}</div></section>
</div>
<p><a href="{{ route('admin.safety-reviews') }}">Open metadata-only safety review queue</a></p>
@endsection
