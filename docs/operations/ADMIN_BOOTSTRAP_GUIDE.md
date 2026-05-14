# Guide Bootstrap Admin (Production)

Ce document explique comment creer le premier compte admin de facon securisee depuis un environnement sans admin existant.

## Contexte

Depuis le durcissement securite:

- `POST /api/v1/auth/registerAdmin` n'est plus public.
- Cette route exige:
  - une authentification `auth:sanctum`
  - une autorisation `can:admin-access` (role admin)

Donc sans admin existant, il faut un bootstrap initial.

## Important

- Ne pas utiliser `UserSeeder` en production (il cree aussi des donnees de test).
- Utiliser une creation ciblee du premier admin.

## Methode recommandee en production (Tinker one-shot)

1. Ouvrir Tinker sur le serveur:

```bash
php artisan tinker
```

2. Executer ce script (adapter les valeurs):

```php
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

User::updateOrCreate(
    ['email' => 'admin@votre-domaine.com'],
    [
        'firstname' => 'Super',
        'lastname' => 'Admin',
        'phone_number' => '+237600000000',
        'password' => Hash::make('MotDePasseTresFort!2026'),
        'role' => UserRole::ADMIN,
        'is_active' => true,
        'email_verified_at' => now(),
    ]
);
```

3. Quitter Tinker:

```bash
exit
```

## Verification

Verifier que l'utilisateur est admin:

```bash
php artisan tinker
```

```php
App\Models\User::where('email', 'admin@votre-domaine.com')->first(['id', 'email', 'role']);
```

## Connexion Postman avec cet admin

1. Login:

`POST /api/v1/auth/login`

Payload:

```json
{
  "email": "admin@votre-domaine.com",
  "password": "MotDePasseTresFort!2026"
}
```

2. Recuperer `access_token`.
3. Appeler:
   - `POST /api/v1/auth/registerAdmin`
   - Header: `Authorization: Bearer <access_token>`

## Resultats attendus

- Sans token: `401 Unauthorized`
- Token non admin: `403 Forbidden`
- Token admin: `201 Created`

## Bonnes pratiques apres bootstrap

- Changer immediatement le mot de passe admin initial.
- Stocker le secret dans un gestionnaire de secrets (pas dans un chat).
- Activer MFA pour les comptes admin (si disponible).
- Journaliser les creations d'admin et revoir regulierement les comptes privilegies.

## Runbook Ops (Checklist copier-coller)

### Pre-check (serveur)

- [ ] Etre sur le bon environnement (production, pas staging).
- [ ] Avoir une sauvegarde recente de la base.
- [ ] Avoir un acces shell + droit `php artisan`.
- [ ] Verifier que l'email cible n'existe pas deja.

Commande pre-check:

```bash
php artisan tinker --execute="App\Models\User::where('email','admin@votre-domaine.com')->exists();"
```

Retour attendu:

- `false` => on peut creer
- `true` => le compte existe deja (utiliser reset password)

### Creation one-shot

```bash
php artisan tinker --execute="
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

User::updateOrCreate(
    ['email' => 'admin@votre-domaine.com'],
    [
        'firstname' => 'Super',
        'lastname' => 'Admin',
        'phone_number' => '+237600000000',
        'password' => Hash::make('MotDePasseTresFort!2026'),
        'role' => UserRole::ADMIN,
        'is_active' => true,
        'email_verified_at' => now(),
    ]
);
"
```

### Post-check immediat

```bash
php artisan tinker --execute="
dump(
    App\Models\User::where('email','admin@votre-domaine.com')
        ->first(['id','email','role','is_active','email_verified_at'])
        ?->toArray()
);
"
```

- [ ] `role = admin`
- [ ] `is_active = true`
- [ ] `email_verified_at` non null

### Validation API

- [ ] Login ok via `POST /api/v1/auth/login`
- [ ] `registerAdmin` refuse un non-admin (`403`)
- [ ] `registerAdmin` accepte le token admin (`201`)

### Rollback rapide (si erreur de creation)

```bash
php artisan tinker --execute="
App\Models\User::where('email','admin@votre-domaine.com')->delete();
"
```

### Incident notes (a journaliser)

- Date/heure de creation
- Operateur ayant execute la procedure
- Email admin cree
- Ticket de changement / incident associe

