<?php

declare(strict_types=1);

use App\Rules\VerifiedUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

/** True when the rule accepts the given value on a `file` field. */
function verifiedUploadPasses(mixed $value): bool
{
    return Validator::make(
        ['file' => $value],
        ['file' => [new VerifiedUpload]]
    )->passes();
}

/** Wraps arbitrary bytes in a real, valid UploadedFile for content inspection. */
function fakeUploadWithBytes(string $bytes, string $clientName): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'kh_vu_');
    file_put_contents($path, $bytes);

    return new UploadedFile($path, $clientName, null, null, true);
}

it('accepts a real jpeg', function (): void {
    expect(verifiedUploadPasses(UploadedFile::fake()->image('ok.jpg', 100, 100)))->toBeTrue();
});

it('accepts a real pdf', function (): void {
    $file = fakeUploadWithBytes("%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF", 'contract.pdf');

    expect(verifiedUploadPasses($file))->toBeTrue();
});

it('rejects a value that is not an uploaded file', function (): void {
    expect(verifiedUploadPasses('not-a-file'))->toBeFalse();
});

it('rejects a dangerous double-extension filename', function (): void {
    $file = UploadedFile::fake()->image('shell.php.jpg', 10, 10);

    expect(verifiedUploadPasses($file))->toBeFalse();
});

it('lets an unrecognised but safely-named file through (heic/xlsx pass-through)', function (): void {
    // ZIP magic bytes → finfo detects application/zip, which is not deep-parsed;
    // the filename guard is the only gate, and it is safe. No regression for xlsx/heic.
    $file = fakeUploadWithBytes("PK\x03\x04arbitrary-archive-bytes", 'evidence.xlsx');

    expect(verifiedUploadPasses($file))->toBeTrue();
});
