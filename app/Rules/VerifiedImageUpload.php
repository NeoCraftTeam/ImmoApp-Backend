<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\UploadedFileInspector;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

final class VerifiedImageUpload implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value instanceof UploadedFile || !$value->isValid()) {
            $fail('Le fichier téléversé est invalide.');

            return;
        }

        try {
            UploadedFileInspector::assertSafeRasterImage($value);
        } catch (InvalidArgumentException $exception) {
            $fail($exception->getMessage());
        }
    }
}
