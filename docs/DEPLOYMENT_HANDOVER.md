# DabApp Backend – Dossier de passation (migration serveur + Docker + déploiement)

Document à remettre au responsable infra / DevOps.
Stack : **Laravel 12 / PHP 8.2**, MySQL, Reverb (WebSocket), queue + scheduler.

Cible : **3 environnements** = 3 serveurs applicatifs séparés + serveur(s) DB à part.
CI/CD : **GitHub Actions → build image → GHCR → `docker compose` sur le serveur**.

| Branche Git | Environnement | Serveur app | Base de données | `APP_ENV` |
|---|---|---|---|---|
| `prod` | Production | serveur prod | `dabapp_prod` (serveur DB) | `production` |
| `dev`  | Développement | serveur dev | `dabapp_dev` | `dev` |
| `test` | Recette / QA | serveur test | `dabapp_test` | `test` |

> Aujourd'hui le repo a `main` + `develop`. Il faut **créer/renommer les branches** `prod`, `dev`, `test` (ou adapter les noms dans [.github/workflows/deploy.yml](../.github/workflows/deploy.yml)).

### Fichiers déjà préparés dans le repo

| Fichier | Rôle |
|---|---|
| [Dockerfile](../Dockerfile) | image multi-stage (composer → vite/puppeteer → php-fpm+nginx+supervisor) |
| [docker-compose.yml](../docker-compose.yml) | tourne sur chaque serveur : `app` + `redis` |
| [docker/](../docker/) | `nginx.conf`, `supervisord.conf` (fpm, nginx, queue, scheduler, reverb), `php.ini`, `entrypoint.sh` |
| [.env.docker.example](../.env.docker.example) | template du `.env` serveur (à remplir par env) |
| [.github/workflows/deploy.yml](../.github/workflows/deploy.yml) | pipeline build + deploy SSH |
| [.dockerignore](../.dockerignore) | |

---

## 1. Accès à fournir

| Élément | Détail |
|---|---|
| Dépôt Git | URL du repo + branche de prod (`main`) + accès (deploy key / compte) |
| Serveur actuel | SSH, pour récupérer : dump DB, dossier `storage/app`, `.env` de prod |
| Nouveau serveur | OS cible, specs (CPU/RAM/disk), accès SSH/root |
| Registre Docker | Si on pousse des images (Docker Hub / GitHub GHCR / registre privé) |
| DNS | Accès à la zone `dabapp.co` (sous-domaine API) |

---

## 2. Prérequis runtime

### PHP 8.2 – extensions requises
`pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, json, bcmath, fileinfo, curl, gd (ou imagick), zip, intl, exif, pcntl, posix, redis`

- `gd/imagick` → `intervention/image` (traitement images upload)
- `pcntl/posix` → `laravel/reverb` + `queue:work`
- `redis` (phpredis) → cache / queue / sessions en prod (recommandé, voir §4)

### Autres binaires
| Binaire | Pourquoi |
|---|---|
| Composer 2 | install des deps PHP |
| Node 20 + npm | build assets Vite **et** `spatie/browsershot` |
| Chrome / Chromium headless | `spatie/browsershot` → génération PDF (offres, reçus). Variables `.env` : `NODE_BINARY_PATH`, `NPM_BINARY_PATH`, `CHROME_PATH`, `PUPPETEER_EXECUTABLE_PATH` |
| MySQL 8 (ou MariaDB 10.6+) | base de données |
| Redis 7 | cache / queue / session / présence |

---

## 3. Processus à faire tourner (⚠️ pas juste le web)

| Process | Commande | Superviseur |
|---|---|---|
| Web | php-fpm + Nginx (docroot = `public/`) | systemd / conteneur |
| **Queue worker** | `php artisan queue:work --tries=3 --timeout=120` | Supervisor / `restart: always` |
| **Scheduler** | `* * * * * php artisan schedule:run` | cron dédié (1 seule instance) |
| **Reverb (WebSocket)** | `php artisan reverb:start --host=0.0.0.0 --port=8080` | Supervisor + reverse-proxy Nginx `wss://` |

### Tâches planifiées (dépendent du scheduler ci-dessus)
`payments:check-pending` (10 min), `user:update-online-status` (15 min), `trainer-bookings:expire-unpaid` (15 min), `notifications:dispatch-scheduled` (1 min), `events:send-reminders` / `trainer-reviews:auto-approve` (hourly), `soom:check-expired-validations` (09:00), `listings:send-follow-up` (18:00), `soom:auto-mark-sold` (daily), `SubscriptionExpirationJob` (00:00), `log:clear` (hebdo), `optimize:clear` (03:00).

---

## 4. Configuration `.env` de production (à livrer HORS Git, en secret)

> `.env` et `.env.example` sont dans le repo mais **sans les vrais secrets**. Le responsable doit recevoir un `.env` de prod complet via un canal sécurisé (gestionnaire de secrets, pas Slack/mail).

Changements obligatoires pour la prod :
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://be.dabapp.co        # doit être exact : utilisé pour les callbacks PayTabs
FRONTEND_URL=https://dabapp.co

