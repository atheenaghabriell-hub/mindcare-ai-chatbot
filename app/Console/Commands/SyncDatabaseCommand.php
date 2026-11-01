<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SyncDatabaseCommand extends Command
{
    protected $signature = 'db:sync
                            {--from= : Source connection (mysql or pgsql)}
                            {--to= : Destination connection (mysql or pgsql)}
                            {--tables= : Comma-separated list of tables to sync}
                            {--truncate : Truncate destination tables before inserting}';

    protected $description = 'Synchronize data between local (MySQL) and online (Neon PostgreSQL) databases';

    /**
     * Tables in dependency order (parents before children).
     *
     * @var array<int, string>
     */
    protected array $defaultTables = [
        'users',
        'conversations',
        'messages',
        'mood_logs',
        'sentiment_logs',
        'crisis_review_events',
        'admin_audit_logs',
        'ai_usage_logs',
    ];

    public function handle(): int
    {
        $currentDefault = config('database.default');

        $from = $this->option('from');
        $to = $this->option('to');

        if (! $from || ! $to) {
            if ($currentDefault === 'pgsql') {
                $from = $from ?: 'pgsql';
                $to = $to ?: 'mysql';
            } else {
                $from = $from ?: 'mysql';
                $to = $to ?: 'pgsql';
            }
        }

        if ($from === $to) {
            $this->error("Source and destination connections cannot be the same ('{$from}').");
            return self::FAILURE;
        }

        $this->info("=== MindCare Database Sync ===");
        $this->line("Source:      <comment>{$from}</comment>");
        $this->line("Destination: <comment>{$to}</comment>");

        // Verify connectivity on both ends
        try {
            DB::connection($from)->getPdo();
            $this->info("✓ Connected to source [{$from}]");
        } catch (Throwable $e) {
            $this->error("✕ Could not connect to source [{$from}]: " . $e->getMessage());
            return self::FAILURE;
        }

        try {
            DB::connection($to)->getPdo();
            $this->info("✓ Connected to destination [{$to}]");
        } catch (Throwable $e) {
            $this->error("✕ Could not connect to destination [{$to}]: " . $e->getMessage());
            return self::FAILURE;
        }

        $tablesOption = $this->option('tables');
        $tables = $tablesOption
            ? array_map('trim', explode(',', $tablesOption))
            : $this->defaultTables;

        $truncate = (bool) $this->option('truncate');
        $results = [];

        foreach ($tables as $table) {
            if (! Schema::connection($from)->hasTable($table)) {
                $results[] = [$table, 0, 'Skipped (missing in source)'];
                continue;
            }

            if (! Schema::connection($to)->hasTable($table)) {
                $results[] = [$table, 0, 'Skipped (missing in destination)'];
                continue;
            }

            try {
                if ($truncate) {
                    $this->disableForeignKeys($to);
                    DB::connection($to)->table($table)->truncate();
                    $this->enableForeignKeys($to);
                }

                $count = 0;
                DB::connection($from)->table($table)->orderBy('id')->chunk(100, function ($rows) use ($to, $table, &$count) {
                    $records = array_map(fn ($r) => (array) $r, $rows->toArray());

                    if (! empty($records)) {
                        $columns = array_keys($records[0]);
                        DB::connection($to)->table($table)->upsert($records, ['id'], $columns);
                        $count += count($records);
                    }
                });

                // Reset PostgreSQL primary key sequence if destination is pgsql
                $this->resetSequenceIfPostgres($to, $table);

                $results[] = [$table, $count, 'Synced ✓'];
            } catch (Throwable $e) {
                $results[] = [$table, 0, 'Failed: ' . $e->getMessage()];
            }
        }

        $this->newLine();
        $this->table(['Table', 'Records Synced', 'Status'], $results);
        $this->info('Database synchronization complete.');

        return self::SUCCESS;
    }

    private function resetSequenceIfPostgres(string $connection, string $table): void
    {
        $driver = config("database.connections.{$connection}.driver");
        if ($driver !== 'pgsql') {
            return;
        }

        try {
            DB::connection($connection)->statement(
                "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), coalesce(max(id), 1), max(id) IS NOT NULL) FROM {$table}"
            );
        } catch (Throwable) {
            // Best effort sequence reset
        }
    }

    private function disableForeignKeys(string $connection): void
    {
        $driver = config("database.connections.{$connection}.driver");
        if ($driver === 'mysql') {
            DB::connection($connection)->statement('SET FOREIGN_KEY_CHECKS=0;');
        }
    }

    private function enableForeignKeys(string $connection): void
    {
        $driver = config("database.connections.{$connection}.driver");
        if ($driver === 'mysql') {
            DB::connection($connection)->statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }
}

