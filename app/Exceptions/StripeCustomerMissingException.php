<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when Stripe answers `resource_missing` for a Customer id — i.e. the
 * locally stored `users.stripe_id` points to a Customer that no longer exists
 * (typically created with test keys, or deleted in the Stripe dashboard).
 *
 * Callers are expected to self-heal: forget the stale id, create a fresh
 * Customer and retry the operation once.
 */
final class StripeCustomerMissingException extends PaymentGatewayException {}
