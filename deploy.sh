#!/usr/bin/env bash
#
# deploy.sh — à lancer sur le serveur, depuis /var/www/barber.
#
#   ./deploy.sh              déploiement courant
#   ./deploy.sh --no-build   saute la compilation front (rien n'a changé côté Vue)
#   ./deploy.sh --first-run  première installation : migrations + contenu initial
#
# Ne demande pas sudo au lancement : seules les commandes qui en ont besoin
# l'utilisent, et elles sont regroupées pour limiter les saisies de mot de passe.
#
set -euo pipefail

VERT='\033[0;32m'; ROUGE='\033[0;31m'; JAUNE='\033[0;33m'; NEUTRE='\033[0m'
etape() { echo -e "\n${VERT}▸ $1${NEUTRE}"; }
alerte() { echo -e "${JAUNE}  ⚠ $1${NEUTRE}"; }
abandon() { echo -e "${ROUGE}✗ $1${NEUTRE}" >&2; relever; exit 1; }

cd "$(dirname "$0")"
RACINE="$(pwd)"

WORKER="broly-worker"
SAUVEGARDES="$HOME/sauvegardes"
BUILD=1
PREMIERE=0

for arg in "$@"; do
    case "$arg" in
        --no-build)  BUILD=0 ;;
        --first-run) PREMIERE=1 ;;
        *) abandon "Option inconnue : $arg" ;;
    esac
done

# Le site est remis en ligne même si le script échoue en cours de route :
# mieux vaut une version ancienne qu'une page de maintenance oubliée.
MAINTENANCE=0
relever() {
    if [ "$MAINTENANCE" = 1 ]; then
        php artisan up >/dev/null 2>&1 || true
        MAINTENANCE=0
    fi
}
trap relever EXIT

# ---------------------------------------------------------------------------
etape "Vérifications préalables"
# ---------------------------------------------------------------------------
[ -f artisan ] || abandon "Ce script doit être lancé depuis la racine du projet."
[ -f .env ]    || abandon ".env absent. Copiez .env.example et renseignez-le."

grep -q "^APP_KEY=base64:" .env || abandon "APP_KEY vide. Lancez : php artisan key:generate"

# Signalé sans bloquer : le mode débogage est parfois voulu sur un serveur de
# test. À savoir tout de même — la page d'erreur y affiche la configuration
# complète, identifiants de base et clés d'envoi compris, à qui tombe dessus.
if grep -q "^APP_DEBUG=true" .env; then
    alerte "APP_DEBUG=true — la page d'erreur exposera la configuration aux visiteurs."
fi

grep -q "^APP_ENV=production" .env || alerte "APP_ENV n'est pas à « production »."

# Une modification faite directement sur le serveur bloquerait le git pull
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    echo
    git status --short
    abandon "Des fichiers suivis ont été modifiés sur le serveur.
  Pour les écarter : git checkout -- <fichier>
  Pour les conserver : git stash"
fi

php -r 'exit(version_compare(PHP_VERSION, "8.2", ">=") ? 0 : 1);' \
    || abandon "PHP 8.2 minimum requis (version actuelle : $(php -r 'echo PHP_VERSION;'))."

for ext in gd mbstring xml curl zip pdo_mysql; do
    php -m | grep -qi "^$ext$" || abandon "Extension PHP manquante : $ext
  sudo apt install php8.2-$ext && sudo systemctl restart php8.2-fpm"
done
echo "  Environnement conforme."

# ---------------------------------------------------------------------------
etape "Sauvegarde de la base"
# ---------------------------------------------------------------------------
# Avant toute migration : c'est le seul moyen de revenir en arrière si une
# migration se passe mal.
mkdir -p "$SAUVEGARDES"

DB_NAME=$(grep "^DB_DATABASE=" .env | cut -d= -f2- | tr -d '"'"'"' ')
DB_USER=$(grep "^DB_USERNAME=" .env | cut -d= -f2- | tr -d '"'"'"' ')
DB_PASS=$(grep "^DB_PASSWORD=" .env | cut -d= -f2- | sed 's/^"//; s/"$//')

DUMP="$SAUVEGARDES/${DB_NAME}-$(date +%F-%H%M).sql"

