<?php

declare(strict_types=1);

use App\Support\ChatAvatarUrl;
use Illuminate\Support\Facades\Storage;

it('returns null for empty input', function (): void {
    expect(ChatAvatarUrl::resolve(null))->toBeNull();
    expect(ChatAvatarUrl::resolve(''))->toBeNull();
    expect(ChatAvatarUrl::resolve('   '))->toBeNull();
});

it('normalizes protocol-relative URLs to https', function (): void {
    expect(ChatAvatarUrl::resolve('//img.clerk.com/v/test/photo'))
        ->toBe('https://img.clerk.com/v/test/photo');
});

it('preserves absolute http(s) URLs', function (): void {
    expect(ChatAvatarUrl::resolve('https://lh3.googleusercontent.com/a/x'))
        ->toBe('https://lh3.googleusercontent.com/a/x');
    expect(ChatAvatarUrl::resolve('HTTP://example.com/z'))
        ->toBe('HTTP://example.com/z');
});

it('resolves relative keys on the app media disk', function (): void {
    Storage::fake('public');
    config(['filesystems.app_media_disk' => 'public']);

    $url = ChatAvatarUrl::resolve('avatars/u1/face.webp');

    expect($url)->not->toBeNull()->toEndWith('avatars/u1/face.webp');
});
