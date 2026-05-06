<?php

declare(strict_types=1);

use App\Enums\AdminAsyncExportType;
use App\Jobs\Admin\ProcessAdminAsyncExportJob;
use App\Models\AdminQueuedExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('creates a queued export artifact when the metrics csv job runs', function (): void {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();

    ProcessAdminAsyncExportJob::dispatchSync($admin->id, AdminAsyncExportType::MetricsCsv);

    $export = AdminQueuedExport::query()->first();
    expect($export)->not->toBeNull()
        ->and($export->user_id)->toBe($admin->id)
        ->and(Storage::disk('local')->exists($export->path))->toBeTrue();

    expect($admin->notifications()->count())->toBe(1);
});

it('forbids downloading another user export', function (): void {
    Storage::fake('local');
    $owner = User::factory()->admin()->create();
    $intruder = User::factory()->admin()->create();

    $export = AdminQueuedExport::query()->create([
        'user_id' => $owner->id,
        'disk' => 'local',
        'path' => 'admin-queued-exports/'.$owner->id.'/test.csv',
        'download_name' => 'test.csv',
        'mime_type' => 'text/csv',
        'expires_at' => now()->addDay(),
    ]);
    Storage::disk('local')->put($export->path, 'x');

    $url = URL::temporarySignedRoute(
        'admin.queued-exports.download',
        now()->addDay(),
        ['export' => $export->getKey()],
    );

    $this->actingAs($intruder)->get($url)->assertForbidden();
});

it('allows download with a valid signature and matching user', function (): void {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();
    $export = AdminQueuedExport::query()->create([
        'user_id' => $admin->id,
        'disk' => 'local',
        'path' => 'admin-queued-exports/'.$admin->id.'/abc.csv',
        'download_name' => 'metrics.csv',
        'mime_type' => 'text/csv',
        'expires_at' => now()->addDay(),
    ]);
    Storage::disk('local')->put($export->path, 'col1,col2');

    $url = URL::temporarySignedRoute(
        'admin.queued-exports.download',
        now()->addDay(),
        ['export' => $export->getKey()],
    );

    $this->actingAs($admin)->get($url)->assertOk();
});
