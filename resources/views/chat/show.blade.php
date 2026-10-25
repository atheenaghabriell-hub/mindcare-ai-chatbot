@extends('layouts.app')

@section('title', $conversation->title)

@section('content')
<div class="mc-row"><div><a href="{{ route('conversations.index') }}">← History</a><h1>{{ $conversation->title }}</h1></div>
    <form method="POST" action="{{ route('conversations.destroy', $conversation) }}" onsubmit="return confirm('Delete this conversation?')">@csrf @method('DELETE')<button class="danger" type="submit">Delete conversation</button></form>
</div>
<div class="notice warning"><strong>Not for emergencies:</strong> This AI is not a therapist and cannot contact emergency services. If you may be in immediate danger, contact your local emergency number or nearest emergency department.</div>
@foreach($messages as $message)
    <article class="message {{ $message->role === 'user' ? 'user' : 'assistant' }}"><strong>{{ $message->role === 'user' ? 'You' : 'MindCare assistant' }}</strong><div>{{ $message->content }}</div></article>
@endforeach
{{ $messages->links() }}
<section class="card">
    @if(!auth()->user()->hasConsentedToGeminiSharing())
        <p>AI chat is off until you opt in to sending messages to Gemini. Your messages are not sent to Gemini without your consent.</p>
        <a class="button" href="{{ route('settings.privacy') }}">Review privacy settings</a>
    @else
        <form method="POST" action="{{ route('messages.store', $conversation) }}">
            @csrf
            <label for="content">Share what’s on your mind</label>
            <textarea id="content" name="content" maxlength="4000" required>{{ old('content') }}</textarea>
            <p class="muted small">Your message and up to the recent turns in this conversation may be sent to Gemini. Don’t include details you don’t want processed by the AI provider.</p>
            <button type="submit">Send message</button>
        </form>
    @endif
</section>
@endsection
