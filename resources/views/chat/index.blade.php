@extends('layouts.app')

@section('title', 'Conversation history')

@section('content')
<div class="mc-row"><div><h1>Conversation history</h1><p class="muted">Only you can access your conversations.</p></div>
    <form method="POST" action="{{ route('conversations.store') }}">@csrf <input type="hidden" name="title" value="Reflection {{ now()->format('M j') }}"><button type="submit">New conversation</button></form>
</div>
<section class="card">
    @forelse($conversations as $conversation)
        <div class="mc-row">
            <div><a href="{{ route('conversations.show', $conversation) }}"><strong>{{ $conversation->title }}</strong></a><div class="muted small">{{ $conversation->messages_count }} messages · {{ $conversation->last_message_at?->diffForHumans() }}</div></div>
            <form method="POST" action="{{ route('conversations.destroy', $conversation) }}" onsubmit="return confirm('Delete this conversation and its messages?')">@csrf @method('DELETE')<button class="danger" type="submit">Delete</button></form>
        </div>
    @empty
        <p class="muted">No conversations yet.</p>
    @endforelse
    {{ $conversations->links() }}
</section>
@endsection