if MYSQL_PWD="$DB_PASS" mysqldump -u "$DB_USER" --single-transaction "$DB_NAME" > "$DUMP" 2>/dev/null; then
    gzip -f "$DUMP"
    echo "  $(basename "$DUMP").gz — $(du -h "$DUMP.gz" | cut -f1)"
    # On ne conserve que les dix dernières : au-delà, elles remplissent le disque
    ls -1t "$SAUVEGARDES"/*.sql.gz 2>/dev/null | tail -n +11 | xargs -r rm --
else
    alerte "Sauvegarde impossible — vérifiez les identifiants du .env."
    read -rp "  Continuer sans sauvegarde ? [o/N] " r
    [[ "$r" =~ ^[oO]$ ]] || abandon "Déploiement interrompu."
fi

# ---------------------------------------------------------------------------
etape "Mise en maintenance"
# ---------------------------------------------------------------------------
# Le secret permet de consulter le site pendant l'opération, pour vérifier
# avant de rouvrir au public.
SECRET="verif-$(date +%s)"
php artisan down --secret="$SECRET" --render="errors::503" >/dev/null 2>&1 || php artisan down >/dev/null 2>&1
MAINTENANCE=1
APP_URL=$(grep "^APP_URL=" .env | cut -d= -f2- | tr -d '"')
echo "  Accès de vérification : ${APP_URL}/${SECRET}"

# ---------------------------------------------------------------------------
etape "Récupération du code"
# ---------------------------------------------------------------------------
BRANCHE=$(git rev-parse --abbrev-ref HEAD)
AVANT=$(git rev-parse HEAD)

git pull --ff-only origin "$BRANCHE"

APRES=$(git rev-parse HEAD)
if [ "$AVANT" = "$APRES" ] && [ "$PREMIERE" = 0 ]; then
    alerte "Aucun nouveau commit."
else
    echo "  ${AVANT:0:7} → ${APRES:0:7}"
fi

# ---------------------------------------------------------------------------
etape "Dépendances PHP"
# ---------------------------------------------------------------------------
# --no-dev écarte Sail et Breeze, inutiles en production.
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# ---------------------------------------------------------------------------
if [ "$BUILD" = 1 ]; then
etape "Compilation des fichiers front"
# ---------------------------------------------------------------------------
    command -v npm >/dev/null || abandon "npm introuvable.
  Installez Node, ou compilez en local puis :
      rsync -av public/build/ utilisateur@serveur:$RACINE/public/build/
  et relancez avec --no-build"

    # `npm ci` respecte le fichier de verrouillage, là où `npm install` peut
    # choisir des versions différentes de celles testées en local.
    npm ci --silent
    npm run build

    [ -f public/build/manifest.json ] || abandon "Manifeste absent après compilation."
    grep -q "resources/css/app.css" public/build/manifest.json \
        || abandon "Le manifeste ne référence pas la feuille de style."
    echo "  Manifeste vérifié."
else
    alerte "Compilation sautée (--no-build)."
    [ -f public/build/manifest.json ] || abandon "Aucun manifeste existant : la compilation est indispensable."
fi

# ---------------------------------------------------------------------------
etape "Purge des caches"
# ---------------------------------------------------------------------------
# AVANT les migrations : une configuration en cache pointerait sur d'anciens
# identifiants de base et ferait échouer la connexion.
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear || alerte "Cache applicatif non vidé (base injoignable ?)."

# ---------------------------------------------------------------------------
etape "Base de données"
# ---------------------------------------------------------------------------
php artisan migrate --force

if [ "$PREMIERE" = 1 ]; then
    echo "  Contenu initial…"
    php artisan db:seed --force
fi

# Git ne versionne pas les dossiers vides : ils manquent après un clone.
mkdir -p storage/framework/{sessions,views,cache/data} storage/logs storage/app/public

[ -L public/storage ] || php artisan storage:link

# ---------------------------------------------------------------------------
etape "Droits d'accès"
# ---------------------------------------------------------------------------
# ubuntu:www-data — vous gardez la main pour éditer, PHP peut écrire ses
# journaux, sessions, caches et images téléversées.
sudo chown -R "$(id -un)":www-data "$RACINE"
sudo chmod -R 775 storage bootstrap/cache
sudo find storage public/storage -type d -exec chmod 775 {} \; 2>/dev/null || true

# ---------------------------------------------------------------------------
etape "Caches de production"
# ---------------------------------------------------------------------------
# En dernier, quand le .env est définitif. Au-delà, toute modification du .env
# reste sans effet jusqu'au prochain config:clear.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ---------------------------------------------------------------------------
etape "File d'attente"
# ---------------------------------------------------------------------------
# Le worker garde en mémoire le code chargé à son démarrage : sans
# redémarrage, il exécuterait l'ancienne version.
if sudo supervisorctl status "$WORKER:*" >/dev/null 2>&1; then
    sudo supervisorctl restart "$WORKER:*"
    sleep 2
    sudo supervisorctl status "$WORKER:*"
else
    alerte "Worker « $WORKER » introuvable dans Supervisor."
    if grep -q "^QUEUE_CONNECTION=database" .env; then
        alerte "QUEUE_CONNECTION=database SANS worker : aucun email ne partira."
    fi
fi

# ---------------------------------------------------------------------------
etape "Tâches planifiées"
# ---------------------------------------------------------------------------
if sudo crontab -u www-data -l 2>/dev/null | grep -q "schedule:run"; then
    echo "  Planificateur actif."
else
    alerte "Aucune entrée cron pour le planificateur — les rappels de rendez-vous ne partiront pas.
  sudo crontab -u www-data -e
  * * * * * cd $RACINE && php artisan schedule:run >> /dev/null 2>&1"
fi

# ---------------------------------------------------------------------------
etape "Remise en ligne"
# ---------------------------------------------------------------------------
php artisan up
MAINTENANCE=0

sleep 1
CODE=$(curl -s -o /dev/null -w "%{http_code}" "$APP_URL" || echo "000")

if [ "$CODE" = "200" ]; then
    echo -e "\n${VERT}✓ Déploiement terminé — $APP_URL répond ($CODE).${NEUTRE}"
else
    echo -e "\n${ROUGE}✗ Le site répond $CODE.${NEUTRE}"
    echo "  Dernière erreur :"
    grep "production.ERROR" storage/logs/laravel.log 2>/dev/null | tail -1 | cut -c1-300 || echo "  (journal vide)"
    echo
    echo "  Retour arrière :"
    echo "    git reset --hard $AVANT && ./deploy.sh --no-build"
    echo "    zcat $DUMP.gz | mysql -u $DB_USER -p $DB_NAME"
    exit 1
fi

# ---------------------------------------------------------------------------
etape "Contrôles finaux"
# ---------------------------------------------------------------------------
ATTENTE=$(php artisan tinker --execute="echo DB::table('jobs')->count();" 2>/dev/null | tr -dc '0-9' || echo "?")
ECHECS=$(php artisan tinker --execute="echo DB::table('failed_jobs')->count();" 2>/dev/null | tr -dc '0-9' || echo "?")
echo "  File d'attente : $ATTENTE en attente, $ECHECS en échec."
[ "${ECHECS:-0}" != "0" ] && alerte "Consultez les échecs : php artisan queue:failed"

echo "  Sauvegarde conservée : $DUMP.gz"
