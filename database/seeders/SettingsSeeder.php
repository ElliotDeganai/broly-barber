<?php

namespace Database\Seeders;

use App\Models\SettingGroup;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

/**
 * Contenu et réglages de départ.
 *
 * Ce seeder POSE les valeurs initiales ; ensuite tout se modifie depuis
 * /admin/contenu. Relançable sans risque : les libellés et aides sont
 * rafraîchis, mais une valeur déjà saisie par le barbier n'est jamais écrasée.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $this->groups();
        $this->content();
        $this->parameters();
    }

    private function groups(): void
    {
        $groups = [
            ['home_hero',       'Accueil — Visuel',       "Le visuel plein écran, l'accroche et le bouton Réserver.", 'content', 1],
            ['home_steps',    'Accueil — Comment ça marche', 'Titre, introduction et libellés des trois étapes.', 'content', 2],
            ['home_services', 'Accueil — Prestations',   'Titre de la section prestations sur la page d\'accueil.', 'content', 2],
            ['home_products', 'Accueil — Produits',      'Titre et mention de vente de la section boutique.', 'content', 3],
            ['home_gallery',  'Accueil — Galerie',       'Titre de la section galerie.', 'content', 4],
            ['home_faq',      'Accueil — FAQ',           'Titre de la section questions fréquentes.', 'content', 5],
            ['services_header', 'Prestations — En-tête',  'Titre et introduction de la page prestations.', 'content', 2],
            ['services_late',   'Prestations — Dernière minute', "Le bandeau de l'offre après 20h.", 'content', 3],
            ['store_header',    'Boutique — En-tête',     'Titre et mention de vente au studio.', 'content', 4],
            ['gallery_header',  'Galerie — En-tête',      'Titre de la page galerie.', 'content', 5],
            ['faq_header',      'FAQ — En-tête',          'Titre de la foire aux questions.', 'content', 6],
            ['contact_header',  'Contact — En-tête',      "Titre et note sur l'adresse du studio.", 'content', 7],

            ['identity', 'Identité',            'Nom du studio et logo affichés dans la barre du haut.', 'settings', 1],
            ['social',   'Réseaux et contact',  'Liens affichés sur la page contact et en pied de page.', 'settings', 2],
            ['booking',  'Règles de réservation', 'Ces valeurs pilotent le calendrier et les messages vus par les clients.', 'settings', 3],
            ['pricing',  'Tarification',        "L'offre de dernière minute et son multiplicateur.", 'settings', 4],
        ];

        foreach ($groups as [$key, $label, $help, $scope, $position]) {
            SettingGroup::updateOrCreate(['key' => $key], compact('label', 'help', 'scope', 'position'));
        }
    }

    private function content(): void
    {
        // [clé, groupe, libellé, aide, type, valeur initiale]
        $fields = [
            ['home_hero_tagline',     'home_hero', 'Accroche',            "Première ligne, en jaune. Ex : « Studio Privé Visagiste ».", 'text',     'Studio Privé Visagiste'],
            ['home_hero_subtitle',    'home_hero', 'Sous-titre',          'Deuxième ligne sous l\'accroche.', 'text', 'Spécialisé dans les cheveux asiatiques'],
            ['home_hero_cta',         'home_hero', 'Texte du bouton',     'Ex : « Réserver ». Rester très court.', 'text', 'Réserver'],
            ['home_hero_image',       'home_hero', 'Visuel plein écran',  "Occupe tout l'écran d'accueil. Format portrait, au moins 1200 px de large.", 'image', null],
            ['home_hero_image_focus', 'home_hero', 'Cadrage du visuel',   'Partie à conserver si l\'image dépasse : center, top, bottom.', 'text', 'center'],

            ['home_hero_location',  'home_hero', 'Lieu',  'Ligne affichée au-dessus de l\'accroche. Ex : « Sur RDV — Marne-la-Vallée ».', 'text', 'Sur RDV — Marne-la-Vallée'],

            ['home_steps_title',    'home_steps', 'Titre',        'Ex : « Comment ça marche ».', 'text', 'Comment ça marche'],
            ['home_steps_subtitle', 'home_steps', 'Introduction', 'Une phrase sous le titre.', 'textarea', 'Trois étapes, et le fauteuil est à vous.'],

            ['home_services_title',  'home_services', 'Titre',            'Ex : « Prestations ».', 'text', 'Prestations'],
            ['home_products_title',  'home_products', 'Titre',            'Ex : « Produits ».', 'text', 'Produits'],
            ['home_products_notice', 'home_products', 'Mention de vente', 'Rappel affiché sous le titre.', 'text', 'Disponible uniquement au studio'],
            ['home_gallery_title',   'home_gallery',  'Titre',            'Ex : « Galerie ».', 'text', 'Galerie'],
            ['home_faq_title',       'home_faq',      'Titre',            'Ex : « FAQ ».', 'text', 'FAQ'],

            ['services_header_title',    'services_header', 'Titre',        'Ex : « Prestations ».', 'text', 'Prestations'],
            ['services_header_subtitle', 'services_header', 'Introduction', 'Une ou deux phrases sous le titre.', 'textarea', null],

            ['services_late_label', 'services_late', 'Nom de l\'offre', 'Ex : « Last minute ».', 'text', 'Last minute'],
            ['services_late_image', 'services_late', 'Visuel',          "Bandeau de l'offre de dernière minute.", 'image', null],

            ['store_header_title',  'store_header', 'Titre',              'Ex : « Only Sayajin Store ».', 'text', 'Only Sayajin Store'],
            ['store_header_notice', 'store_header', 'Mention de vente',   'Rappel affiché en orange sous le titre.', 'text', 'Disponible uniquement au studio'],
            ['store_header_image',  'store_header', 'Visuel de la gamme', "Bandeau affiché en tête de la boutique.", 'image', null],

            ['gallery_header_title', 'gallery_header', 'Titre', 'Ex : « Galerie ».', 'text', 'Galerie'],
            ['faq_header_title',     'faq_header',     'Titre', 'Ex : « FAQ ».', 'text', 'FAQ'],

            ['contact_header_title', 'contact_header', 'Titre', 'Ex : « Contact ».', 'text', 'Contact'],
            ['contact_header_note',  'contact_header', 'Note sous les coordonnées', "Le studio étant privé, l'adresse n'est pas publiée.", 'textarea', "L'adresse complète et les informations d'accès vous sont communiquées à la confirmation de votre rendez-vous."],
        ];

        $this->save($fields, false);
    }

    private function parameters(): void
    {
        // [clé, groupe, libellé, aide, type, valeur, système]
        $fields = [
            ['salon_name', 'identity', 'Nom du studio', 'Affiché dans la barre du haut et le pied de page.', 'text', 'Broly Asian Barber', false],
            ['logo',       'identity', 'Logo',          'Affiché en haut à gauche. PNG à fond transparent, hauteur minimale 200 px.', 'image', null, false],

            ['instagram_url',    'social', 'Lien Instagram',   'Adresse complète du profil.', 'url',  'https://instagram.com/broly.asianbarber', false],
            ['instagram_handle', 'social', 'Pseudo Instagram', 'Affiché sur la page contact.', 'text', 'broly.asianbarber', false],
            ['tiktok_url',       'social', 'Lien TikTok',      'Adresse complète du profil.', 'url',  'https://tiktok.com/@broly.asianbarber', false],
            ['tiktok_handle',    'social', 'Pseudo TikTok',    'Affiché sur la page contact.', 'text', 'broly.asianbarber', false],
            ['studio_address',   'social', 'Adresse du studio', "Communiquée au client dans l'email de confirmation, jamais affichée publiquement.", 'textarea', null, false],
            ['contact_email',    'social', 'Email de contact', 'Affiché sur la page contact.', 'text', 'brolyasianbarber@gmail.com', false],

            ['response_hours',       'booking', 'Délai de réponse annoncé',       "En heures. Affiché à la validation : « réponse sous X h ». C'est un engagement, pas un automatisme.", 'number', 24, true],
            ['cancellation_hours',   'booking', "Délai d'annulation",             'En heures avant le rendez-vous. Passé ce délai, le bouton Annuler disparaît côté client.', 'number', 12, true],
            ['booking_lead_hours',   'booking', 'Délai minimum avant un créneau', 'En heures. Empêche une réservation pour dans dix minutes.', 'number', 2, true],
            ['booking_horizon_days', 'booking', 'Profondeur du calendrier',       "En jours. Combien de temps à l'avance les clients peuvent réserver.", 'number', 30, true],
            ['auto_block_threshold', 'booking', 'Blocage automatique',            'Annulations sur six mois avant blocage automatique. 0 pour désactiver.', 'number', 3, true],

            ['late_night_hour',       'pricing', 'Heure de bascule',      "À partir de cette heure, le tarif est multiplié. 20 = 20h00.", 'number', 20, true],
            ['late_night_multiplier', 'pricing', 'Multiplicateur',        'Le tarif de la prestation est multiplié par cette valeur. 2 = tarif doublé.', 'number', 2, true],
            ['late_night_label',      'pricing', "Nom de l'offre",        'Affiché sur la page prestations.', 'text', 'Last minute', false],
        ];

        $this->save($fields, true);
    }

    /** La documentation est toujours rafraîchie, la valeur seulement à la création. */
    private function save(array $fields, bool $withSystem): void
    {
        foreach ($fields as $i => $field) {
            [$key, $group, $label, $help, $type, $value] = $field;

            $setting = SiteSetting::firstOrNew(['key' => $key]);
            $setting->fill(compact('group', 'label', 'help', 'type') + [
                'position'  => $i,
                'is_system' => $withSystem ? ($field[6] ?? false) : false,
            ]);

            if (! $setting->exists) {
                $setting->value = $value;
            }

            $setting->save();
        }
    }
}
