<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

/** Les huit questions de la maquette, avec leurs réponses. */
class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            [
                'question' => 'Est-ce que je peux venir si je ne suis pas asiatique ?',
                'answer'   => "Oui, bien sûr. Le studio est spécialisé dans les cheveux asiatiques, mais il accueille tous les types de cheveux et toutes les communautés.",
            ],
            [
                'question' => 'Comment prendre rendez-vous ?',
                'answer'   => "Sélectionnez la prestation et le créneau souhaités directement sur le site. Votre demande est ensuite envoyée au barber, qui vous confirme la disponibilité du rendez-vous.",
            ],
            [
                'question' => 'Mon rendez-vous est-il confirmé immédiatement ?',
                'answer'   => "Non. Le créneau est définitivement réservé uniquement après avoir reçu la confirmation du barber.",
            ],
            [
                'question' => 'Je ne sais pas quelle coupe choisir, est-ce un problème ?',
                'answer'   => "Pas du tout. Vous pouvez venir avec une idée, une photo d'inspiration ou simplement demander conseil. Le barber vous accompagne pour choisir une coupe adaptée à vos cheveux, votre visage et votre style.",
            ],
            [
                'question' => 'Comment dois-je préparer mes cheveux avant le rendez-vous ?',
                'answer'   => "Venez de préférence avec les cheveux propres, sans produit coiffant et dans leur état naturel afin de faciliter le diagnostic et la réalisation de la coupe.",
            ],
            [
                'question' => 'Où se trouve le studio ?',
                'answer'   => "Le studio privé se situe à Torcy. L'adresse complète et les informations d'accès sont communiquées lors de la confirmation du rendez-vous.",
            ],
            [
                'question' => 'Puis-je modifier ou annuler mon rendez-vous ?',
                'answer'   => "Oui, en prévenant le barber suffisamment à l'avance. En cas de retard important, la prestation pourra être adaptée ou reportée afin de ne pas décaler les rendez-vous suivants.",
            ],
            [
                'question' => 'Quels moyens de paiement sont acceptés ?',
                'answer'   => "Les moyens de paiement disponibles vous seront précisés au moment de la confirmation du rendez-vous.",
            ],
        ];

        foreach ($faqs as $i => $faq) {
            Faq::updateOrCreate(
                ['question' => $faq['question']],
                $faq + ['is_published' => true, 'position' => $i],
            );
        }
    }
}
