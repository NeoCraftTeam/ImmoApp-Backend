<?php

declare(strict_types=1);

use App\Listeners\SendBackupByEmailListener;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Config\Config;
use Spatie\Backup\Events\BackupWasSuccessful;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;

/**
 * La rétention n'est PAS surchargée ici : les tests valident les valeurs
 * livrées dans config/backup.php (fenêtre plate de 30 jours). Seuls le nom
 * de l'archive et le disque de destination sont redirigés vers un faux disque.
 */
beforeEach(function (): void {
    Storage::fake('backups');

    config([
        'backup.backup.name' => 'keyhome-test',
        'backup.backup.destination.disks' => ['backups'],
    ]);

    // `Config` est un singleton scoped construit depuis config('backup') :
    // il faut l'oublier pour qu'il relise les valeurs ci-dessus.
    app()->forgetInstance(Config::class);

    Carbon::setTestNow(Carbon::parse('2026-09-04 03:00:00'));
});

afterEach(function (): void {
    Carbon::setTestNow(null);
});

/**
 * @return array<string, string>
 */
function backupArchivesAgedInDays(array $ages): array
{
    $disk = Storage::disk('backups');

    $paths = [];

    foreach ($ages as $label => $days) {
        $paths[$label] = 'keyhome-test/'.Carbon::now()->subDays($days)->format('Y-m-d-H-i-s').'.zip';
        $disk->put($paths[$label], 'fake-zip-content');
    }

    return $paths;
}

it('purge les archives de plus de 30 jours et garde celles de la fenêtre', function (): void {
    $paths = backupArchivesAgedInDays([
        'today' => 0,
        'j5' => 5,
        'j29' => 29,
        'j31' => 31,
        'j400' => 400,
    ]);

    $this->artisan('backup:clean --disable-notifications')->assertSuccessful();

    $disk = Storage::disk('backups');

    expect($disk->exists($paths['today']))->toBeTrue()
        ->and($disk->exists($paths['j5']))->toBeTrue()
        ->and($disk->exists($paths['j29']))->toBeTrue()
        ->and($disk->exists($paths['j31']))->toBeFalse()
        ->and($disk->exists($paths['j400']))->toBeFalse();
});

it("ne supprime jamais l'archive la plus récente, même hors fenêtre", function (): void {
    $paths = backupArchivesAgedInDays(['j400' => 400]);

    $this->artisan('backup:clean --disable-notifications')->assertSuccessful();

    expect(Storage::disk('backups')->exists($paths['j400']))->toBeTrue();
});

it('conserve chaque archive du jour sans dédoublonner dans la fenêtre', function (): void {
    $disk = Storage::disk('backups');

    $morning = 'keyhome-test/'.Carbon::now()->subDays(3)->setTime(2, 0)->format('Y-m-d-H-i-s').'.zip';
    $evening = 'keyhome-test/'.Carbon::now()->subDays(3)->setTime(20, 0)->format('Y-m-d-H-i-s').'.zip';

    $disk->put($morning, 'fake-zip-content');
    $disk->put($evening, 'fake-zip-content');

    $this->artisan('backup:clean --disable-notifications')->assertSuccessful();

    expect($disk->exists($morning))->toBeTrue()
        ->and($disk->exists($evening))->toBeTrue();
});

it('planifie run puis clean puis monitor, avec la sortie tracée', function (): void {
    $events = collect(app(Schedule::class)->events());

    $find = fn (string $needle) => $events->first(
        fn ($event): bool => str_contains((string) $event->command, $needle)
    );

    $expected = [
        'backup:run --only-db' => '0 2,8,14,20 * * *',
        'backup:run --only-files' => '20 2 * * 0',
        'backup:clean' => '0 3 * * *',
        'backup:monitor' => '0 4 * * *',
    ];

    foreach ($expected as $needle => $expression) {
        $event = $find($needle);

        expect($event)->not->toBeNull("La commande `{$needle}` n'est pas planifiée.")
            ->and($event->expression)->toBe($expression)
            ->and($event->output)->toBe(storage_path('logs/backup.log'))
            ->and($event->shouldAppendOutput)->toBeTrue();
    }
});

it("court-circuite l'email récapitulatif quand BACKUP_SEND_BY_MAIL est désactivé", function (): void {
    config(['backup.send_backup_by_mail' => false]);

    Log::shouldReceive('warning')->never();
    Log::shouldReceive('error')->never();

    (new SendBackupByEmailListener)->handle(
        new BackupWasSuccessful(diskName: 'backups', backupName: 'keyhome-test')
    );
});

it("trace un avertissement quand l'envoi est actif mais qu'aucune archive n'existe", function (): void {
    config([
        'backup.send_backup_by_mail' => true,
        'backup.notifications.mail.to' => 'admin@example.com',
    ]);

    Log::shouldReceive('warning')->once();

    (new SendBackupByEmailListener)->handle(
        new BackupWasSuccessful(diskName: 'backups', backupName: 'keyhome-test')
    );
});

