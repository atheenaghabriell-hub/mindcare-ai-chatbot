@extends('layouts.app')

@section('title', 'Sign in')

@section('content')
<section class="card" style="max-width:480px;margin:24px auto">
    <h1>{{ $adminMode ? 'Administrator sign in' : 'Welcome back' }}</h1>
    <p class="muted">
        {{ $adminMode ? 'Use your existing administrator account. This page does not grant admin access.' : 'Sign in to view your private MindCare workspace.' }}
    </p>
    <form method="POST" action="{{ $adminMode ? route('admin.login.store') : route('login.store') }}">
        @csrf
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        @unless($adminMode)
            <label><input type="checkbox" name="remember" value="1" style="width:auto"> Keep me signed in</label>
        @endunless
        <p><button type="submit">Sign in</button></p>
    </form>
    @if($adminMode)
        <p class="small"><a href="{{ route('login') }}">Return to regular sign in</a></p>
    @else
        <p class="small">New here? <a href="{{ route('register') }}">Create an account</a></p>
        <p class="small">Administrator? <a href="{{ route('admin.login') }}">Admin sign in</a></p>
    @endif
</section>
@endsection
