<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Enterprise Grade PII Masking Processor.
 *
 * Automatically masks sensitive information like emails, phone numbers,
 * and passwords in log messages and context to ensure GDPR compliance.
 */
class MaskSensitiveDataProcessor implements ProcessorInterface
{
    /**
     * Sensitive keys to mask in context arrays.
     */
    private array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'access_token',
        'refresh_token',
        'card_number',
        'cvv',
        'api_key',
        'secret',
        'key',
        'email',
        'phone',
        'phone_number',
        'authorization',
        'cookie',
        'session_id',
        'webhook_secret',
        'verif_hash',
        'x-webauthn-token',
    ];

    /**
     * Regex patterns for PII in strings.
     */
    private array $patterns = [
        // Email pattern
        '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i' => '[EMAIL_MASKED]',
        // Phone pattern (basic)
        '/(?:\+?\d{1,3}[- ]?)?\(?\d{3}\)?[- ]?\d{3}[- ]?\d{4}/' => '[PHONE_MASKED]',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $record->context;
        $message = $record->message;

        // Mask context
        if (!empty($context)) {
            $context = $this->maskArray($context);
        }

        // Mask message
        foreach ($this->patterns as $pattern => $replacement) {
            $message = preg_replace($pattern, (string) $replacement, (string) $message);
        }

        return $record->with(
            message: $message,
            context: $context
        );
    }

    /**
     * Recursively mask sensitive keys in an array.
     */
    private function maskArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->maskArray($value);
            } elseif (is_string($key) && in_array(strtolower($key), $this->sensitiveKeys)) {
                $data[$key] = '********';
            } elseif (is_string($value)) {
                foreach ($this->patterns as $pattern => $replacement) {
                    $data[$key] = preg_replace($pattern, (string) $replacement, $value);
                }
            }
        }

        return $data;
    }
}
