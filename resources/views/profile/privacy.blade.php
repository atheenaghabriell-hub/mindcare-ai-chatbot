@extends('layouts.app')

@section('title', 'Privacy settings')

@section('content')
<h1>Privacy and data controls</h1>
<p class="muted">Choose how this capstone prototype handles your information.</p>
<section class="card">
    <form method="POST" action="{{ route('settings.privacy.update') }}">
        @csrf @method('PUT')
        <h2>Gemini AI processing</h2>
        <p>When enabled, your message and up to 10 recent turns from the current conversation are sent to Google Gemini for a reply. Messages are stored in this app. Gemini availability and provider data handling depend on your configured Google account and applicable provider terms.</p>
        <p class="small"><strong>Configuration:</strong> {{ $geminiConfigured ? 'API key is configured on the server.' : 'Gemini API key is not configured; chat requests will fail until it is set.' }}</p>
        <label><input type="checkbox" name="gemini_consent" value="1" style="width:auto" @checked(auth()->user()->hasConsentedToGeminiSharing())> I consent to send my chat messages to Gemini for responses.</label>
        <h2>Context memory</h2>
        <p>When enabled, selected recent messages from your other conversations may also be included with a Gemini request. This is off by default.</p>
        <label><input type="checkbox" name="context_memory_enabled" value="1" style="width:auto" @checked(auth()->user()->context_memory_enabled)> Allow selected recent history to inform Gemini replies.</label>
        <h2>Optional safety review</h2>
        <p>If the simple phrase-based crisis check is triggered, opting in allows a supervisor to see only a case reference and timestamp for up to 30 days. No message text is included. This is not a monitored emergency service.</p>
        <label><input type="checkbox" name="crisis_review_consent" value="1" style="width:auto" @checked(auth()->user()->crisis_review_consented_at !== null)> I explicitly opt in to metadata-only safety review.</label>
        <p><button type="submit">Save privacy settings</button></p>
    </form>
</section>
<section class="card">
    <h2>Delete account and data</h2>
    <p>Deleting your account permanently deletes your conversations, messages, mood entries, sentiment metadata, and safety-review metadata.</p>
    <form method="POST" action="{{ route('settings.account.destroy') }}" onsubmit="return confirm('Permanently delete your account and associated data?')">
        @csrf @method('DELETE')
        <label for="delete-password">Confirm your password</label>
        <input id="delete-password" name="password" type="password" autocomplete="current-password" required>
        <p><button class="danger" type="submit">Delete my account and data</button></p>
    </form>
</section>
@endsection
