@extends('layouts.app')

@section('title', 'Application logs')

@section('content')
<h1>Application logs</h1>
<p class="muted">View recent log output written to this app's <code>storage/logs</code> folder. Hosting-provider and other external service logs are not available here. Log entries may contain sensitive information; this page is restricted to administrators.</p>

<section class="card mb-3">
    <h2 class="h5">Logging status for this running instance</h2>
    <dl class="mb-0">
        <dt>Active channel</dt>
        <dd><code>{{ $loggingChannel }}</code></dd>
        @if($loggingChannel === 'stack')
            <dt>Stack channels</dt>
            <dd><code>{{ implode(', ', (array) $stackChannels) }}</code></dd>
        @endif
        <dt>Log folder</dt>
        <dd>{{ $logDirectoryExists ? 'Present' : 'Missing' }}; {{ $logDirectoryWritable ? 'writable' : 'not writable' }}</dd>
    </dl>
</section>

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
    <div class="alert alert-info" role="status">
        No .log files were found in this running instance's <code>storage/logs</code> folder.
        @if(! $logDirectoryExists || ! $logDirectoryWritable)
            Check the deployment's storage-folder setup and write permissions.
        @else
            A 502 in the web-server access log is not a Laravel log entry. Confirm the active channel above includes <code>single</code>, then reproduce the error on this instance. Render instances have separate temporary filesystems, so the request and this page must reach the same instance for local files to be visible.
        @endif
    </div>
@endif
@endsection
