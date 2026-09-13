#!/usr/bin/env bash
#
# publish.sh — à lancer sur la machine de développement.
#
# Vérifie que le dépôt est publiable, puis pousse sur GitHub.
# Ne déploie rien : c'est deploy.sh, sur le serveur, qui s'en charge.
#
#   ./publish.sh "message de commit"
#
set -euo pipefail

VERT='\033[0;32m'; ROUGE='\033[0;31m'; JAUNE='\033[0;33m'; NEUTRE='\033[0m'
etape() { echo -e "\n${VERT}▸ $1${NEUTRE}"; }
alerte() { echo -e "${JAUNE}  ⚠ $1${NEUTRE}"; }
abandon() { echo -e "${ROUGE}✗ $1${NEUTRE}" >&2; exit 1; }

cd "$(dirname "$0")"

MESSAGE="${1:-}"
[ -z "$MESSAGE" ] && abandon "Message de commit manquant. Usage : ./publish.sh \"votre message\""

# Sail ou PHP local, selon ce qui est disponible
if [ -f vendor/bin/sail ] && docker compose ps 2>/dev/null | grep -q "Up"; then
    PHP="./vendor/bin/sail php"
    NPM="./vendor/bin/sail npm"
    COMPOSER="./vendor/bin/sail composer"
else
    PHP="php"; NPM="npm"; COMPOSER="composer"
    alerte "Conteneurs Sail arrêtés — utilisation des outils locaux."
fi

# ---------------------------------------------------------------------------
etape "Aucun secret ne doit partir sur GitHub"
# ---------------------------------------------------------------------------
# Le dépôt est public : un .env poussé exposerait les identifiants de base,
# les clés Brevo et le mot de passe administrateur.
if git ls-files --error-unmatch .env >/dev/null 2>&1; then
    abandon ".env est suivi par Git. Retirez-le : git rm --cached .env"
fi

for motif in "storage/*.key" "auth.json" ".env.production"; do
    if git ls-files --error-unmatch "$motif" >/dev/null 2>&1; then
        abandon "$motif est suivi par Git — retirez-le avant de publier."
    fi
done
echo "  Aucun fichier sensible suivi."

# ---------------------------------------------------------------------------
etape "Les dépendances doivent s'installer sur le serveur (PHP 8.2)"
# ---------------------------------------------------------------------------
# Le conteneur tourne en PHP 8.5 : sans la clé « platform » dans composer.json,
# Composer choisit des paquets exigeant 8.4 et l'installation échoue en
# production. C'est le piège qui nous a coûté un déploiement.
if ! grep -q '"platform"' composer.json; then
    abandon "composer.json ne fixe pas la plateforme.
  Ajoutez dans la section \"config\" :
      \"platform\": { \"php\": \"8.2\" }
  puis lancez : $COMPOSER update"
fi

$COMPOSER check-platform-reqs --no-dev >/dev/null 2>&1 \
    || abandon "Des dépendances sont incompatibles avec PHP 8.2.
  Détail : $COMPOSER check-platform-reqs --no-dev"
echo "  Dépendances compatibles PHP 8.2."

# ---------------------------------------------------------------------------
etape "Compilation des fichiers front"
# ---------------------------------------------------------------------------
# Compilée ici uniquement pour VALIDER : public/build n'est pas versionné,
# le serveur recompile de son côté. Une erreur de syntaxe Vue doit être vue
# maintenant, pas pendant le déploiement.
$NPM run build >/dev/null 2>&1 || abandon "La compilation échoue. Lancez : $NPM run build"

grep -q "resources/css/app.css" public/build/manifest.json \
    || abandon "Le manifeste ne contient pas la feuille de style.
  Vérifiez le champ « input » de vite.config.js."
echo "  Compilation réussie, manifeste complet."

# ---------------------------------------------------------------------------
etape "Migrations et routes"
# ---------------------------------------------------------------------------
$PHP artisan route:list >/dev/null 2>&1 || abandon "Les routes ne se chargent pas."

NOUVELLES=$(git status --porcelain database/migrations | wc -l)
[ "$NOUVELLES" -gt 0 ] && alerte "$NOUVELLES migration(s) nouvelle(s) : deploy.sh sauvegardera la base avant de les appliquer."

# ---------------------------------------------------------------------------
etape "Publication"
# ---------------------------------------------------------------------------
if [ -z "$(git status --porcelain)" ]; then
    alerte "Aucune modification à publier."
    exit 0
fi

git status --short
echo
read -rp "Publier ces modifications ? [o/N] " reponse
[[ "$reponse" =~ ^[oO]$ ]] || abandon "Publication annulée."

git add -A
git commit -m "$MESSAGE"
git push origin "$(git rev-parse --abbrev-ref HEAD)"

echo -e "\n${VERT}✓ Publié.${NEUTRE}"
echo "  Sur le serveur : cd /var/www/barber && ./deploy.sh"