# Recommandé en prod (au lieu de "database")
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST / REDIS_PASSWORD / REDIS_PORT

# DB du nouveau serveur
DB_HOST / DB_DATABASE / DB_USERNAME / DB_PASSWORD

# Reverb (public)
REVERB_HOST=be.dabapp.co
REVERB_PORT=443
REVERB_SCHEME=https
```

Secrets à transmettre (présents dans le `.env` actuel) :
- `APP_KEY`, `JWT_SECRET` — **garder les mêmes** (sinon toutes les sessions/tokens sont invalidés)
- `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET`
- Mail **Mailgun** : `MAIL_HOST=smtp.eu.mailgun.org`, `MAIL_USERNAME`, `MAIL_PASSWORD`
- **PayTabs** : `PAYTABS_LIVE_*` + `PAYTABS_TEST_*` + `PAYTABS_ENVIRONMENT` (mettre `live` en prod)
- **AISensy** (OTP WhatsApp) : `AISENSY_API_KEY`, `AISENSY_CAMPAIGN_NAME`, `AISENSY_USERNAME`
- **Firebase** (push FCM) : `FIREBASE_PROJECT_ID` + fichier (voir §5)
- Swagger : `SWAGGER_USER` / `SWAGGER_PASSWORD`
- `OTP_WHATSAPP_ENABLED`, `OTP_EMAIL_ENABLED`
- `AWS_*` → vides actuellement. **Décision à prendre** : garder le stockage sur disque local (volume) ou basculer sur S3.

---

## 5. Fichiers secrets hors `.env` (ne sont PAS dans Git)

À copier manuellement sur le nouveau serveur / monter en volume :
- `app/firebase_credentials.json` — compte de service Firebase (push notifications). Chemin défini par `FIREBASE_CREDENTIALS`.
- `storage/app/google-service-account.json` — compte de service Google
- `storage/app/public/google-service-account.json`

---

## 6. Données persistantes / volumes

| Chemin | Contenu | Action |
|---|---|---|
| Base MySQL | données applicatives | `mysqldump` depuis l'ancien serveur → import sur le nouveau |
| `storage/app/public/` | **uploads utilisateurs** (photos annonces, docs…) | rsync depuis l'ancien serveur + `php artisan storage:link` |
| `storage/app/private/` | fichiers privés | rsync |
| `storage/logs/` | logs | volume (pas critique) |
| `bootstrap/cache/`, `storage/framework/` | caches | volume ou tmpfs, permissions écriture pour www-data |

En Docker : volumes nommés pour `storage/app` et la DB. `storage/framework` et `bootstrap/cache` doivent être **inscriptibles par l'utilisateur PHP-FPM**.

---

## 7. DNS / domaine / SSL / CORS

- Choisir/confirmer le domaine API (ex. `be.dabapp.co`) → pointer l'enregistrement A vers le nouveau serveur.
- Certificat TLS (Let's Encrypt / reverse proxy).
- Nginx : proxy WebSocket pour Reverb (`/app`, `/apps` selon config Reverb) avec `Upgrade`/`Connection` headers.
- **CORS** : les origines autorisées sont en dur dans [app/Http/Middleware/OwnCors.php](../app/Http/Middleware/OwnCors.php) (`https://dabapp.co`, `https://old.dabapp.co`, localhost). `config/cors.php` autorise `*.dabapp.co`. Si le front change de domaine → modif code à prévoir.
- `post_max_size` / `upload_max_filesize` PHP : l'app renvoie une 413 propre au-delà de la limite. Fixer une valeur cohérente avec les uploads (photos multiples) — proposer **20–30 Mo**.

---

## 8. Services tiers à reconfigurer après migration

| Service | À faire |
|---|---|
| **PayTabs** | Vérifier l'URL de callback/IPN : l'app envoie `https://<APP_URL>/api/paytabs/callback`. Whitelister l'IP du nouveau serveur si besoin. Passer le profil en **live**. |
| **Firebase** | Rien si on garde le même projet + fichier credentials. |
| **AISensy** | Whitelister l'IP sortante du nouveau serveur si filtrage. |
| **Mailgun** | Domaine d'envoi `dabapp.co` déjà vérifié ; vérifier réputation IP / SPF si IP dédiée. |
| **Swagger** (`/api/documentation`) | protégé par basic auth (`SWAGGER_USER/PASSWORD`) — décider si on l'expose en prod. |

---

## 9. Image Docker (déjà dans le repo)

Une seule image, un seul conteneur `app` : `supervisord` fait tourner **php-fpm + nginx + queue worker + scheduler + reverb** (voir [docker/supervisord.conf](../docker/supervisord.conf)). Pas de conteneur MySQL — la DB est sur un serveur séparé, `DB_HOST` dans le `.env`.

Build multi-stage ([Dockerfile](../Dockerfile)) :
1. `composer:2` → deps PHP `--no-dev` + autoload optimisé
2. `node:20` → `npm run build` (Vite) + `node_modules` (puppeteer, sans télécharger Chromium)
3. `php:8.2-fpm-bookworm` → extensions (`pdo_mysql, redis, gd, intl, zip, exif, pcntl, bcmath, opcache, sockets…`), **Chromium système** + polices (latin + arabe) pour `spatie/browsershot` / génération des plaques

