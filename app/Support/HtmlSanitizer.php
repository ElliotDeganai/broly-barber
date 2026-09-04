<?php

namespace App\Support;

/**
 * Filtre le HTML reçu de l'éditeur enrichi.
 *
 * Le back office est réservé au barbier, mais on ne stocke jamais du HTML sans
 * le filtrer : un copier-coller depuis une page web peut amener un script, et
 * ce contenu est ensuite injecté tel quel dans les pages publiques.
 *
 * Liste blanche stricte : tout ce qui n'y figure pas est retiré, seul le texte
 * est conservé.
 */
class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'hr',
        'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del',
        'h2', 'h3', 'h4',
        'ul', 'ol', 'li',
        'blockquote', 'a',
    ];

    /**
     * Balises converties au lieu d'être supprimées.
     *
     * Dans un champ contenteditable, la touche Entrée produit un <div> sous
     * Chrome et un <p> sous Firefox. Supprimer le <div> fusionnerait tous les
     * paragraphes en un seul bloc : on le convertit.
     */
    private const RENAME = [
        'div'  => 'p',
        'h1'   => 'h2',   // le titre de page est déjà un h1
        'h5'   => 'h4',
        'h6'   => 'h4',
        'span' => null,   // null : on garde le contenu, on retire la balise
        'font' => null,
    ];

    /** Attributs conservés, par balise. */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title'],
    ];

    public static function clean(?string $html): string
    {
        if (! $html || trim(strip_tags($html)) === '') {
            return '';
        }

        // Contenu hérité, saisi en texte brut avant l'éditeur enrichi : sans
        // conversion, les sauts de ligne disparaîtraient à l'affichage et tout
        // le texte formerait un seul bloc.
        if (! preg_match('/<[a-z][\s\S]*>/i', $html)) {
            $html = self::fromPlainText($html);
        }

        $document = new \DOMDocument();

        // LIBXML_NOERROR : le HTML d'un éditeur est rarement un document
        // complet, les avertissements de structure ne nous intéressent pas.
        // L'en-tête XML force l'interprétation en UTF-8, sans quoi les accents
        // ressortent en mojibake.
        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div>' . $html . '</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();

        self::walk($document->documentElement);

        $out = '';

        foreach ($document->documentElement->childNodes as $child) {
            $out .= $document->saveHTML($child);
        }

        return trim($out);
    }

    /** Convertit du texte brut en paragraphes, en traitant les titres Markdown. */
    private static function fromPlainText(string $text): string
    {
        $blocks = preg_split('/\n{2,}/', trim($text));

        return collect($blocks)
            ->map(function (string $block) {
                $block = trim($block);

                // « ## Titre » devient un vrai titre de niveau 2
                if (preg_match('/^#{2,3}\s*(.+)$/u', $block, $m)) {
                    return '<h2>' . e($m[1]) . '</h2>';
                }

                return '<p>' . nl2br(e($block)) . '</p>';
            })
            ->implode("\n");
    }

    /** Parcourt l'arbre et retire ce qui n'est pas autorisé. */
    private static function walk(\DOMNode $node): void
    {
        // Copie du tableau : retirer un nœud pendant l'itération sur la
        // NodeList vivante en ferait sauter un sur deux.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMText) {
                continue;
            }

            if (! $child instanceof \DOMElement) {
                // Commentaires et instructions de traitement
                $node->removeChild($child);
                continue;
            }

            $tag = strtolower($child->nodeName);

            if (array_key_exists($tag, self::RENAME)) {
                $target = self::RENAME[$tag];

                if ($target === null) {
                    self::walk($child);
                    self::unwrap($child);
                } else {
                    $child = self::rename($child, $target);
                    self::stripAttributes($child, $target);
                    self::walk($child);
                }

                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                // On garde le texte : supprimer la balise ne doit pas faire
                // disparaître le contenu qu'elle entoure.
                self::walk($child);
                self::unwrap($child);

                continue;
            }

            self::stripAttributes($child, $tag);
            self::walk($child);
        }
    }

    private static function stripAttributes(\DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->nodeName);

            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->nodeName);
                continue;
            }

            // Un href en javascript: exécuterait du code au clic
            if ($name === 'href' && ! preg_match('#^(https?://|mailto:|tel:|/)#i', trim($attribute->nodeValue))) {
                $element->removeAttribute('href');
            }
        }

        // Les liens externes s'ouvrent dans un nouvel onglet, sans donner à la
        // page cible l'accès à la nôtre.
        if ($tag === 'a' && $element->hasAttribute('href')
            && str_starts_with($element->getAttribute('href'), 'http')) {
            $element->setAttribute('target', '_blank');
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    /**
     * Remplace une balise par une autre en conservant son contenu.
     *
     * DOM ne permet pas de renommer un élément : on en crée un nouveau et on
     * y déplace les enfants.
     */
    private static function rename(\DOMElement $element, string $tag): \DOMElement
    {
        $replacement = $element->ownerDocument->createElement($tag);

        while ($element->firstChild) {
            $replacement->appendChild($element->firstChild);
        }

        $element->parentNode->replaceChild($replacement, $element);

        return $replacement;
    }

    /** Remplace un élément par son contenu. */
    private static function unwrap(\DOMElement $element): void
    {
        $parent = $element->parentNode;

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }
}
