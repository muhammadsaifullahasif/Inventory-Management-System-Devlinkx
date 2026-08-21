<?php

return [

    'backup' => [

        // Used as the subdirectory name on each destination disk, e.g.
        // storage/app/backups/{name}/*.zip
        'name' => env('APP_NAME', 'inventory-management-system'),

        'source' => [

            'files' => [
                // storage/app (product images, dompdf PDFs, uploads) + storage/logs.
                // storage/framework/cache stays excluded — cache, not data.
                'include' => [
                    storage_path('app'),
                    storage_path('logs'),
                ],

                'exclude' => [
                    storage_path('framework/cache'),
                    storage_path('app/backups'), // never back up the backups themselves
                ],

                'follow_links' => false,

                'ignore_unreadable_directories' => false,

                // null (absolute path) breaks on Windows: entries end up named
                // with the drive letter + colon (e.g. "D:\Laravel\..."), which
                // isn't valid inside a zip entry and gets silently dropped on
                // extract. Relative to project root instead.
                'relative_path' => base_path(),
            ],

            // Full dump of the default connection (mysql) — accounting, catalog,
            // sales, purchasing, RBAC tables, everything. No table exclusions:
            // the 34-table AUTO_INCREMENT incident (see RESTORE.md) means every
            // table's schema needs to be reproducible from a restore.
            'databases' => [
                'mysql',
            ],
        ],

        'database_dump_compressor' => null,

        'database_dump_file_timestamp_format' => null,

        'database_dump_filename_base' => 'database',

        'database_dump_file_extension' => '',

        // destination.* MUST live inside 'backup' — spatie/laravel-backup's
        // config merge is shallow, not recursive, so this nesting matters.
        'destination' => [
            'compression_method' => \ZipArchive::CM_DEFAULT,

            'compression_level' => 9,

            'filename_prefix' => '',

            // Local disk today; add 's3' here later once the commented-out
            // 'backups-s3' disk in config/filesystems.php is filled in with
            // real credentials, e.g. 'disks' => ['backups', 's3'].
            'disks' => [
                'backups',
            ],
        ],

        'temporary_directory' => storage_path('app/backup-temp'),

        'password' => env('BACKUP_ARCHIVE_PASSWORD'),

        'encryption' => 'default',

        'tries' => 1,

        'retry_delay' => 0,
    ],

    'notifications' => [

        // All channels empty — 'log' is not a real Laravel notification driver
        // (Manager::createDriver blows up on it). Alerting is handled directly
        // via BackupHasFailed/CleanupHasFailed/UnhealthyBackupWasFound listeners
        // in AppServiceProvider (Log::critical + Sentry), not through spatie's
        // own notification system.
        'notifications' => [
            \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class => [],
            \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification::class => [],
            \Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification::class => [],
            \Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class => [],
            \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification::class => [],
            \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification::class => [],
        ],

        'notifiable' => \Spatie\Backup\Notifications\Notifiable::class,

        'mail' => [
            'to' => env('BACKUP_NOTIFICATION_EMAIL', 'admin@example.com'),
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'backups@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Backup Monitor'),
            ],
        ],

        'slack' => [
            'webhook_url' => env('BACKUP_SLACK_WEBHOOK_URL', ''),
            'channel' => null,
            'username' => null,
            'icon' => null,
        ],

        'discord' => [
            'webhook_url' => env('BACKUP_DISCORD_WEBHOOK_URL', ''),
            'username' => '',
            'avatar_url' => '',
        ],
    ],

    'monitor_backups' => [
        [
            'name' => env('APP_NAME', 'inventory-management-system'),
            'disks' => ['backups'],
            'health_checks' => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],
    ],

    'cleanup' => [
        'strategy' => \Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy::class,

        // 7 daily, 4 weekly, 3 monthly. keep_all_backups_for_days is small
        // (1) since backups already run daily — no need to keep every run
        // within a day un-thinned.
        'default_strategy' => [
            'keep_all_backups_for_days' => 1,
            'keep_daily_backups_for_days' => 7,
            'keep_weekly_backups_for_weeks' => 4,
            'keep_monthly_backups_for_months' => 3,
            'keep_yearly_backups_for_years' => 0,
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],

        'tries' => 1,

        'retry_delay' => 0,
    ],

];
