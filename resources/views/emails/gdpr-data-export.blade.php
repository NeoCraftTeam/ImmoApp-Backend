@extends('emails.owner-layout')

@section('title', 'Export de vos données personnelles — RGPD')

@section('preheader', 'Votre archive de données personnelles est disponible. Téléchargez-la avant l\'expiration du lien sécurisé.')

@section('content')

    <h1>Vos données personnelles</h1>

    <p class="text">
        Bonjour <strong>{{ $authorName }}</strong>,
    </p>

    <p class="text">
        Suite à votre demande d'accès à vos données personnelles, vous trouverez
        <strong>en pièce jointe</strong> un fichier JSON contenant l'intégralité
        des informations que nous détenons sur votre compte.
    </p>

    {{-- Attachment badge --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin-top: 24px;">
        <tr>
            <td style="
                background-color: #f0fdfa;
                border: 1px solid #99f6e4;
                border-radius: 10px;
                padding: 16px 20px;
            ">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                    <tr>
                        <td width="40" valign="middle">
                            <div style="font-size: 28px; line-height: 1;">📎</div>
                        </td>
                        <td valign="middle" style="padding-left: 12px;">
                            <p style="margin: 0; font-size: 14px; font-weight: 700; color: #0f172a;">
                                keyhome-mes-donnees-{{ now()->format('Y-m-d') }}.json
                            </p>
                            <p style="margin: 3px 0 0 0; font-size: 12px; color: #64748b;">
                                Fichier JSON · Données personnelles complètes
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Contents list --}}
    <p style="margin: 24px 0 12px 0; font-size: 14px; font-weight: 700; color: #0f172a;">
        Ce fichier contient :
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
        @foreach ([
            ['📋', 'Informations de compte', 'Nom, email, téléphone, date d\'inscription'],
            ['🏠', 'Annonces publiées', 'Titre, statut, prix, date de création'],
            ['💳', 'Historique des paiements', 'Montants, statuts et moyens de paiement'],
            ['⭐', 'Crédits & transactions', 'Points accumulés et dépensés'],
            ['💬', 'Avis déposés', 'Notes et commentaires laissés sur les annonces'],
            ['🔔', 'Préférences email', 'Paramètres de notification'],
        ] as [$icon, $label, $desc])
        <tr>
            <td style="padding: 8px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; width: 32px;">
                <span style="font-size: 15px;">{{ $icon }}</span>
            </td>
            <td style="padding: 8px 0 8px 10px; border-bottom: 1px solid #f1f5f9;">
                <p style="margin: 0; font-size: 13px; font-weight: 600; color: #0f172a;">{{ $label }}</p>
                <p style="margin: 2px 0 0 0; font-size: 12px; color: #64748b;">{{ $desc }}</p>
            </td>
        </tr>
        @endforeach
    </table>

    {{-- RGPD rights info box --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="
        border-collapse: collapse;
        margin-top: 24px;
        background-color: #f8fafc;
        border-left: 4px solid #0d9488;
        border-radius: 0 8px 8px 0;
    ">
        <tr>
            <td style="padding: 16px 20px;">
                <p style="margin: 0 0 6px 0; font-size: 13px; font-weight: 700; color: #0f172a;">
                    🛡️ Vos droits RGPD
                </p>
                <p style="margin: 0; font-size: 13px; color: #475569; line-height: 1.6;">
                    Conformément au Règlement Général sur la Protection des Données, vous pouvez
                    à tout moment demander la rectification ou la suppression de vos données.
                    Pour exercer ces droits, contactez-nous à
                    <a href="mailto:privacy@keyhome.app" style="color: #0d9488; text-decoration: none;">
                        privacy@keyhome.app</a>.
                </p>
            </td>
        </tr>
    </table>

    <p class="text" style="margin-top: 24px; font-size: 13px; color: #94a3b8;">
        Cet email a été généré automatiquement suite à votre demande depuis votre espace personnel.
        Si vous n'êtes pas à l'origine de cette demande, veuillez contacter notre support immédiatement.
    </p>

@endsection