/**
 * Recharge config/backup.php avec les variables d'environnement fournies,
 * puis restaure l'état initial du dépôt d'environnement.
 *
 * @param  array<string, string>  $env
 * @return array<mixed>
 */
function backupConfigWithEnv(array $env): array
{
    $repository = Env::getRepository();

    $previous = [];

    foreach ($env as $key => $value) {
        $previous[$key] = $repository->has($key) ? $repository->get($key) : null;
        $repository->set($key, $value);
    }

    try {
        return require config_path('backup.php');
    } finally {
        foreach ($previous as $key => $value) {
            $value === null ? $repository->clear($key) : $repository->set($key, $value);
        }
    }
}

it("exclut les médias de l'archive quand ils vivent sur un stockage objet", function (): void {
    $config = backupConfigWithEnv([
        'MEDIA_DISK' => 'r2',
        'APP_MEDIA_DISK' => 'r2',
        'FILESYSTEM_DISK' => 'r2',
    ]);

    expect($config['backup']['source']['files']['exclude'])
        ->toContain(storage_path('app/public'));
});

it("garde les médias dans l'archive quand le disque média est local", function (): void {
    $config = backupConfigWithEnv([
        'MEDIA_DISK' => 'public',
        'APP_MEDIA_DISK' => 'public',
        'FILESYSTEM_DISK' => 'local',
    ]);

    expect($config['backup']['source']['files']['include'])
        ->toContain(storage_path('app'))
        ->and($config['backup']['source']['files']['exclude'])
        ->not->toContain(storage_path('app/public'));
});

it("n'archive jamais les identifiants de service ni les fichiers reproductibles", function (): void {
    $exclude = config('backup.backup.source.files.exclude');

    expect($exclude)
        ->toContain(storage_path('app/firebase-credentials.json'))
        ->toContain(storage_path('app/seeder-images'))
        ->toContain(storage_path('app/backup-temp'))
        ->and(config('backup.backup.source.files.include'))
        ->not->toContain(base_path());
});

it('notifie les échecs par email et laisse les succès muets', function (): void {
    $notifications = config('backup.notifications.notifications');

    expect($notifications[BackupHasFailedNotification::class])->toBe(['mail'])
        ->and($notifications[UnhealthyBackupWasFoundNotification::class])->toBe(['mail'])
        ->and($notifications[CleanupHasFailedNotification::class])->toBe(['mail'])
        ->and($notifications[BackupWasSuccessfulNotification::class])->toBe([])
        ->and($notifications[HealthyBackupWasFoundNotification::class])->toBe([])
        ->and($notifications[CleanupWasSuccessfulNotification::class])->toBe([]);
});

it("n'envoie qu'un seul email par jour malgré quatre sauvegardes", function (): void {
    config([
        'backup.send_backup_by_mail' => true,
        'backup.notifications.mail.to' => 'admin@example.com',
    ]);

    $disk = Storage::disk('backups');
    $disk->put('keyhome-test/2026-09-04-02-00-01.zip', 'fake-zip-content');

    // Le disque falsifié est local : `temporaryUrl()` y lève une exception.
    $signed = Mockery::mock($disk)->makePartial();
    $signed->shouldReceive('temporaryUrl')->andReturn('https://example.test/signed');
    Storage::set('backups', $signed);

    Mail::shouldReceive('send')->once();

    $event = new BackupWasSuccessful(diskName: 'backups', backupName: 'keyhome-test');

    foreach ([2, 8, 14, 20] as $hour) {
        Carbon::setTestNow(Carbon::parse('2026-09-04')->setTime($hour, 0));

        (new SendBackupByEmailListener)->handle($event);
    }
});

it("réarme l'email le lendemain", function (): void {
    config([
        'backup.send_backup_by_mail' => true,
        'backup.notifications.mail.to' => 'admin@example.com',
    ]);

    $disk = Storage::disk('backups');
    $disk->put('keyhome-test/2026-09-04-02-00-01.zip', 'fake-zip-content');

    $signed = Mockery::mock($disk)->makePartial();
    $signed->shouldReceive('temporaryUrl')->andReturn('https://example.test/signed');
    Storage::set('backups', $signed);

    Mail::shouldReceive('send')->twice();

    $event = new BackupWasSuccessful(diskName: 'backups', backupName: 'keyhome-test');

    Carbon::setTestNow(Carbon::parse('2026-09-04 20:00:00'));
    (new SendBackupByEmailListener)->handle($event);

    Carbon::setTestNow(Carbon::parse('2026-09-05 02:00:00'));
    (new SendBackupByEmailListener)->handle($event);
});
