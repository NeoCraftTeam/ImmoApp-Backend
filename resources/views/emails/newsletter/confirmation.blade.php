@extends('emails.layout')

@section('title', 'Bienvenue sur la newsletter KeyHome')

@section('content')

    <h1>Bienvenue sur la newsletter KeyHome{{ $name ? ', ' . $name : '' }} !</h1>

    <p class="text">
        Votre abonnement à la newsletter KeyHome est bien confirmé. Vous recevrez désormais
        nos meilleures offres immobilières, les tendances du marché et nos conseils d'experts
        directement dans votre boîte mail.
    </p>

    <p class="text" style="margin-top: 24px; font-weight: 600; color: #000;">
        Ce que vous allez recevoir :
    </p>

    <table style="width: 100%; border-collapse: collapse; margin-top: 16px;">
        <tr>
            <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; width: 28px;">
                <span style="color: #F6475F; font-weight: 700; font-size: 16px;">→</span>
            </td>
            <td style="padding: 12px 0 12px 12px; border-bottom: 1px solid #f1f5f9;">
                <strong>Nouvelles annonces exclusives</strong><br>
                <span style="color: #6b7280; font-size: 13px;">Soyez le premier informé des biens récemment publiés.</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; width: 28px;">
                <span style="color: #F6475F; font-weight: 700; font-size: 16px;">→</span>
            </td>
            <td style="padding: 12px 0 12px 12px; border-bottom: 1px solid #f1f5f9;">
                <strong>Tendances du marché</strong><br>
                <span style="color: #6b7280; font-size: 13px;">Analyses et évolutions des prix de l'immobilier au Cameroun.</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 12px 0; vertical-align: top; width: 28px;">
                <span style="color: #F6475F; font-weight: 700; font-size: 16px;">→</span>
            </td>
            <td style="padding: 12px 0 12px 12px;">
                <strong>Conseils immobiliers</strong><br>
                <span style="color: #6b7280; font-size: 13px;">Guides pratiques pour acheter, louer ou investir en toute confiance.</span>
            </td>
        </tr>
    </table>

    <div class="btn-wrapper">
        <a href="{{ config('app.frontend_url', config('app.url')) }}" class="btn">
            Découvrir les annonces
        </a>
    </div>

    <p class="fallback" style="margin-top: 24px;">
        Vous pouvez vous désabonner à tout moment en cliquant
        <a href="{{ $unsubscribeUrl }}" class="link">ici</a>.
    </p>

@endsection