L'`entrypoint` reconstruit les caches (`config/route/event:cache`), fait `storage:link`, puis `php artisan migrate --force` (désactivable via `RUN_MIGRATIONS=false`).

**Volumes** (`docker-compose.yml`) :
- `storage` (nommé) → `storage/app` : uploads utilisateurs
- `./secrets/firebase_credentials.json` → `app/firebase_credentials.json` (ro)
- `./secrets/google-service-account.json` → `storage/app/{,public/}google-service-account.json` (ro)

**⚠️ Changement de code déjà appliqué** : [PlateGeneratorController.php](../app/Http/Controllers/PlateGeneratorController.php) avait le chemin Chrome en dur (`.cache/puppeteer/chrome/linux-143…`, spécifique Cloudways). Il lit maintenant `PUPPETEER_EXECUTABLE_PATH` / `CHROME_PATH` avec fallback. `screenshot.cjs` a besoin du package `puppeteer` → il est bien copié dans l'image.

---

## 10. CI/CD — GitHub Actions ([.github/workflows/deploy.yml](../.github/workflows/deploy.yml))

Flux : **push sur `prod` / `dev` / `test`** →

1. `build` : construit l'image, tag `ghcr.io/<owner>/<repo>:<branch>` + `:<branch>-<sha>`, push sur **GHCR**
2. `deploy` : SSH sur le serveur de l'environnement → `docker login ghcr.io` → `docker compose pull` → `docker compose up -d` (l'entrypoint applique les migrations)

### À configurer côté GitHub

- **3 Environments** dans *Settings → Environments* : `prod`, `dev`, `test` (protection rules sur `prod` : required reviewers).
- Secrets **par environnement** :

| Secret | Valeur |
|---|---|
| `SSH_HOST` | IP/hostname du serveur de cet env |
| `SSH_USER` | user de déploiement (dans le groupe `docker`) |
| `SSH_KEY` | clé privée SSH dédiée au déploiement |
| `DEPLOY_PATH` | ex. `/opt/dabapp` (où sont `docker-compose.yml` + `.env` + `secrets/`) |
| `GHCR_USER` | compte GitHub / bot ayant accès au package GHCR |
| `GHCR_TOKEN` | PAT `read:packages` (ou token du bot) |

- Le package GHCR doit être accessible aux 3 serveurs (package **private** + token, ou lié au repo).

### Setup initial de chaque serveur (une seule fois, manuel)

```bash
# Docker + compose plugin installés
sudo mkdir -p /opt/dabapp/secrets && cd /opt/dabapp
# déposer : docker-compose.yml (copié du repo), .env (rempli depuis .env.docker.example),
#           secrets/firebase_credentials.json, secrets/google-service-account.json
docker login ghcr.io -u <user> -p <token>
export IMAGE_TAG=prod            # dev / test
docker compose up -d
docker compose logs -f app
```

- Reverse-proxy de l'hôte (nginx/traefik + Let's Encrypt) : `be.dabapp.co` → `127.0.0.1:${HTTP_PORT}` (8000), avec passage WebSocket (`Upgrade`/`Connection`) pour `/app` et `/apps`.
- Serveur DB : créer les 3 bases + users, ouvrir le port 3306 **uniquement** aux IP des 3 serveurs app (réseau privé / security group).

---

## 11. Migration des données (prod)

```bash
# depuis l'ancien serveur (Cloudways)
mysqldump --single-transaction --routines --triggers -u USER -p DB > dabapp_prod.sql
rsync -avz storage/app/ user@NEW_DB_OR_APP:/opt/dabapp/storage-seed/

# sur le nouveau serveur DB
mysql -u root -p dabapp_prod < dabapp_prod.sql
# charger le volume storage (avant le 1er up, ou via `docker compose cp`)
```

---

## Checklist rapide de remise

- [ ] Accès repo GitHub + créer les branches `prod` / `dev` / `test`
- [ ] `.env` complet **par environnement** (canal sécurisé) — base sur `.env.docker.example`
- [ ] 3 fichiers JSON (Firebase + 2× Google service account)
- [ ] Dump MySQL récent + archive `storage/app/` (uploads)
- [ ] 3 serveurs app provisionnés (Docker + compose) + 1 serveur DB (3 bases)
- [ ] GitHub Environments `prod`/`dev`/`test` + secrets SSH/GHCR
- [ ] Package GHCR accessible aux serveurs
- [ ] Sous-domaines API + DNS + TLS (reverse-proxy hôte, WebSocket ok)
- [ ] `APP_KEY` et `JWT_SECRET` de prod **conservés** (pas régénérés)
- [ ] Décision stockage : disque local (volume) vs S3
- [ ] Décision : Swagger exposé en prod ou non
- [ ] PayTabs en `live` + callback `https://be.dabapp.co/api/paytabs/callback` ; whitelist IP PayTabs / Mailgun / AISensy
- [ ] Origines CORS à jour dans `app/Http/Middleware/OwnCors.php` si le front change de domaine
