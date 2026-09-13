#!/usr/bin/env bash
#
# publish.sh — à lancer sur la machine de développement.
#
# Vérifie que le dépôt est publiable, puis pousse sur GitHub.
# Ne déploie rien : c'est deploy.sh, sur le serveur, qui s'en charge.
#
#   ./publish.sh                      message déduit des fichiers modifiés
#   ./publish.sh "message de commit"  message imposé
#
set -euo pipefail

VERT='\033[0;32m'; ROUGE='\033[0;31m'; JAUNE='\033[0;33m'; NEUTRE='\033[0m'
etape() { echo -e "\n${VERT}▸ $1${NEUTRE}"; }
alerte() { echo -e "${JAUNE}  ⚠ $1${NEUTRE}"; }
abandon() { echo -e "${ROUGE}✗ $1${NEUTRE}" >&2; exit 1; }

cd "$(dirname "$0")"

MESSAGE="${1:-}"

# ---------------------------------------------------------------------------
# Message déduit des fichiers modifiés
#
# Les chemins du projet portent leur domaine : resources/js/Pages/Admin est du
# back office, app/Notifications des emails. On les regroupe plutôt que de
# lister trente fichiers, et le détail part dans le corps du commit.
# ---------------------------------------------------------------------------
deduire_message() {
    local fichiers domaines detail
    fichiers=$(git status --porcelain --untracked-files=all | awk '{print $NF}')

    domaines=$(echo "$fichiers" | awk '
        $0 == "" { next }
        /^resources\/js\/Pages\/Admin/     { d["back office"]++;        next }
        /^resources\/js\/Pages\/Client/    { d["site public"]++;        next }
        /^resources\/js\/Pages\/Auth/      { d["connexion"]++;          next }
        /^resources\/js\/Pages\/Booking/   { d["tunnel de réservation"]++; next }
        /^resources\/js\/(Layouts|Components)/ { d["composants"]++;      next }
        /^resources\/css/                   { d["styles"]++;             next }
        /^resources\/views/                 { d["gabarits"]++;           next }
        /^app\/Notifications/               { d["notifications"]++;      next }
        /^app\/Http\/Controllers\/Admin/   { d["back office"]++;        next }
        /^app\/Http\/Controllers/          { d["contrôleurs"]++;        next }
        /^app\/Services|^app\/Support/     { d["services"]++;           next }
        /^app\/Models/                      { d["modèles"]++;            next }
        /^app\/Console/                     { d["commandes"]++;          next }
        /^database\/migrations/             { d["migrations"]++;         next }
        /^database\/seeders/                { d["données initiales"]++;  next }
        /^routes/                            { d["routes"]++;             next }
        /^public/                            { d["fichiers publics"]++;   next }
        /\.sh$|^README|\.md$/              { d["outillage"]++;          next }
                                             { d["divers"]++ }
        END {
            n = 0
            for (k in d) { liste[n++] = k " (" d[k] ")" }
            # Tri par nombre décroissant : le domaine le plus touché en tête
            for (i = 0; i < n; i++)
                for (j = i + 1; j < n; j++) {
                    gsub(/.*\(|\)/, "", a); a = liste[i]; b = liste[j]
                    ai = substr(a, index(a, "(") + 1); ai = substr(ai, 1, length(ai) - 1)
                    bi = substr(b, index(b, "(") + 1); bi = substr(bi, 1, length(bi) - 1)
                    if (bi + 0 > ai + 0) { t = liste[i]; liste[i] = liste[j]; liste[j] = t }
                }
            out = ""
            for (i = 0; i < n && i < 4; i++) out = out (out == "" ? "" : ", ") liste[i]
            if (n > 4) out = out " et " (n - 4) " autre(s)"
            print out
        }')

    local total ajouts suppressions
    total=$(echo "$fichiers" | grep -c .)
    ajouts=$(git status --porcelain --untracked-files=all | grep -c "^??" || true)
    suppressions=$(git status --porcelain --untracked-files=all | grep -c "^ D\|^D " || true)

    detail="Mise à jour — $domaines"

    printf '%s\n\n%s fichier(s) : %s ajout(s), %s suppression(s).\n\n%s\n' \
        "$detail" "$total" "$ajouts" "$suppressions" \
        "$(git status --porcelain --untracked-files=all | sed 's/^/  /')"
}

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

if [ -z "$MESSAGE" ]; then
    MESSAGE=$(deduire_message)
    echo
    echo "Message déduit :"
    echo "$MESSAGE" | head -3 | sed 's/^/  /'
    echo
fi

git commit -m "$MESSAGE"
git push origin "$(git rev-parse --abbrev-ref HEAD)"

echo -e "\n${VERT}✓ Publié.${NEUTRE}"
echo "  Sur le serveur : cd /var/www/barber && ./deploy.sh"
