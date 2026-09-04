<?php

use Spatie\Backup\Notifications\Notifiable;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;

/*
 * `storage/app/public` n'est inclus dans l'archive que si les médias y vivent
 * réellement. En production ils sont sur Cloudflare R2 (`MEDIA_DISK=r2`,
 * `FILESYSTEM_DISK=r2`), donc déjà répliqués hors du VPS : les rezipper chaque
 * semaine n'apporterait rien. En local le même dossier pèse plusieurs Go, ce
 * qui rendait `backup:run --only-files` inutilisable.
 *
 * Les deux variables sont testées : `MEDIA_DISK` pilote spatie/medialibrary
 * (photos d'annonces) et `APP_MEDIA_DISK` les avatars, logos et contrats. Si
 * l'une des deux redevient locale, les médias repassent automatiquement dans
 * l'archive — pas de perte silencieuse en cas de changement de configuration.
 */
$mediaDisksAreLocal = (bool) array_intersect(
    [
        env('MEDIA_DISK', env('FILESYSTEM_DISK', 'public')),
        env('APP_MEDIA_DISK', env('FILESYSTEM_DISK', 'public')),
    ],
    ['public', 'local'],
);

return [

    /*
     * Envoi d'un email récapitulatif après chaque sauvegarde réussie sur le
     * disque `backups`, avec un lien R2 signé valide 48h (jamais de pièce
     * jointe : les archives dépassent la limite des fournisseurs SMTP).
     *
     * Défaut à `true` car c'est le comportement effectif depuis l'origine ;
     * le drapeau était documenté mais ignoré par le listener.
     *
     * @see \App\Listeners\SendBackupByEmailListener
     */
    'send_backup_by_mail' => (bool) env('BACKUP_SEND_BY_MAIL', true),

    'backup' => [
        /*
         * The name of this application. You can use this name to monitor
         * the backups.
         */
        'name' => env('APP_NAME', 'laravel-backup'),

        'source' => [
            'files' => [
                /*
                 * On ne sauvegarde que l'état non reproductible : les uploads du
                 * disque local (`storage/app`). Le code applicatif vit dans l'image
                 * Docker, reconstructible depuis git, et les médias de production
                 * sont déjà sur R2 (`FILESYSTEM_DISK=r2`) — inclure `base_path()`
                 * produisait une archive de plusieurs centaines de Mo sans rien
                 * protéger de plus.
                 *
                 * Conséquence voulue : `.env` reste HORS de l'archive. Les secrets
                 * de production ne partent donc jamais en clair sur R2 ; ils sont
                 * gérés côté déploiement (voir docker-compose.yml).
                 */
                'include' => [
                    storage_path('app'),
                ],

                /*
                 * These directories and files will be excluded from the backup.
                 *
                 * Directories used by the backup process will automatically be excluded.
                 */
                'exclude' => array_values(array_filter([
                    storage_path('app/tmp'),
                    storage_path('app/backup-temp'),
                    // Jeu d'images de seed (~233 Mo en local) : reproductible,
                    // absent de la production, inutile dans une archive.
                    storage_path('app/seeder-images'),
                    // Extrait OpenStreetMap (~213 Mo) : re-téléchargeable depuis
                    // Geofabrik, il doublait à lui seul la taille de l'archive.
                    storage_path('app/private/osm'),
                    // Dumps manuels : sauvegarder une sauvegarde n'apporte rien.
                    storage_path('app/private/backups'),
                    // Même logique que `.env` : les identifiants de service ne
                    // partent pas sur le bucket, ils vivent dans le gestionnaire
                    // de secrets.
                    storage_path('app/firebase-credentials.json'),
                    $mediaDisksAreLocal ? null : storage_path('app/public'),
                ])),

                /*
                 * Determines if symlinks should be followed.
                 */
                'follow_links' => false,

                /*
                 * Determines if it should avoid unreadable folders.
                 */
                'ignore_unreadable_directories' => false,

                /*
                 * Les entrées du zip sont relatives à la racine du projet
                 * (`storage/app/…` au lieu de `/var/www/storage/app/…`), ce qui
                 * rend la restauration possible par un simple `unzip` dans le
                 * dossier du projet, sans reconstruire l'arborescence absolue
                 * de la machine d'origine.
                 */
                'relative_path' => base_path(),
            ],

            /*
             * The names of the connections to the databases that should be backed up
             * MySQL, PostgreSQL, SQLite and Mongo databases are supported.
             *
             * The content of the database dump may be customized for each connection
             * by adding a 'dump' key to the connection settings in config/database.php.
             * E.g.
             * 'mysql' => [
             *       ...
             *      'dump' => [
             *           'exclude_tables' => [
             *                'table_to_exclude_from_backup',
             *                'another_table_to_exclude'
             *            ]
             *       ],
             * ],
             *
             * If you are using only InnoDB tables on a MySQL server, you can
             * also supply the useSingleTransaction option to avoid table locking.
             *
             * E.g.
             * 'mysql' => [
             *       ...
             *      'dump' => [
             *           'useSingleTransaction' => true,
             *       ],
             * ],
             *
             * For a complete list of available customization options, see https://github.com/spatie/db-dumper
             */
            'databases' => [
                env('DB_CONNECTION', 'pgsql'),
            ],
        ],

        /*
         * The database dump can be compressed to decrease disk space usage.
         *
         * Out of the box Laravel-backup supplies
         * Spatie\DbDumper\Compressors\GzipCompressor::class.
         *
         * You can also create custom compressor. More info on that here:
         * https://github.com/spatie/db-dumper#using-compression
         *
         * If you do not want any compressor at all, set it to null.
         */
        'database_dump_compressor' => null,

        /*
         * If specified, the database dumped file name will contain a timestamp (e.g.: 'Y-m-d-H-i-s').
         */
        'database_dump_file_timestamp_format' => null,

        /*
         * The base of the dump filename, either 'database' or 'connection'
         *
         * If 'database' (default), the dumped filename will contain the database name.
         * If 'connection', the dumped filename will contain the connection name.
         */
        'database_dump_filename_base' => 'database',

        /*
         * The file extension used for the database dump files.
         *
         * If not specified, the file extension will be .archive for MongoDB and .sql for all other databases
         * The file extension should be specified without a leading .
         */
        'database_dump_file_extension' => '',

        'destination' => [
            /*
             * The compression algorithm to be used for creating the zip archive.
             *
             * If backing up only database, you may choose gzip compression for db dump and no compression at zip.
             *
             * Some common algorithms are listed below:
             * ZipArchive::CM_STORE (no compression at all; set 0 as compression level)
             * ZipArchive::CM_DEFAULT
             * ZipArchive::CM_DEFLATE
             * ZipArchive::CM_BZIP2
             * ZipArchive::CM_XZ
             *
             * For more check https://www.php.net/manual/zip.constants.php and confirm it's supported by your system.
             */
            'compression_method' => ZipArchive::CM_DEFAULT,

            /*
             * The compression level corresponding to the used algorithm; an integer between 0 and 9.
             *
             * Check supported levels for the chosen algorithm, usually 1 means the fastest and weakest compression,
             * while 9 the slowest and strongest one.
             *
             * Setting of 0 for some algorithms may switch to the strongest compression.
             */
            'compression_level' => 9,

            /*
             * The filename prefix used for the backup zip file.
             */
            'filename_prefix' => '',

            /*
             * The disk names on which the backups will be stored.
             * Production: add 'backups' (S3/R2) via BACKUP_DISKS for offsite storage.
             */
            'disks' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('BACKUP_DISKS', 'local'))))) ?: ['local'],

            /*
             * Determines whether to allow backups to continue when some targets fail instead of failing completely.
             */
            'continue_on_failure' => false,
        ],

        /*
         * The directory where the temporary files will be stored.
         */
        'temporary_directory' => storage_path('app/backup-temp'),

        /*
         * The password to be used for archive encryption.
         * Set to `null` to disable encryption.
         */
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),

        /*
         * The encryption algorithm to be used for archive encryption.
         * Set to 'none' to disable encryption.
         *
         * Supported: 'none', 'default', 'aes128', 'aes192', 'aes256'
         *
         * When set to 'default', we'll use AES-256 if available on your system.
         */
        'encryption' => 'default',

        /*
         * After creating the zip, verify it can be opened and contains files.
         * Recommended for critical backups but adds a small overhead.
         *
         * Activé : sur une archive base-seule de ~1,5 Mo le surcoût est
         * négligeable, et un zip tronqué ou vide échoue immédiatement au lieu
         * d'être découvert le jour de la restauration.
         */
        'verify_backup' => (bool) env('BACKUP_VERIFY', true),

        /*
         * The number of attempts, in case the backup command encounters an exception
         */
        'tries' => 1,

        /*
         * The number of seconds to wait before attempting a new backup if the previous try failed
         * Set to `0` for none
         */
        'retry_delay' => 0,
    ],

    /*
     * You can get notified when specific events occur. Out of the box you can use 'mail' and 'slack'.
     * For Slack you need to install laravel/slack-notification-channel.
     *
     * You can also use your own notification classes, just make sure the class is named after one of
     * the `Spatie\Backup\Notifications\Notifications` classes.
     */
    'notifications' => [
        /*
         * Seuls les ÉCHECS déclenchent un email Spatie. Les trois notifications
         * « tout va bien » sont volontairement muettes : avec run + clean +
         * monitor quotidiens elles produisaient quatre emails par jour, et un
         * canal saturé de succès est un canal où l'échec passe inaperçu.
         *
         * Le signal de bonne santé quotidien reste assuré par
         * `SendBackupByEmailListener`, plus utile puisqu'il prouve à la fois que
         * l'archive existe et qu'elle est téléchargeable (lien R2 signé 48h).
         *
         * @see \App\Listeners\SendBackupByEmailListener
         */
        'notifications' => [
            BackupHasFailedNotification::class => ['mail'],
            UnhealthyBackupWasFoundNotification::class => ['mail'],
            CleanupHasFailedNotification::class => ['mail'],
            BackupWasSuccessfulNotification::class => [],
            HealthyBackupWasFoundNotification::class => [],
            CleanupWasSuccessfulNotification::class => [],
        ],

        /*
         * Here you can specify the notifiable to which the notifications should be sent. The default
         * notifiable will use the variables specified in this config file.
         */
        'notifiable' => Notifiable::class,

        'mail' => [
            'to' => env('BACKUP_NOTIFICATION_MAIL', env('MAIL_FROM_ADDRESS', 'your@example.com')),

            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Example'),
            ],
        ],

        'slack' => [
            'webhook_url' => '',

            /*
             * If this is set to null the default channel of the webhook will be used.
             */
            'channel' => null,

            'username' => null,

            'icon' => null,
        ],

        'discord' => [
            'webhook_url' => '',

            /*
             * If this is an empty string, the name field on the webhook will be used.
             */
            'username' => '',

            /*
             * If this is an empty string, the avatar on the webhook will be used.
             */
            'avatar_url' => '',
        ],

        /*
         * A generic webhook channel that POSTs JSON to a URL.
         * Useful for Mattermost, Microsoft Teams, or custom integrations.
         */
        'webhook' => [
            'url' => '',
        ],
    ],

    /*
     * Here you can specify which backups should be monitored.
     * If a backup does not meet the specified requirements the
     * UnHealthyBackupWasFound event will be fired.
     */
    'monitor_backups' => [
        [
            'name' => env('APP_NAME', 'laravel-backup'),
            'disks' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('BACKUP_DISKS', 'local'))))) ?: ['local'],
            'health_checks' => [
                MaximumAgeInDays::class => 1,
                MaximumStorageInMegabytes::class => 5000,
            ],
        ],

        /*
        [
            'name' => 'name of the second app',
            'disks' => ['local', 's3'],
            'health_checks' => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],
        */
    ],

    'cleanup' => [
        /*
         * The strategy that will be used to cleanup old backups. The default strategy
         * will keep all backups for a certain amount of days. After that period only
         * a daily backup will be kept. After that period only weekly backups will
         * be kept and so on.
         *
         * No matter how you configure it the default strategy will never
         * delete the newest backup.
         */
        'strategy' => DefaultStrategy::class,

        'default_strategy' => [
            /*
             * Rétention PLATE : on garde tous les backups des 30 derniers jours,
             * puis plus rien. Les quatre paliers du « grandfather-father-son » de
             * Spatie (daily/weekly/monthly/yearly) sont donc neutralisés à 0 —
             * `DefaultStrategy::calculateDateRanges()` les enchaîne à partir de
             * `keep_all_backups_for_days`, si bien que la fenêtre annuelle se
             * termine elle aussi à J-30 et que `removeBackupsOlderThan()` purge
             * tout ce qui la dépasse.
             *
             * Garde-fou intégré à Spatie : le backup le PLUS RÉCENT n'est jamais
             * supprimé, même s'il dépasse la fenêtre. Si la planification
             * s'arrête, il reste toujours une copie exploitable.
             */
            'keep_all_backups_for_days' => (int) env('BACKUP_KEEP_DAYS', 30),
            'keep_daily_backups_for_days' => 0,
            'keep_weekly_backups_for_weeks' => 0,
            'keep_monthly_backups_for_months' => 0,
            'keep_yearly_backups_for_years' => 0,

            /*
             * After cleaning up the backups remove the oldest backup until
             * this amount of megabytes has been reached.
             * Set null for unlimited size.
             */
            'delete_oldest_backups_when_using_more_megabytes_than' => (int) env('BACKUP_MAX_STORAGE_MB', 5000),
        ],

        /*
         * The number of attempts, in case the cleanup command encounters an exception
         */
        'tries' => 1,

        /*
         * The number of seconds to wait before attempting a new cleanup if the previous try failed
         * Set to `0` for none
         */
        'retry_delay' => 0,
    ],

];
