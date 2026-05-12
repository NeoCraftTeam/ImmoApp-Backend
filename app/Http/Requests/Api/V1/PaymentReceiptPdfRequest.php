<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Optional locale display for single-receipt PDFs (mirrors {@see PaymentController::export}).
 */
final class PaymentReceiptPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'currency' => ['nullable', 'string', 'size:3'],
            'rate' => ['nullable', 'numeric', 'min:0.0000000001'],
        ];
    }
}
