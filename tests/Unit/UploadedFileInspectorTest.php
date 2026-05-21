<?php

declare(strict_types=1);

use App\Support\UploadedFileInspector;
use Illuminate\Http\UploadedFile;

it('maps verified mime types to safe storage extensions', function (): void {
    expect(UploadedFileInspector::extensionForMime('image/jpeg'))->toBe('jpg')
        ->and(UploadedFileInspector::extensionForMime('application/pdf'))->toBe('pdf')
        ->and(UploadedFileInspector::extensionForMime('audio/webm'))->toBe('webm');
});

it('rejects dangerous filename extensions', function (): void {
    expect(fn () => UploadedFileInspector::rejectDangerousFilename('shell.php.jpg'))
        ->toThrow(InvalidArgumentException::class);
});

it('accepts a real jpeg via assertSafeRasterImage', function (): void {
    $file = UploadedFile::fake()->image('ok.jpg', 100, 100);

    UploadedFileInspector::assertSafeRasterImage($file);

    expect(UploadedFileInspector::detectContentMime($file))->toBe('image/jpeg');
});

it('rejects php bytes with jpg extension via assertSafeRasterImage', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'kh_unit_');
    file_put_contents($path, '<?php echo 1; ?>');
    $file = new UploadedFile($path, 'bad.jpg', 'image/jpeg', null, true);

    expect(fn () => UploadedFileInspector::assertSafeRasterImage($file))
        ->toThrow(InvalidArgumentException::class, 'image valide');
});
