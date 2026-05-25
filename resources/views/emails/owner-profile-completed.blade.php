@extends('emails.owner-layout')

@section('title', 'Profil complété — publiez votre première annonce')

@section('preheader', 'Votre profil bailleur est complet. Publiez votre premier bien immobilier et commencez à recevoir des demandes de visite.')

@section('content')

    {{-- Hero banner --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin-bottom: 28px;">
        <tr>
            <td align="center" style="
                background: linear-gradient(135deg, #0f766e 0%, #0d9488 60%, #14b8a6 100%);
                border-radius: 12px;
                padding: 32px 24px;
            ">
                <div style="font-size: 44px; line-height: 1; margin-bottom: 12px;">✅</div>
                <p style="margin: 0; font-size: 22px; font-weight: 800; color: #ffffff; line-height: 1.3;">
                    Profil complété, {{ $firstName }} !
                </p>
                <p style="margin: 8px 0 0 0; font-size: 14px; color: #99f6e4; font-weight: 500;">
                    Votre espace bailleur est prêt à accueillir des locataires
                </p>
            </td>
        </tr>
    </table>

    <p class="text">
        Excellent travail. Un profil complet inspire confiance aux locataires et augmente significativement le taux
        de réponse à vos annonces. Les bailleurs avec un profil rempli reçoivent en moyenne
        <strong>3× plus de demandes de visite</strong>.
    </p>

    {{-- Next steps --}}
    <p class="text" style="margin-top: 24px; font-weight: 700; color: #0f172a; font-size: 15px;">
        Prochaine étape : publiez votre premier bien
    </p>

    <table style="width: 100%; border-collapse: collapse; margin-top: 12px;">
        <tr>
            <td style="padding: 14px 0; border-bottom: 1px solid #f0fdf4; vertical-align: top; width: 32px;">
                <span style="
                    display: inline-block; width: 24px; height: 24px; line-height: 24px;
                    text-align: center; background-color: #0d9488; color: #fff;
                    border-radius: 50%; font-weight: 800; font-size: 12px;
                ">1</span>
            </td>
            <td style="padding: 14px 0 14px 12px; border-bottom: 1px solid #f0fdf4;">
                <strong style="color: #0f172a;">Décrivez votre bien</strong><br>
                <span style="color: #6b7280; font-size: 13px;">
                    Titre accrocheur, description détaillée, type de bien, surface, loyer en FCFA.
                </span>
            </td>
        </tr>
        <tr>
            <td style="padding: 14px 0; border-bottom: 1px solid #f0fdf4; vertical-align: top; width: 32px;">
                <span style="
                    display: inline-block; width: 24px; height: 24px; line-height: 24px;
                    text-align: center; background-color: #0d9488; color: #fff;
                    border-radius: 50%; font-weight: 800; font-size: 12px;
                ">2</span>
            </td>
            <td style="padding: 14px 0 14px 12px; border-bottom: 1px solid #f0fdf4;">
                <strong style="color: #0f172a;">Ajoutez des photos de qualité</strong><br>
                <span style="color: #6b7280; font-size: 13px;">
                    Les annonces avec 5+ photos reçoivent 4× plus de clics. Lumière naturelle recommandée.
                </span>
            </td>
        </tr>
        <tr>
            <td style="padding: 14px 0; vertical-align: top; width: 32px;">
                <span style="
                    display: inline-block; width: 24px; height: 24px; line-height: 24px;
                    text-align: center; background-color: #0d9488; color: #fff;
                    border-radius: 50%; font-weight: 800; font-size: 12px;
                ">3</span>
            </td>
            <td style="padding: 14px 0 14px 12px;">
                <strong style="color: #0f172a;">Configurez vos créneaux de visite</strong><br>
                <span style="color: #6b7280; font-size: 13px;">
                    Les bailleurs qui proposent des créneaux reçoivent des demandes confirmées directement dans leur agenda.
                </span>
            </td>
        </tr>
    </table>

    <div style="margin-top: 32px;">
        @include('emails.partials.button', [
            'url'   => $newAdUrl,
            'label' => 'Publier ma première annonce',
            'color' => '#0d9488',
            'width' => 260,
        ])
    </div>

    <p style="margin-top: 12px; text-align: center; font-size: 13px; color: #6b7280;">
        ou <a href="{{ $panelUrl }}" style="color: #0d9488; font-weight: 600;">accéder à mon tableau de bord →</a>
    </p>

    <p class="fallback" style="margin-top: 32px; font-size: 13px;">
        Une question ? Notre équipe est disponible à
        <a href="mailto:support@keyhome.app" class="link">support@keyhome.app</a>.
    </p>

@endsection
