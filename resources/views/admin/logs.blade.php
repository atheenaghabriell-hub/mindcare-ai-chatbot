@extends('layouts.app')

@section('title', 'Application logs')

@section('content')
<h1>Application logs</h1>
<p class="muted">View recent log output written to this app's <code>storage/logs</code> folder. Hosting-provider and other external service logs are not available here. Log entries may contain sensitive information; this page is restricted to administrators.</p>

@if($logs)
    <form method="GET" action="{{ route('admin.logs') }}" class="card">
        <label for="log-file">Log file</label>
        <div class="d-flex gap-2 align-items-center">
            <select id="log-file" name="file" class="form-select">
                @foreach($logs as $log)
                    <option value="{{ $log['name'] }}" @selected($selectedLog && $selectedLog['name'] === $log['name'])>
                        {{ $log['name'] }} ({{ number_format($log['size']) }} bytes)
                    </option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-success">View</button>
        </div>
    </form>

    @if($truncated)
        <div class="alert alert-info" role="status">Showing only the most recent {{ number_format(300) }} lines, with a maximum read of 512 KB.</div>
    @endif

    <section class="card mt-3">
        <h2 class="h5">{{ $selectedLog['name'] }}</h2>
        <pre class="mb-0" style="max-height: 65vh; overflow: auto; white-space: pre-wrap; overflow-wrap: anywhere;">{{ $content !== '' ? $content : 'This log file is empty.' }}</pre>
    </section>
@else
    <div class="alert alert-info" role="status">No .log files were found in the app's storage/logs folder.</div>
@endif
@endsection
