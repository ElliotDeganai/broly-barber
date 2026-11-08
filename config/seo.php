<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Visibilité dans les moteurs de recherche
    |--------------------------------------------------------------------------
    |
    | false — valeur par défaut — fait partir un en-tête « noindex » sur
    | toutes les réponses. Le site reste parfaitement joignable par son
    | adresse ; il cesse simplement d'apparaître dans les résultats.
    |
    | Le défaut est volontairement fermé. Un environnement de test n'a pas
    | besoin d'être référencé pour le devenir : tout certificat HTTPS émis
    | est publié dans des journaux publics que des robots dépouillent en
    | continu. Avec ce défaut, chaque nouvel environnement naît muet, et
    | c'est la mise en ligne qui demande un geste explicite.
    |
    | Le jour du lancement, et sur le seul domaine définitif :
    |
    |     SITE_INDEXABLE=true
    |
    | À inscrire dans la checklist de mise en production — oublier cette
    | ligne, c'est un vrai site invisible.
    |
    */

    'indexable' => env('SITE_INDEXABLE', false),

    /*
    |--------------------------------------------------------------------------
    | Consigne envoyée aux robots
    |--------------------------------------------------------------------------
    |
    | « noindex » suffirait seul. Les trois autres ferment les portes
    | dérobées : la copie en cache, l'extrait affiché sous le lien, et les
    | images de la page — que l'en-tête d'une réponse PHP ne couvrirait pas
    | autrement, puisque les fichiers de storage sont servis directement par
    | le serveur web, sans passer par Laravel.
    |
    */

    'consigne' => 'noindex, nofollow, noarchive, noimageindex',

];
