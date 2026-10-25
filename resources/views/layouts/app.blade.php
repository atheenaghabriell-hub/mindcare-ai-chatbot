<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'MindCare') · MindCare</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<header class="mc-navbar">
    <nav class="navbar navbar-expand-lg" aria-label="Main navigation">
        <div class="container">
            <a class="navbar-brand mc-brand" href="{{ auth()->check() ? route('dashboard') : route('login') }}">MindCare</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mindcareNavigation" aria-controls="mindcareNavigation" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mindcareNavigation">
                <div class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                    @auth
                        <a class="nav-link" href="{{ route('dashboard') }}">Dashboard</a>
                        <a class="nav-link" href="{{ route('conversations.index') }}">Conversations</a>
                        <a class="nav-link" href="{{ route('settings.privacy') }}">Privacy</a>
                        @if(auth()->user()->is_admin)
                            <a class="nav-link" href="{{ route('admin.analytics') }}">Admin metrics</a>
                            <a class="nav-link" href="{{ route('admin.safety-reviews') }}">Safety reviews</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}" class="ms-lg-2">@csrf
                            <button class="btn btn-outline-success btn-sm" type="submit">Sign out</button>
                        </form>
                    @else
                        <a class="nav-link" href="{{ route('login') }}">Sign in</a>
                        <a class="nav-link" href="{{ route('register') }}">Create account</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>
</header>
<main class="container mc-main">
    @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-warning" role="alert">
            <strong>Please check the form:</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    @yield('content')
</main>
<footer class="mc-footer">MindCare is an educational wellbeing prototype, not a therapist or emergency service. Do not use it for emergencies.</footer>
</body>
</html>
