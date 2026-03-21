<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Audience for rows in `anonymous_survey_responses` (no user_id stored).
 * Lets admins distinguish true public guests from authenticated users who chose anonymous mode,
 * and see bailleur vs client vs admin for the latter — without storing personally identifiable data.
 */
enum SurveyAnonymousAudience: string
{
    case PublicGuest = 'public_guest';
    case IncognitoBailleur = 'incognito_bailleur';
    case IncognitoClient = 'incognito_client';
    case IncognitoAdmin = 'incognito_admin';

    public function adminLabel(): string
    {
        return match ($this) {
            self::PublicGuest => 'Invité (lien public)',
            self::IncognitoBailleur => 'Bailleur (réponse anonyme)',
            self::IncognitoClient => 'Client (réponse anonyme)',
            self::IncognitoAdmin => 'Administrateur (réponse anonyme)',
        };
    }

    public static function fromUserRole(UserRole $role): self
    {
        return match ($role) {
            UserRole::AGENT => self::IncognitoBailleur,
            UserRole::CUSTOMER => self::IncognitoClient,
            UserRole::ADMIN => self::IncognitoAdmin,
        };
    }
}
