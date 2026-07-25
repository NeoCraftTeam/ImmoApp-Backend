---
inclusion: always
---

# Pas de Sail

Sur ce dépôt, **ne pas** utiliser `vendor/bin/sail` ni `./vendor/bin/sail`.

Exécuter directement sur l’hôte :

- `php artisan …`
- `composer …`
- `vendor/bin/pint`, `vendor/bin/phpstan`, `php artisan test`
- Frontend : `cd keyhome-frontend-next && npm …`

Docker / Sail peut exister pour d’autres environnements ; les agents ne doivent pas l’invoquer par défaut ici.
