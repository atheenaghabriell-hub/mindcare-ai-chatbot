@extends('layouts.app')

@section('title', 'Verify your email')

@section('content')
<section class="card" style="max-width:520px;margin:24px auto">
    <h1>Verify your email address</h1>
    <p>We sent a verification link to your email address. Verify it before using MindCare.</p>
    @if (session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif
    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit">Resend verification email</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" style="margin-top:12px">
        @csrf
        <button type="submit">Sign out</button>
    </form>
</section>
@endsection
