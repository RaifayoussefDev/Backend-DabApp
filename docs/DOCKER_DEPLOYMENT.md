# Backend DabApp — Documentation Docker & déploiement

API **Laravel 12 / PHP 8.2**. Un conteneur applicatif (`app`) fait tourner, via
`supervisord` : **php-fpm + nginx + queue worker + scheduler + Reverb**. Un conteneur
`redis` l'accompagne. La base MySQL est **externe** (serveur dédié).

- Doc de passation (secrets, migration données, checklist) : [HANDOVER.md](DEPLOYMENT_HANDOVER.md)
- Fichiers : `Dockerfile`, `docker-compose.yml`, `docker/`, `.env.docker.example`

---

## 1. Architecture d'exécution

```
                    ┌─────────────────────────── serveur applicatif ───────────────────────────┐
   Internet ─ 443 ─▶ reverse-proxy hôte (nginx/traefik + TLS)                                   │
                    │        │                                                                  │
                    │        ▼  :8000                                                           │
                    │   ┌────────────── conteneur "app" (image buildée du repo) ──────────────┐            │
                    │   │  supervisord                                             │            │
                    │   │   ├─ nginx  :80  ──▶ php-fpm :9000  (routes /api/*)      │            │
                    │   │   ├─ php artisan queue:work                             │            │
                    │   │   ├─ php artisan schedule:work                          │            │
                    │   │   └─ php artisan reverb:start :8080  (WS /app, /apps)   │            │
                    │   └────────────────────────────────────────────────────────┘            │
                    │        │ 6379                        │ 3306                               │
                    │        ▼                             ▼                                    │
                    │   ┌─────────┐                 ┌──────────────── serveur DB ─────────────┐ │
                    │   │  redis  │                 │  MySQL 8 : dabapp_{prod,dev,test}       │ │
                    │   └─────────┘                 └────────────────────────────────────────┘ │
                    └───────────────────────────────────────────────────────────────────────────┘
```

Le reverse-proxy de l'hôte doit :
- proxifier `be.dabapp.co` → `127.0.0.1:8000`
- transmettre le WebSocket (`Upgrade`/`Connection`) pour `location /app` et `/apps` (Reverb)
- fixer `client_max_body_size` ≥ 30M (uploads photos)

---

## 2. Prérequis

| Où | Quoi |
|---|---|
| Poste dev | Docker Desktop / Docker Engine + plugin `compose` |
| Build | Dokploy — build du `Dockerfile` (aucun registre) |
| Serveur | Docker Engine + `docker compose`, un user dans le groupe `docker` |
| Serveur DB | MySQL 8 (ou MariaDB 10.6+), 3 bases + 3 users, port ouvert aux seules IP des serveurs app |

L'image embarque déjà : extensions PHP (`pdo_mysql, redis, gd, intl, zip, exif, pcntl, bcmath, opcache, sockets`), **Chromium** + polices latine/arabe (génération des plaques via `spatie/browsershot` + `screenshot.cjs`), Node 20, `nginx`, `supervisor`.

---

## 3. Développement local

### 3.1 Avec Docker (proche de la prod)

```bash
cp .env.docker.example .env
# éditer .env : APP_ENV=local, APP_DEBUG=true, DB_HOST=host.docker.internal (XAMPP),
#               REDIS_PASSWORD=..., APP_KEY=$(php artisan key:generate --show)
docker compose up --build
# API : http://localhost:8000  — health : http://localhost:8000/up
```

> `RUN_MIGRATIONS=true` (défaut) : l'entrypoint lance `php artisan migrate --force` au démarrage.

### 3.2 Sans Docker

```bash
composer install
npm ci && npm run build
php artisan key:generate
php artisan migrate
php artisan serve                     # http://localhost:8000
php artisan queue:work                # terminal séparé
php artisan schedule:work             # terminal séparé
php artisan reverb:start              # terminal séparé (si test WebSocket)
```

Voir aussi [reference: Local MySQL via XAMPP] dans la mémoire projet si `migrate` renvoie *connection refused*.

---

## 4. Construire l'image manuellement

```bash
docker build -t dabapp-backend:local .
docker run --rm -p 8000:80 --env-file .env dabapp-backend:local
```

