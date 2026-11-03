<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminLogController extends Controller
{
    private const MAX_BYTES = 524288;

    private const MAX_LINES = 300;

    public function __invoke(Request $request): \Illuminate\Http\Response
    {
        Gate::authorize('view-application-logs');

        $logs = $this->availableLogs();
        $requestedFile = $request->query('file');
        $selectedLog = null;

        if ($requestedFile !== null) {
            if (! is_string($requestedFile)) {
                abort(404);
            }

            foreach ($logs as $log) {
                if ($log['name'] === $requestedFile) {
                    $selectedLog = $log;
                    break;
                }
            }

            if ($selectedLog === null) {
                abort(404);
            }
        } else {
            $selectedLog = $logs[0] ?? null;
        }

        $content = '';
        $truncated = false;

        if ($selectedLog !== null) {
            [$content, $truncated] = $this->readLogTail($selectedLog['path']);
        }

        return response()
            ->view('admin.logs', [
                'logs' => $logs,
                'selectedLog' => $selectedLog,
                'content' => $content,
                'truncated' => $truncated,
            ])
            ->header('Cache-Control', 'private, no-store');
    }

    /**
     * @return array<int, array{name: string, path: string, size: int, modified: int}>
     */
    private function availableLogs(): array
    {
        $directory = storage_path('logs');
        $realDirectory = realpath($directory);

        if ($realDirectory === false) {
            return [];
        }

        $paths = glob($directory.DIRECTORY_SEPARATOR.'*.log');
        if ($paths === false) {
            abort(500, 'Unable to list application log files.');
        }

        $logs = [];
        foreach ($paths as $path) {
            $realPath = realpath($path);
            if ($realPath === false || ! is_file($realPath)) {
                continue;
            }

            $directoryPrefix = rtrim($realDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
            if (strncasecmp($realPath, $directoryPrefix, strlen($directoryPrefix)) !== 0) {
                continue;
            }

            $size = filesize($realPath);
            $modified = filemtime($realPath);
            if ($size === false || $modified === false) {
                abort(500, 'Unable to read application log file metadata.');
            }

            $logs[] = [
                'name' => basename($realPath),
                'path' => $realPath,
                'size' => $size,
                'modified' => $modified,
            ];
        }

        usort($logs, fn (array $a, array $b): int => $b['modified'] <=> $a['modified']);

        return $logs;
    }

    /**
     * @return array{string, bool}
     */
    private function readLogTail(string $path): array
    {
        $size = filesize($path);
        if ($size === false) {
            abort(500, 'Unable to determine the selected log file size.');
        }

        $offset = max(0, $size - self::MAX_BYTES);
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            abort(500, 'Unable to open the selected log file.');
        }

        try {
            if (fseek($handle, $offset) !== 0) {
                abort(500, 'Unable to seek within the selected log file.');
            }

            $contents = fread($handle, self::MAX_BYTES);
            if ($contents === false) {
                abort(500, 'Unable to read the selected log file.');
            }
        } finally {
            fclose($handle);
        }

        $lines = explode("\n", $contents);
        if ($offset > 0) {
            array_shift($lines);
        }

        $truncated = $offset > 0 || count($lines) > self::MAX_LINES;

        return [
            implode("\n", array_slice($lines, -self::MAX_LINES)),
            $truncated,
        ];
    }
}
