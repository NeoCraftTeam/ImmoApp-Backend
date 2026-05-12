<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when TrustScoreService::compute() is invoked for a user who has not
 * given explicit consent to TrustScore computation (GDPR opt-in).
 */
final class TrustScoreConsentMissingException extends RuntimeException {}
