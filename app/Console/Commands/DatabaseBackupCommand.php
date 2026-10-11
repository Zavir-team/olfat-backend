<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Logical database backup (full export) with retention and verification.
 *
 * DBA reference: Mullins, "Database Administration" 2nd ed., Ch. 16
 * (Full vs. incremental backups, logical backups, "Document Your Backup Strategy").
 */
class DatabaseBackupCommand extends Command
{
    protected $signature = 'db:backup
        {--disk= : Filesystem disk to write to (default: config backup.disk)}
        {--keep= : Days of backups to keep (default: config backup.keep_days, 0 = keep all)}
        {--connection= : Database connection name (default: database.default)}
        {--no-compress : Write plain .sql instead of .sql.gz}';

    protected $description = 'Create a verified logical SQL backup of the database and prune old backups';

    /** @var list<string> Transient framework tables that must not be restored over a live system. */
    private const VOLATILE_TABLES = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches'];

    public function handle(): int
    {
        $connectionName = (string) ($this->option('connection') ?: config('database.default'));
        $connection = DB::connection($connectionName);
        $driver = $connection->getDriverName();

        $diskName = (string) ($this->option('disk') ?: config('backup.disk'));
        $directory = trim((string) config('backup.directory'), '/');
        $keepDays = $this->option('keep') !== null ? (int) $this->option('keep') : (int) config('backup.keep_days');
        $compress = ! $this->option('no-compress') && (bool) config('backup.compress') && function_exists('gzencode');

        $disk = Storage::disk($diskName);
        $timestamp = now()->format('Y-m-d_His');
        $fileName = sprintf('%s-%s-%s.sql%s', str()->slug((string) config('app.name', 'app')), $driver, $timestamp, $compress ? '.gz' : '');
        $path = ($directory !== '' ? $directory.'/' : '').$fileName;

        $this->components->info("Backing up [{$connectionName}] ({$driver}) to disk [{$diskName}] as {$path}");

        try {
            $sql = $this->dump($connection, $driver);
        } catch (Throwable $e) {
            $this->components->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $payload = $compress ? gzencode($sql, 9) : $sql;

        if ($payload === false || ! $disk->put($path, $payload)) {
            $this->components->error('Could not write backup file.');

            return self::FAILURE;
        }

        if (! $this->verify($disk->get($path) ?? '', $compress)) {
            $this->components->error('Backup verification failed (file empty or corrupt).');
            $disk->delete($path);

            return self::FAILURE;
        }

        $size = $disk->size($path);
        $this->components->twoColumnDetail('Backup file', $path);
        $this->components->twoColumnDetail('Size', $this->humanSize($size));
        $this->components->twoColumnDetail('Verified', 'yes');

        $pruned = $this->prune($disk, $directory, $keepDays);
        $this->components->twoColumnDetail('Pruned old backups', (string) $pruned);

        return self::SUCCESS;
    }

    private function dump(Connection $connection, string $driver): string
    {
        if (in_array($driver, ['mysql', 'mariadb'], true) && config('backup.use_mysqldump')) {
            $native = $this->mysqldump($connection);

            if ($native !== null) {
                return $native;
            }

            $this->components->warn('mysqldump not available; using portable PHP dumper.');
        }

        return $this->portableDump($connection, $driver);
    }

    private function mysqldump(Connection $connection): ?string
    {
        $binary = (new ExecutableFinder)->find('mysqldump');

        if ($binary === null) {
            return null;
        }

        $config = $connection->getConfig();

        $process = new Process([
            $binary,
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? 3306),
            '--user='.($config['username'] ?? ''),
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--set-gtid-purged=OFF',
            '--default-character-set=utf8mb4',
            $config['database'],
        ], null, ['MYSQL_PWD' => (string) ($config['password'] ?? '')], null, 600);

        $process->run();

        if (! $process->isSuccessful()) {
            $this->components->warn('mysqldump failed: '.trim($process->getErrorOutput()));

            return null;
        }

        return $process->getOutput();
    }

    /**
     * Portable INSERT-based dump inside a single read transaction (consistent snapshot).
     */
    private function portableDump(Connection $connection, string $driver): string
    {
        $pdo = $connection->getPdo();
        $schema = Schema::connection($connection->getName());
        $tables = array_values(array_diff(
            $schema->getTableListing(schemaQualified: false),
            self::VOLATILE_TABLES,
        ));

        $out = [];
        $out[] = '-- '.config('app.name').' logical backup';
        $out[] = '-- driver: '.$driver.'  generated: '.now()->toIso8601String();
        $out[] = '-- Restore into an EMPTY schema created with: php artisan migrate --force';
        $out[] = '';

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $out[] = 'SET FOREIGN_KEY_CHECKS=0;';
            $out[] = 'SET NAMES utf8mb4;';
        } elseif ($driver === 'sqlite') {
            $out[] = 'PRAGMA foreign_keys=OFF;';
        }
        $out[] = 'BEGIN;';

        $connection->beginTransaction();

        try {
            foreach ($tables as $table) {
                $out[] = '';
                $out[] = "-- table: {$table}";

                $quotedTable = $this->quoteIdentifier($table, $driver);
                $out[] = "DELETE FROM {$quotedTable};";

                $columns = $schema->getColumnListing($table);
                $columnList = implode(', ', array_map(fn ($c) => $this->quoteIdentifier($c, $driver), $columns));

                $connection->table($table)->orderBy($columns[0])->chunk(500, function ($rows) use (&$out, $pdo, $quotedTable, $columnList, $columns) {
                    $values = [];
                    foreach ($rows as $row) {
                        $row = (array) $row;
                        $values[] = '('.implode(', ', array_map(function ($column) use ($row, $pdo) {
                            $value = $row[$column] ?? null;

                            return match (true) {
                                $value === null => 'NULL',
                                is_bool($value) => $value ? '1' : '0',
                                is_int($value), is_float($value) => (string) $value,
                                default => $pdo->quote((string) $value),
                            };
                        }, $columns)).')';
                    }

                    $out[] = "INSERT INTO {$quotedTable} ({$columnList}) VALUES\n".implode(",\n", $values).';';
                });
            }
        } finally {
            $connection->rollBack();
        }

        $out[] = '';
        $out[] = 'COMMIT;';

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $out[] = 'SET FOREIGN_KEY_CHECKS=1;';
        } elseif ($driver === 'sqlite') {
            $out[] = 'PRAGMA foreign_keys=ON;';
        }

        return implode("\n", $out)."\n";
    }

    private function quoteIdentifier(string $name, string $driver): string
    {
        return in_array($driver, ['mysql', 'mariadb'], true) ? "`{$name}`" : "\"{$name}\"";
    }

    private function verify(string $contents, bool $compressed): bool
    {
        if ($contents === '') {
            return false;
        }

        if ($compressed) {
            $decoded = @gzdecode($contents);

            if ($decoded === false) {
                return false;
            }

            $contents = $decoded;
        }

        return str_contains($contents, '-- table:') || str_contains($contents, 'CREATE TABLE');
    }

    private function prune(Filesystem $disk, string $directory, int $keepDays): int
    {
        if ($keepDays <= 0) {
            return 0;
        }

        $cutoff = now()->subDays($keepDays)->getTimestamp();
        $pruned = 0;

        foreach ($disk->files($directory) as $file) {
            if (! str_ends_with($file, '.sql') && ! str_ends_with($file, '.sql.gz')) {
                continue;
            }

            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $pruned++;
            }
        }

        return $pruned;
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = (float) $bytes;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return sprintf('%.1f %s', $size, $units[$i]);
    }
}
