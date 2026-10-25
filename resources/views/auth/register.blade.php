@extends('layouts.app')

@section('title', 'Create account')

@section('content')
<section class="card" style="max-width:520px;margin:24px auto">
    <h1>Create your account</h1>
    <div class="notice warning">This student capstone prototype is not clinical care. Your account stores your conversations and mood entries. You can delete them by deleting your account.</div>
    <form method="POST" action="{{ route('register.store') }}">
        @csrf
        <label for="name">Name</label>
        <input id="name" name="name" value="{{ old('name') }}" autocomplete="name" maxlength="255" required>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
        <label for="password">Password (at least 9 characters, including letters and numbers)</label>
        <input id="password" name="password" type="password" autocomplete="new-password" required>
        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
        <label><input type="checkbox" name="privacy_acknowledged" value="1" required style="width:auto"> I understand this is not a therapist or emergency service and that my entries are stored in this app.</label>
        <p><button type="submit">Create account</button></p>
    </form>
</section>
@endsection
