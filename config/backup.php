<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database backup settings (php artisan db:backup)
    |--------------------------------------------------------------------------
    |
    | Logical (SQL) backups are written to the given filesystem disk under the
    | given directory and pruned after "keep_days". On serverless hosts such as
    | Vercel the local disk is ephemeral, so point BACKUP_DISK at durable
    | storage (e.g. an S3-compatible disk) or rely on the DB provider's
    | automated backups and use db:backup for ad-hoc logical exports.
    |
    */

    'disk' => env('BACKUP_DISK', 'local'),

    'directory' => env('BACKUP_DIRECTORY', 'backups'),

    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 30),

    'compress' => (bool) env('BACKUP_COMPRESS', true),

    /*
    | Prefer the native mysqldump binary when it exists on PATH. The portable
    | PHP dumper is used otherwise (works for MySQL, MariaDB, PostgreSQL, SQLite).
    */
    'use_mysqldump' => (bool) env('BACKUP_USE_MYSQLDUMP', true),

];
