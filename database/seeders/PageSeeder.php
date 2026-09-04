<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Pages libres et pages légales.
 *
 * ⚠ Les textes légaux sont des SQUELETTES à compléter, pas des documents
 * juridiquement valables. Ils doivent citer l'identité réelle de l'exploitant,
 * l'hébergeur et les traitements effectivement mis en œuvre.
 */
class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'slug'  => 'qui-suis-je',
                'title' => 'Qui suis-je ?',
                'body'  => "Barber visagiste installé à Torcy, spécialisé dans les cheveux asiatiques.\n\n[À compléter : parcours, formation, approche du métier, ce qui distingue le studio.]",
            ],
            [
                'slug'  => 'mentions-legales',
                'title' => 'Mentions légales',
                'body'  => "## Éditeur du site\n\n[Raison sociale] — [forme juridique]\n[Adresse]\n[Numéro SIRET]\n[Email de contact]\n\n## Hébergeur\n\n[Nom]\n[Adresse]\n\n## Propriété intellectuelle\n\nL'ensemble des contenus (textes, photographies, visuels) est la propriété de l'éditeur, sauf mention contraire.",
            ],
            [
                'slug'  => 'confidentialite',
                'title' => 'Politique de confidentialité',
                'body'  => "## Données collectées\n\nNom, adresse email, numéro de téléphone, historique des rendez-vous, montant des dettes éventuelles.\n\n## Finalité\n\nGestion des réservations, contact en cas de modification d'un rendez-vous, suivi du programme de fidélité.\n\n## Durée de conservation\n\n[À définir — par exemple 3 ans après le dernier rendez-vous.]\n\n## Vos droits\n\nAccès, rectification, effacement, portabilité et opposition. Écrire à [email de contact].\n\n## Connexion via réseaux sociaux\n\nEn cas de connexion via Instagram, Facebook ou TikTok, seules les données de profil de base sont récupérées.",
            ],
            [
                'slug'  => 'cookies',
                'title' => 'Gestion des cookies',
                'body'  => "Le site utilise uniquement des cookies techniques nécessaires au fonctionnement de la session et de l'authentification. Aucun cookie publicitaire ni de mesure d'audience tierce n'est déposé.",
            ],
            [
                'slug'  => 'annulation',
                'title' => "Conditions d'annulation",
                'body'  => "## Validation\n\nToute demande de rendez-vous est soumise à validation par le barber. Une réponse est apportée sous 24 heures.\n\n## Annulation\n\nL'annulation est possible jusqu'à 12 heures avant l'heure du rendez-vous, depuis votre espace client. Passé ce délai, contactez le studio.\n\n## Retard\n\nEn cas de retard important, la prestation pourra être adaptée ou reportée afin de ne pas décaler les rendez-vous suivants.\n\n## Annulations répétées\n\nDes annulations tardives répétées peuvent entraîner la suspension de l'accès à la réservation en ligne.\n\n## Paiement\n\nLe règlement s'effectue exclusivement au studio. Aucun paiement n'est encaissé en ligne.",
            ],
        ];

        foreach ($pages as $page) {
            Page::updateOrCreate(['slug' => $page['slug']], $page + ['is_published' => true]);
        }
    }
}
