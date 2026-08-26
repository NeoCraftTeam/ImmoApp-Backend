<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Machine-stable codes for successful API operations.
 *
 * The `value` is the code emitted in the `code` field of the success envelope;
 * {@see message()} resolves the localized, user-facing copy from lang/fr/success.php.
 */
enum SuccessCode: string
{
    case Logout = 'LOGOUT';
    case ViewingConfirmed = 'VIEWING_CONFIRMED';
    case AvailabilityScheduleCreated = 'AVAILABILITY_SCHEDULE_CREATED';
    case AvailabilityScheduleUpdated = 'AVAILABILITY_SCHEDULE_UPDATED';

    /**
     * User-facing success message for this code.
     *
     * Pinned to the French catalog to match the hardcoded French envelope
     * messages in bootstrap/app.php: a non-fr request locale (en/pt/… are
     * supported and negotiated by LocaleResolver) has no `success` catalog,
     * so an unpinned lookup would leak the raw `success.<CODE>` key as copy.
     * The machine-stable `code` field remains the localization contract for
     * clients that need to translate.
     */
    public function message(): string
    {
        $message = trans('success.'.$this->value, [], 'fr');

        return is_string($message) ? $message : $this->value;
    }
}
