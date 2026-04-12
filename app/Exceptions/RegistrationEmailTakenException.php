<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\User;
use Illuminate\Contracts\Support\Responsable;
use Symfony\Component\HttpFoundation\Response;

final class RegistrationEmailTakenException extends \RuntimeException implements Responsable
{
    public function __construct(
        public readonly string $registrationConflict,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function forExistingUser(User $user): self
    {
        if (filled($user->clerk_id)) {
            $code = 'use_clerk_sso';
        } elseif ($user->email_verified_at === null) {
            $code = 'complete_email_verification';
        } else {
            $code = 'use_login_or_reset';
        }

        $message = match ($code) {
            'use_clerk_sso' => __('auth.registration_email_taken_use_clerk'),
            'complete_email_verification' => __('auth.registration_email_taken_verify_email'),
            default => __('auth.registration_email_taken_login_or_reset'),
        };

        return new self($code, $message);
    }

    public function toResponse($request): Response
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'email' => [$this->getMessage()],
            ],
            'registration_conflict' => $this->registrationConflict,
        ], 422);
    }
}