Build multi-stage :
1. `composer:2` → deps PHP `--no-dev`, autoload optimisé
2. `node:20` → `npm run build` (Vite) + `node_modules` (puppeteer, sans télécharger Chromium)
3. `php:8.2-fpm-bookworm` → runtime

---

## 5. Variables d'environnement

`.env.docker.example` est la référence complète. Les valeurs **diffèrent par environnement**.

| Clé | prod | dev / test | Note |
|---|---|---|---|
| `APP_ENV` | `production` | `dev` / `test` | |
| `APP_DEBUG` | `false` | `true` | |
| `APP_KEY` | *(clé prod actuelle)* | générée | **ne jamais régénérer la prod** |
| `APP_URL` | `https://be.dabapp.co` | `https://be-dev.dabapp.co` | exact — sert aux callbacks PayTabs |
| `FRONTEND_URL` | `https://dabapp.co` | idem dev | |
| `DB_HOST` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | serveur DB, `dabapp_prod` | `dabapp_dev` / `dabapp_test` | |
| `REDIS_HOST` | `redis` | `redis` | nom du service compose |
| `REDIS_PASSWORD` | secret | secret | requis (compose l'exige) |
| `CACHE_STORE` / `QUEUE_CONNECTION` / `SESSION_DRIVER` | `redis` | `redis` | |
| `BROADCAST_CONNECTION` | `reverb` | `reverb` | |
| `REVERB_APP_ID/KEY/SECRET` | secret | secret | garder l'`APP_ID` actuel |
| `REVERB_HOST` | `be.dabapp.co` | `be-dev.dabapp.co` | hôte public (wss) |
| `REVERB_PORT` / `REVERB_SCHEME` | `443` / `https` | idem | |
| `MAIL_*` (Mailgun EU) | secret | secret / mailtrap | |
| `JWT_SECRET` | *(valeur prod actuelle)* | générée | **ne pas régénérer la prod** |
| `PAYTABS_ENVIRONMENT` | `live` | `test` | |
| `PAYTABS_LIVE_*` / `PAYTABS_TEST_*` | secret | secret | |
| `AISENSY_API_KEY` | secret | secret | OTP WhatsApp |
| `FIREBASE_PROJECT_ID` | `dabapp-3d853` | idem | + fichier monté (voir §6.1) |
| `FILESYSTEM_DISK` | `local` ou `s3` | idem | décision ouverte |
| `SWAGGER_USER` / `SWAGGER_PASSWORD` | secret | secret | |
| `RUN_MIGRATIONS` | `true` | `true` | mettre `false` sur un nœud secondaire |
| `GENERATE_SWAGGER` | `false` | `true` si docs exposées | |

Knobs lus par `docker-compose.yml` (pas Laravel) : `IMAGE_TAG`, `HTTP_PORT`, `GENERATE_SWAGGER`.

---

## 6. Déploiement — Dokploy (build depuis la source)

L'image est **construite depuis le dépôt** : `docker-compose.yml` a `build: .` et
**aucune** référence `ghcr.io`. Pas de registre, pas de credentials, pas de GitHub Actions.

### 6.1 Dokploy — type « Compose »

1. Nouveau service **Compose** → source = repo `Fadel-B-G/Dabapp-Backend`, branche `prod`.
2. Fichier : `docker-compose.yml`. Dokploy fait `docker compose up -d --build`.
3. **Environnement** : coller le contenu du `.env` (basé sur `.env.docker.example`) dans
   l'onglet *Environment* de Dokploy (ou fournir un fichier `.env`).
4. **Fichiers secrets** (onglet *Advanced → Volumes / File Mounts* de Dokploy) — créer :
   - `secrets/firebase_credentials.json`
   - `secrets/google-service-account.json`
5. **Domaine** : `be.dabapp.co` → service `app`, port conteneur `80`. TLS par Dokploy.
   Le WebSocket Reverb (`/app`, `/apps`) passe par le même domaine — Traefik/Dokploy
   gère l'upgrade automatiquement.
6. **Auto Deploy** (webhook) activé → chaque `git push` sur `prod` reconstruit + migre
   (l'entrypoint lance `migrate --force`).

### 6.2 Manuel (serveur avec Docker)

```bash
git clone -b prod https://github.com/Fadel-B-G/Dabapp-Backend.git /opt/dabapp
cd /opt/dabapp
mkdir -p secrets
#   -> déposer .env, secrets/firebase_credentials.json, secrets/google-service-account.json
docker compose up -d --build
docker compose logs -f app        # vérifier migrations + "ready"
```

### 6.3 Base de données (serveur MySQL séparé)

```sql
CREATE DATABASE dabapp_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'dabapp_prod'@'10.0.0.%' IDENTIFIED BY '...';
GRANT ALL PRIVILEGES ON dabapp_prod.* TO 'dabapp_prod'@'10.0.0.%';
```
Port `3306` ouvert **uniquement** aux IP des serveurs applicatifs.

### 6.4 Charger les uploads existants

```bash
docker compose cp ./storage-seed/. app:/var/www/html/storage/app/
docker compose exec app php artisan storage:link
```

### 6.5 Note build

Le stage Node (Vite) + Chromium alourdit le build. Prévoir de la RAM / du swap sur
l'hôte Dokploy ; en cas d'OOM au `npm run build`, ajouter du swap.

### 6.6 GitHub Actions

`.github/workflows/deploy.yml` (modèle GHCR + SSH) n'est **plus utilisé** — laissé en
`workflow_dispatch` seul, peut être supprimé.

```sql
CREATE DATABASE dabapp_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'dabapp_prod'@'10.0.0.%' IDENTIFIED BY '...';
GRANT ALL PRIVILEGES ON dabapp_prod.* TO 'dabapp_prod'@'10.0.0.%';
```

---

## 7. Opérations courantes

```bash
# logs (tous les process passent par stdout/stderr du conteneur)
docker compose logs -f app

# artisan
docker compose exec app php artisan <cmd>
docker compose exec app php artisan migrate:status
docker compose exec app php artisan tinker

# vider / reconstruire les caches
docker compose exec app php artisan optimize:clear
docker compose restart app        # l'entrypoint recache au boot

# relancer les workers sans redéployer
docker compose exec app php artisan queue:restart

# état des tâches planifiées
docker compose exec app php artisan schedule:list
```

---

## 8. Rollback

Les images sont taguées `:<branch>-<sha>` (immuables). Pour revenir en arrière :

```bash
cd /opt/dabapp
export IMAGE_TAG=prod-<sha_precedent>
docker compose up -d
```

⚠️ Si le déploiement fautif a migré la base, un rollback d'image ne défait pas la
migration. Prévoir une migration `down` testée ou un dump avant chaque déploiement prod
(le pipeline peut être complété par un `mysqldump` pré-déploiement).

---

## 9. Dépannage

| Symptôme | Piste |
|---|---|
| `SQLSTATE[HY000] [2002] Connection refused` | `DB_HOST` / firewall du serveur DB / user non autorisé pour l'IP |
| 502 au démarrage | php-fpm pas encore prêt / erreur dans `config:cache` → `docker compose logs app` |
| WebSocket ne se connecte pas | reverse-proxy hôte ne transmet pas `Upgrade`/`Connection` sur `/app` ; `REVERB_*` incohérents |
| PDF / plaque KO | Chromium : vérifier `PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium` ; polices arabes manquantes |
| 413 sur upload | `client_max_body_size` du reverse-proxy hôte + `post_max_size` (`docker/php.ini`) |
| Paiement PayTabs sans retour | `APP_URL` incorrect (callback), IP serveur non whitelistée côté PayTabs |
| `env()` renvoie null en prod | normal après `config:cache` — lire la valeur via `config(...)`, pas `env(...)` |
| CORS bloqué | ajouter l'origine dans `app/Http/Middleware/OwnCors.php` (liste en dur) |
| Migrations rejouées sur un 2ᵉ nœud | mettre `RUN_MIGRATIONS=false` sur les nœuds secondaires |

---

## 10. Changement de code déjà appliqué

`app/Http/Controllers/PlateGeneratorController.php` : le chemin de Chrome était figé
sur un chemin Cloudways (`.cache/puppeteer/chrome/linux-143…`). Il lit désormais
`PUPPETEER_EXECUTABLE_PATH` puis `CHROME_PATH`, avec l'ancien chemin en dernier recours.
`screenshot.cjs` nécessite le paquet `puppeteer` → il est copié dans l'image (Chromium
système, pas de téléchargement).
