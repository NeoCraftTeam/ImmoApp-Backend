<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

/**
 * @OA\Info(
 *     title="KeyHome API",
 *     version="1.0.0",
 *     description="Documentation complète de l'API KeyHome — plateforme immobilière multi-tenant pour l'Afrique subsaharienne francophone (Cameroun / CEMAC / UEMOA). Monnaie : XOF/XAF.",
 *
 *     @OA\Contact(
 *         email="dev@keyhome.app",
 *         name="Équipe KeyHome"
 *     ),
 *
 *     @OA\License(
 *         name="Propriétaire — NéoCraft",
 *         url="https://keyhome.app"
 *     )
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Serveur local (développement)"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     in="header",
 *     name="Authorization",
 *     bearerFormat="JWT",
 *     description="Token JWT issu de Clerk ou Sanctum. Format : Bearer {token}"
 * )
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     in="header",
 *     name="Authorization",
 *     bearerFormat="JWT",
 *     description="Token Sanctum (Personal Access Token). Format : Bearer {token}"
 * )
 *
 * ─── Tags — Annonces ────────────────────────────────────────────────────────
 *
 * @OA\Tag(name="� Annonces",        description="CRUD annonces, statuts, duplication, brouillons, KeyScore, boost")
 * @OA\Tag(name="🔄 Brouillons",       description="Modifications en attente (draft_payload) sur annonces publiées")
 * @OA\Tag(name="🔍 Filtre",           description="Recherche, filtres, facettes, autocomplete")
 * @OA\Tag(name="🗺️ Géo",              description="Recherche isochrone, heatmap prix, directions, geocoding")
 * @OA\Tag(name="📄 PDF / QR Code",    description="Génération de pancarte PDF et QR code d'annonce")
 * @OA\Tag(name="🤖 IA",               description="Amélioration de description, génération IA, recherche image")
 * @OA\Tag(name="📊 Interactions",     description="Vues, impressions, favoris, partages, clics téléphone")
 * @OA\Tag(name="📊 Analyses",         description="Analytics détaillées par annonce (owner)")
 * @OA\Tag(name="🚀 Boost",            description="Packs de boost, activation et gestion")
 *
 * ─── Tags — Bailleur / Gestion locative ─────────────────────────────────────
 * @OA\Tag(name="📊 Dashboard Bailleur", description="Statistiques et KPI du dashboard propriétaire")
 * @OA\Tag(name="👥 Locataires",        description="Gestion du carnet de locataires du bailleur")
 * @OA\Tag(name="📋 Baux / Contrats",   description="Contrats de bail, signatures, renouvellements")
 * @OA\Tag(name="✍️ Signatures",         description="Demandes et flux de signature électronique")
 * @OA\Tag(name="📊 Dépenses",          description="Suivi des dépenses par bien (maintenance, charges, etc.)")
 * @OA\Tag(name="� Factures",          description="Factures générées et téléchargement PDF")
 * @OA\Tag(name="🗓️ Réservations",      description="Réservations de visites (tentative et confirmées)")
 * @OA\Tag(name="📅 Disponibilités",    description="Créneaux de disponibilité du bailleur pour visites")
 *
 * ─── Tags — Utilisateur / Auth ──────────────────────────────────────────────
 * @OA\Tag(name="🔐 Authentification",  description="Login, logout, refresh token, MFA, Clerk JWT")
 * @OA\Tag(name="👤 Utilisateur",       description="Profil, préférences, avatar, historique connexions")
 * @OA\Tag(name="� Passkeys",          description="WebAuthn / passkeys — enregistrement et authentification")
 * @OA\Tag(name="🔒 GDPR",             description="Gestion des données personnelles, export, suppression")
 *
 * ─── Tags — Messagerie / Chat ────────────────────────────────────────────────
 * @OA\Tag(name="💬 Messagerie",        description="Conversations locataire-bailleur, messages, pièces jointes, E2EE")
 * @OA\Tag(name="⚖️ Litiges",          description="Ouverture de litiges, messages, preuves, transitions de statut")
 *
 * ─── Tags — Paiements ────────────────────────────────────────────────────────
 * @OA\Tag(name="� Paiements",         description="Initialisation, vérification, webhooks (Kpay, Stripe)")
 * @OA\Tag(name="💳 Méthodes de paiement", description="Gestion des méthodes de paiement Stripe (cartes)")
 * @OA\Tag(name="💰 Remboursements",    description="Demandes et traitements de remboursements")
 * @OA\Tag(name="� Crédits / Points",  description="Solde, achats de packs, dépenses en points")
 * @OA\Tag(name="🎁 Abonnements",       description="Plans d'abonnement, souscription, renouvellement")
 *
 * ─── Tags — Contenu / Référentiels ──────────────────────────────────────────
 * @OA\Tag(name="🏷️ Type d'annonce",   description="Référentiel des types de biens (appartement, villa, bureau…)")
 * @OA\Tag(name="🏙️ Ville",            description="Référentiel des villes disponibles sur la plateforme")
 * @OA\Tag(name="📍 Quartier",          description="Référentiel des quartiers par ville")
 * @OA\Tag(name="🏡 Tour 3D",           description="Upload scènes 360°, configuration hotspots, suppression tour")
 * @OA\Tag(name="💼 Agence",           description="Profil et gestion d'agence immobilière")
 * @OA\Tag(name="💼 Équipe",           description="Membres de l'équipe / agence, invitations")
 *
 * ─── Tags — Alertes / Notifications ─────────────────────────────────────────
 * @OA\Tag(name="� Alertes",           description="Alertes de recherche personnalisées, fréquences, comptage")
 * @OA\Tag(name="Notifications",         description="Notifications in-app : liste, lecture, suppression")
 * @OA\Tag(name="📱 Push / FCM",        description="Enregistrement et suppression des tokens FCM push")
 *
 * ─── Tags — Avis / Score ─────────────────────────────────────────────────────
 * @OA\Tag(name="⭐ Recommandations",   description="Annonces recommandées pour l'utilisateur")
 * @OA\Tag(name="⭐ Avis",             description="Avis et notes laissés sur les annonces")
 *
 * ─── Tags — Autres ───────────────────────────────────────────────────────────
 * @OA\Tag(name="📊 Statistiques",     description="Statistiques landing page, témoignages")
 * @OA\Tag(name="🎯 Enquêtes",         description="Enquêtes NPS et satisfaction, réponses anonymes")
 * @OA\Tag(name="📰 Newsletter",       description="Inscription/désinscription newsletter")
 * @OA\Tag(name="🏥 Santé",           description="Health check de l'API")
 * @OA\Tag(name="🔔 Préférences notif", description="Préférences de notification par canal et type")
 */
final class DocController {}
